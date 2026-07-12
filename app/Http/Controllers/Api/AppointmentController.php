<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\ParentUser;
use App\Models\Enseignant;
use App\Models\Eleve;
use App\Services\PushNotificationService;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Créer une nouvelle demande de rendez-vous
     */
    public function store(Request $request)
    {
        // Adaptation for Flutter app payload which uses 'type' and 'motif'
        if (!$request->has('mode') && $request->has('type')) {
            $mode = 'presentiel';
            if ($request->type === 'video') $mode = 'video';
            if ($request->type === 'vocal') $mode = 'vocal';
            $request->merge(['mode' => $mode]);
        }
        if (!$request->has('objet') && $request->has('motif')) {
            $request->merge(['objet' => $request->motif]);
        }

        $request->validate([
            'enseignant_id' => 'required|integer',
            'parent_id'     => 'required|integer',
            'eleve_id'      => 'nullable|integer',
            'objet'         => 'required|string|max:255',
            'date_heure'    => 'required|date',
            'mode'          => 'required|in:presentiel,vocal,video',
            'motif'         => 'nullable|string',
            'requester'     => 'required|in:parent,enseignant'
        ]);

        // Récupérer les informations pour les notifications
        $parent = ParentUser::find($request->parent_id);
        $enseignant = Enseignant::find($request->enseignant_id);
        $eleve = $request->eleve_id ? Eleve::find($request->eleve_id) : null;

        if (!$parent || !$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Parent ou enseignant introuvable.'
            ], 404);
        }

        // Générer lien vidéo si mode = video
        $lien_video = null;
        if ($request->mode === 'video') {
            $roomName = "RendezVous-" . Str::random(10);
            $lien_video = "https://meet.jit.si/" . $roomName;
        }

        $appointment = Appointment::create([
            'enseignant_id' => $request->enseignant_id,
            'parent_id'     => $request->parent_id,
            'eleve_id'      => $request->eleve_id,
            'objet'         => $request->objet,
            'date_heure'    => $request->date_heure,
            'mode'          => $request->mode,
            'type'          => $request->mode === 'video' ? 'video' : 'physique', // legacy
            'lien_video'    => $lien_video,
            'statut'        => 'en_attente',
            'motif'         => $request->motif,
            'requester'     => $request->requester,
        ]);

        // Envoi notification enrichie
        if ($request->requester === 'enseignant') {
            // Notifier le parent
            if (!empty($parent->fcm_token)) {
                $title = "📅 Nouveau rendez-vous proposé";
                $enseignantNom = trim("{$enseignant->prenom} {$enseignant->nom}");
                $dateFormatee = date('d/m/Y à H:i', strtotime($request->date_heure));
                $body = "$enseignantNom propose un rendez-vous pour le $dateFormatee";

                $this->notificationService->sendAndSave('parent', $parent->id, $parent->fcm_token, $title, $body, [
                    'appointment_id' => (string)$appointment->id,
                    'type' => 'appointment_request',
                    'objet' => $request->objet,
                    'date_heure' => $request->date_heure,
                    'mode' => $request->mode,
                    'enseignant_nom' => $enseignantNom,
                    'eleve_nom' => $eleve ? "{$eleve->prenom} {$eleve->nom}" : null,
                    'statut' => 'en_attente'
                ]);
            }
        } else {
            // Notifier l'enseignant
            if (!empty($enseignant->fcm_token)) {
                $title = "📅 Nouvelle demande de rendez-vous";
                $parentNom = trim("{$parent->prenom} {$parent->nom}");
                $dateFormatee = date('d/m/Y à H:i', strtotime($request->date_heure));
                $body = "$parentNom demande un rendez-vous pour le $dateFormatee";

                $this->notificationService->sendAndSave('enseignant', $enseignant->id, $enseignant->fcm_token, $title, $body, [
                    'appointment_id' => (string)$appointment->id,
                    'type' => 'appointment_request',
                    'objet' => $request->objet,
                    'date_heure' => $request->date_heure,
                    'mode' => $request->mode,
                    'parent_nom' => $parentNom,
                    'eleve_nom' => $eleve ? "{$eleve->prenom} {$eleve->nom}" : null,
                    'statut' => 'en_attente'
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande de rendez-vous envoyée avec succès.',
            'appointment' => $appointment->load(['parent', 'enseignant', 'eleve'])
        ], 201);
    }

    /**
     * Mettre à jour le statut d'un rendez-vous (accepter, refuser, reporter)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:accepte,refuse,reporte',
            'new_proposed_date' => 'nullable|date|required_if:statut,reporte',
            'report_reason' => 'nullable|string|required_if:statut,reporte'
        ]);

        $appointment = Appointment::with(['parent', 'enseignant', 'eleve'])->findOrFail($id);

        $updateData = ['statut' => $request->statut];

        // Si report, enregistrer la nouvelle date proposée et la raison
        if ($request->statut === 'reporte') {
            $updateData['new_proposed_date'] = $request->new_proposed_date;
            $updateData['report_reason'] = $request->report_reason;
        }

        $appointment->update($updateData);

        // Préparer les données pour les notifications
        $parent = $appointment->parent;
        $enseignant = $appointment->enseignant;
        $eleve = $appointment->eleve;

        $enseignantNom = $enseignant ? trim("{$enseignant->prenom} {$enseignant->nom}") : 'Enseignant';
        $parentNom = $parent ? trim("{$parent->prenom} {$parent->nom}") : 'Parent';
        $eleveNom = $eleve ? trim("{$eleve->prenom} {$eleve->nom}") : null;

        // Déterminer qui notifier selon qui a fait la demande et qui met à jour
        if ($appointment->requester === 'parent') {
            // Le parent a fait la demande, l'enseignant met à jour -> notifier le parent
            if ($parent && !empty($parent->fcm_token)) {
                $this->sendStatusUpdateNotification(
                    $parent->fcm_token,
                    $request->statut,
                    $enseignantNom,
                    $parentNom,
                    $appointment,
                    'parent',
                    $parent->id
                );
            }
        } else {
            // L'enseignant a fait la demande, le parent met à jour -> notifier l'enseignant
            if ($enseignant && !empty($enseignant->fcm_token)) {
                $this->sendStatusUpdateNotification(
                    $enseignant->fcm_token,
                    $request->statut,
                    $parentNom,
                    $enseignantNom,
                    $appointment,
                    'enseignant',
                    $enseignant->id
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Statut du rendez-vous mis à jour.',
            'appointment' => $appointment->fresh(['parent', 'enseignant', 'eleve'])
        ]);
    }

    /**
     * Accepter une date reportée et mettre à jour le rendez-vous
     */
    public function acceptPostponedDate(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        if ($appointment->statut !== 'reporte' || !$appointment->new_proposed_date) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune date reportée disponible pour ce rendez-vous.'
            ], 400);
        }

        // Mettre à jour la date du rendez-vous avec la nouvelle date proposée
        $appointment->update([
            'date_heure' => $appointment->new_proposed_date,
            'statut' => 'accepte',
            'new_proposed_date' => null,
            'report_reason' => null
        ]);

        // Notifier l'autre partie
        $parent = ParentUser::find($appointment->parent_id);
        $enseignant = Enseignant::find($appointment->enseignant_id);

        if ($appointment->requester === 'parent') {
            // Notifier l'enseignant que le parent a accepté la nouvelle date
            if ($enseignant && !empty($enseignant->fcm_token)) {
                $parentNom = $parent ? trim("{$parent->prenom} {$parent->nom}") : 'Parent';
                $dateFormatee = date('d/m/Y à H:i', strtotime($appointment->date_heure));

                $this->notificationService->sendAndSave('enseignant', $enseignant->id, $enseignant->fcm_token,
                    "✅ Rendez-vous confirmé",
                    "$parentNom a accepté le rendez-vous reporté pour le $dateFormatee",
                    [
                        'appointment_id' => (string)$appointment->id,
                        'type' => 'appointment_accepted',
                        'statut' => 'accepte'
                    ]
                );
            }
        } else {
            // Notifier le parent que l'enseignant a accepté la nouvelle date
            if ($parent && !empty($parent->fcm_token)) {
                $enseignantNom = $enseignant ? trim("{$enseignant->prenom} {$enseignant->nom}") : 'Enseignant';
                $dateFormatee = date('d/m/Y à H:i', strtotime($appointment->date_heure));

                $this->notificationService->sendAndSave('parent', $parent->id, $parent->fcm_token,
                    "✅ Rendez-vous confirmé",
                    "$enseignantNom a confirmé le rendez-vous pour le $dateFormatee",
                    [
                        'appointment_id' => (string)$appointment->id,
                        'type' => 'appointment_accepted',
                        'statut' => 'accepte'
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous confirmé avec la nouvelle date.',
            'appointment' => $appointment->load(['parent', 'enseignant', 'eleve'])
        ]);
    }

    /**
     * Envoyer une notification de mise à jour de statut
     */
    private function sendStatusUpdateNotification(
        $token,
        $statut,
        $nomInitiateur,
        $nomDestinataire,
        $appointment,
        $destinataireType,
        $destinataireId
    ) {
        $dateFormatee = date('d/m/Y à H:i', strtotime($appointment->date_heure));

        switch ($statut) {
            case 'accepte':
                $title = "✅ Rendez-vous accepté";
                $body = "$nomInitiateur a accepté votre demande de rendez-vous pour le $dateFormatee";
                $type = 'appointment_accepted';
                break;

            case 'refuse':
                $title = "❌ Rendez-vous refusé";
                $body = "$nomInitiateur a refusé votre demande de rendez-vous pour le $dateFormatee";
                $type = 'appointment_refused';
                break;

            case 'reporte':
                $newDateFormatee = $appointment->new_proposed_date
                    ? date('d/m/Y à H:i', strtotime($appointment->new_proposed_date))
                    : '';
                $title = "🔄 Rendez-vous reporté";
                $body = "$nomInitiateur propose une nouvelle date : $newDateFormatee";
                $type = 'appointment_postponed';
                break;

            default:
                $title = "Mise à jour rendez-vous";
                $body = "Votre rendez-vous a été mis à jour.";
                $type = 'appointment_update';
        }

        $data = [
            'appointment_id' => (string)$appointment->id,
            'type' => $type,
            'statut' => $statut,
            'objet' => $appointment->objet,
            'date_heure' => $appointment->date_heure,
            'mode' => $appointment->mode,
            'enseignant_nom' => $appointment->enseignant ? trim("{$appointment->enseignant->prenom} {$appointment->enseignant->nom}") : null,
            'parent_nom' => $appointment->parent ? trim("{$appointment->parent->prenom} {$appointment->parent->nom}") : null,
            'eleve_nom' => $appointment->eleve ? trim("{$appointment->eleve->prenom} {$appointment->eleve->nom}") : null,
        ];

        if ($statut === 'reporte' && $appointment->new_proposed_date) {
            $data['new_proposed_date'] = $appointment->new_proposed_date;
            $data['report_reason'] = $appointment->report_reason;
        }

        $this->notificationService->sendAndSave($destinataireType, $destinataireId, $token, $title, $body, $data);
    }

    /**
     * Liste des rendez-vous avec filtres
     */
    public function index(Request $request)
    {
        $query = Appointment::with(['parent', 'enseignant', 'eleve']);

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        if ($request->has('enseignant_id')) {
            $query->where('enseignant_id', $request->enseignant_id);
        }

        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        // Filtrer par statuts spécifiques (pour les événements en attente/acceptés)
        if ($request->has('status_in')) {
            $query->whereIn('statut', $request->status_in);
        }

        return response()->json([
            'success' => true,
            'appointments' => $query->orderBy('date_heure', 'desc')->get()
        ]);
    }

    /**
     * Obtenir les détails d'un rendez-vous
     */
    public function show($id)
    {
        $appointment = Appointment::with(['parent', 'enseignant', 'eleve'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'appointment' => $appointment
        ]);
    }

    /**
     * Annuler un rendez-vous
     */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string'
        ]);

        $appointment = Appointment::with(['parent', 'enseignant'])->findOrFail($id);

        // Seul le créateur peut annuler, ou les deux parties si accepté
        $appointment->update([
            'statut' => 'cancelled',
            'motif' => $request->reason ?? 'Rendez-vous annulé'
        ]);

        // Notifier l'autre partie
        $parent = $appointment->parent;
        $enseignant = $appointment->enseignant;

        if ($request->user_type === 'parent') {
            if ($enseignant && !empty($enseignant->fcm_token)) {
                $parentNom = $parent ? trim("{$parent->prenom} {$parent->nom}") : 'Parent';
                $this->notificationService->sendAndSave('enseignant', $enseignant->id, $enseignant->fcm_token,
                    "🚫 Rendez-vous annulé",
                    "$parentNom a annulé le rendez-vous.",
                    [
                        'appointment_id' => (string)$appointment->id,
                        'type' => 'appointment_cancelled',
                        'statut' => 'cancelled'
                    ]
                );
            }
        } else {
            if ($parent && !empty($parent->fcm_token)) {
                $enseignantNom = $enseignant ? trim("{$enseignant->prenom} {$enseignant->nom}") : 'Enseignant';
                $this->notificationService->sendAndSave('parent', $parent->id, $parent->fcm_token,
                    "🚫 Rendez-vous annulé",
                    "$enseignantNom a annulé le rendez-vous.",
                    [
                        'appointment_id' => (string)$appointment->id,
                        'type' => 'appointment_cancelled',
                        'statut' => 'cancelled'
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous annulé.',
            'appointment' => $appointment->fresh()
        ]);
    }
}
