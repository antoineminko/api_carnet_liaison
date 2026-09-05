<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\Conversation;
use App\Models\ParentUser;
use App\Services\PushNotificationService;

class ReportController extends Controller
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /* Création d'une alerte de modération suite à un comportement inapproprié (harcèlement, spam, etc.) */
    public function store(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'reporter_id' => 'required|integer',
            'reporter_type' => 'required|in:enseignant,parent',
            'reported_id' => 'required|integer',
            'reported_type' => 'required|in:enseignant,parent',
            'reason' => 'required|in:harassment,inappropriate_content,spam,fake_account,other',
            'description' => 'nullable|string',
            'evidence' => 'nullable|string',
        ]);

        $ecoleId = $request->attributes->get('school')?->id;
        if ($ecoleId) {
            $conversation = Conversation::find($request->conversation_id);
            if (!$conversation || $conversation->ecole_id != $ecoleId) {
                return response()->json(['error' => 'Conversation non trouvée ou accès refusé'], 403);
            }
        }

        /* Enregistrement de l'alerte en base de données avec le statut "en attente" */
        $report = Report::create([
            'conversation_id' => $request->conversation_id,
            'reporter_id' => $request->reporter_id,
            'reporter_type' => $request->reporter_type,
            'reported_id' => $request->reported_id,
            'reported_type' => $request->reported_type,
            'reason' => $request->reason,
            'description' => $request->description,
            'evidence' => $request->evidence,
            'status' => 'pending',
        ]);

        /* Déclenchement de l'alerte auprès de l'équipe de modération */
        $this->notifyAdmins($report);

        return response()->json([
            'success' => true,
            'message' => 'Signalement créé avec succès.',
            'report' => $report,
        ], 201);
    }

    /* Extraction de la liste des alertes de modération pour l'interface d'administration */
    public function index(Request $request)
    {
        $ecoleId = $request->attributes->get('school')?->id;

        $query = Report::with(['conversation']);

        if ($ecoleId) {
            $query->whereHas('conversation', function($q) use ($ecoleId) {
                $q->where('ecole_id', $ecoleId);
            });
        }


        if ($request->has('status')) {
            $query->where('status', $request->status);
        }


        if ($request->has('reason')) {
            $query->where('reason', $request->reason);
        }

        $reports = $query->orderBy('created_at', 'desc')->get();

        /* Hydratation des libellés et des noms pour l'affichage */
        foreach ($reports as $report) {
            $report->reporter_name = $this->getUserName($report->reporter_type, $report->reporter_id);
            $report->reported_name = $this->getUserName($report->reported_type, $report->reported_id);
            $report->reason_label = Report::getReasonLabel($report->reason);
            $report->status_label = Report::getStatusLabel($report->status);
        }

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    /* Extraction détaillée d'une alerte incluant le contexte de la conversation */
    public function show($id)
    {
        $report = Report::with(['conversation'])->findOrFail($id);

        $report->reporter_name = $this->getUserName($report->reporter_type, $report->reporter_id);
        $report->reported_name = $this->getUserName($report->reported_type, $report->reported_id);
        $report->reason_label = Report::getReasonLabel($report->reason);
        $report->status_label = Report::getStatusLabel($report->status);

        /* Extraction de l'historique récent de la conversation pour analyse par le modérateur */
        $messages = $report->conversation->messages()->orderBy('created_at', 'desc')->limit(50)->get();

        return response()->json([
            'success' => true,
            'report' => $report,
            'messages' => $messages,
        ]);
    }

    /* Traitement de l'alerte par la modération (Changement d'état) */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:in_review,resolved,rejected',
            'admin_notes' => 'nullable|string',
        ]);

        $report = Report::findOrFail($id);

        $updateData = [
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
        ];

        if ($request->status === 'resolved' || $request->status === 'rejected') {
            $updateData['resolved_at'] = now();
        }

        $report->update($updateData);

        /* Clôture de l'alerte : Information du plaignant concernant l'issue de son signalement */
        if ($request->status === 'resolved' || $request->status === 'rejected') {
            $this->notifyReporter($report, $request->status);
        }

        return response()->json([
            'success' => true,
            'message' => 'Statut du signalement mis à jour.',
            'report' => $report,
        ]);
    }

    /* Extraction de l'historique des alertes émises par un utilisateur */
    public function getUserReports(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|in:enseignant,parent',
        ]);

        $reports = Report::where('reporter_id', $request->user_id)
            ->where('reporter_type', $request->user_type)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($reports as $report) {
            $report->reason_label = Report::getReasonLabel($report->reason);
            $report->status_label = Report::getStatusLabel($report->status);
        }

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    /* Extraction de l'historique des alertes visant un utilisateur spécifique */
    public function getReportsAgainstUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|in:enseignant,parent',
        ]);

        $reports = Report::where('reported_id', $request->user_id)
            ->where('reported_type', $request->user_type)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($reports as $report) {
            $report->reason_label = Report::getReasonLabel($report->reason);
            $report->status_label = Report::getStatusLabel($report->status);
        }

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    private function getUserName(string $type, int $id): string
    {
        if ($type === 'enseignant') {
            $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')->where('id', $id)->first();
            return $enseignant ? trim("{$enseignant->prenom} {$enseignant->nom}") : 'Enseignant inconnu';
        } else {
            $parent = ParentUser::find($id);
            return $parent ? trim("{$parent->prenom} {$parent->nom}") : 'Parent inconnu';
        }
    }

    private function notifyAdmins(Report $report)
    {
        try {
        } catch (\Throwable $e) {
            \Log::error('Erreur notification admins : ' . $e->getMessage());
        }
    }

    private function notifyReporter(Report $report, string $status)
    {
        try {
            $title = $status === 'resolved' ? ' Signalement résolu' : ' Signalement rejeté';
            $body = $status === 'resolved'
                ? "Votre signalement (#{$report->id}) a été traité et résolu."
                : "Votre signalement (#{$report->id}) a été rejeté après examen.";

            if ($report->reporter_type === 'parent') {
                $parent = ParentUser::find($report->reporter_id);
                if ($parent && !empty($parent->fcm_token)) {
                    $this->notificationService->sendAndSave(
                        'parent',
                        $parent->id,
                        $parent->fcm_token,
                        $title,
                        $body,
                        [
                            'type' => 'report_updated',
                            'report_id' => (string)$report->id,
                            'status' => $status,
                        ]
                    );
                }
            } else {
                $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')->where('id', $report->reporter_id)->first();
                if ($enseignant && !empty($enseignant->fcm_token)) {
                    $this->notificationService->sendAndSave(
                        'enseignant',
                        $enseignant->id,
                        $enseignant->fcm_token,
                        $title,
                        $body,
                        [
                            'type' => 'report_updated',
                            'report_id' => (string)$report->id,
                            'status' => $status,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Erreur notification signaleur : ' . $e->getMessage());
        }
    }

    /* Extraction des alertes de modération impliquant un élève spécifique */
    public function getReportsForEleve($eleve_id)
    {
        try {
            $reports = Report::where('eleve_id', $eleve_id)
                ->with(['eleve', 'classe'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($report) {
                    return [
                        'id' => $report->id,
                        'reason' => $report->reason,
                        'description' => $report->description,
                        'status' => $report->status,
                        'created_at' => $report->created_at->format('d/m/Y H:i'),
                        'eleve_nom' => $report->eleve ? $report->eleve->nom : null,
                        'eleve_prenom' => $report->eleve ? $report->eleve->prenom : null,
                        'classe_nom' => $report->classe ? $report->classe->nom : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'reports' => $reports,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur récupération signalements élève: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des signalements',
            ], 500);
        }
    }
}
