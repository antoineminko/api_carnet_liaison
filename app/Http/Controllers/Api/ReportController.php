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

    /**
     * Créer un signalement
     */
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

        // Créer le signalement
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

        // Notifier les administrateurs (on peut notifier un rôle spécifique ou tous les admins)
        $this->notifyAdmins($report);

        return response()->json([
            'success' => true,
            'message' => 'Signalement créé avec succès.',
            'report' => $report,
        ], 201);
    }

    /**
     * Liste des signalements pour l'administration
     */
    public function index(Request $request)
    {
        $query = Report::with(['conversation']);

        // Filtrer par statut
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filtrer par motif
        if ($request->has('reason')) {
            $query->where('reason', $request->reason);
        }

        $reports = $query->orderBy('created_at', 'desc')->get();

        // Enrichir avec les noms
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

    /**
     * Obtenir les détails d'un signalement
     */
    public function show($id)
    {
        $report = Report::with(['conversation'])->findOrFail($id);

        $report->reporter_name = $this->getUserName($report->reporter_type, $report->reporter_id);
        $report->reported_name = $this->getUserName($report->reported_type, $report->reported_id);
        $report->reason_label = Report::getReasonLabel($report->reason);
        $report->status_label = Report::getStatusLabel($report->status);

        // Obtenir les messages de la conversation pour analyse
        $messages = $report->conversation->messages()->orderBy('created_at', 'desc')->limit(50)->get();

        return response()->json([
            'success' => true,
            'report' => $report,
            'messages' => $messages,
        ]);
    }

    /**
     * Mettre à jour le statut d'un signalement (admin only)
     */
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
            // Ici on pourrait récupérer l'ID de l'admin authentifié
            // $updateData['resolved_by'] = auth()->id();
        }

        $report->update($updateData);

        // Notifier le signaleur de la résolution
        if ($request->status === 'resolved' || $request->status === 'rejected') {
            $this->notifyReporter($report, $request->status);
        }

        return response()->json([
            'success' => true,
            'message' => 'Statut du signalement mis à jour.',
            'report' => $report,
        ]);
    }

    /**
     * Signalements faits par un utilisateur
     */
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

    /**
     * Signalements reçus par un utilisateur
     */
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
            // Notifier tous les admins via FCM ou autre système
            // Pour l'instant, on log l'action
            \Log::info("Nouveau signalement créé : #{$report->id} - Motif: {$report->reason}");

            // Ici on pourrait envoyer des notifications push aux admins
            // ou envoyer un email, ou créer une notification dans le système
        } catch (\Throwable $e) {
            \Log::error('Erreur notification admins : ' . $e->getMessage());
        }
    }

    private function notifyReporter(Report $report, string $status)
    {
        try {
            $title = $status === 'resolved' ? '✅ Signalement résolu' : '❌ Signalement rejeté';
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

    // Obtenir les signalements liés à un élève spécifique
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
