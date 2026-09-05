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
        /* Rétrocompatibilité : Translation du payload Flutter natif (type, motif) vers le modèle interne (mode, objet) */
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

        /* Hydratation des entités nécessaires à la construction des notifications */
        $parent = ParentUser::find($request->parent_id);
        $enseignant = Enseignant::find($request->enseignant_id);
        $eleve = $request->eleve_id ? Eleve::find($request->eleve_id) : null;

        if (!$parent || !$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Parent ou enseignant introuvable.'
            ], 404);
        }

        /* Création d'une salle de visioconférence unique via Jitsi si le mode l'exige */
        $lien_video = null;
        if ($request->mode === 'video') {
            $roomName = "RendezVous-" . Str::random(10);
            $lien_video = "https://meet.jit.si/" . $roomName;
        }

        /* Résolution du contexte d'établissement (ecole_id) */
        $ecole_id = null;
        if ($request->has('ecole_id')) {
            $ecole_id = $request->ecole_id;
        } elseif ($eleve && $eleve->classe) {
            $ecole_id = $eleve->classe->ecole_id;
        }

        $appointment = Appointment::create([
            'ecole_id'      => $ecole_id,
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

        /* Déclenchement de la notification Push vers la partie prenante cible */
        if ($request->requester === 'enseignant') {
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
                    'ecole_id' => (string)$ecole_id,
                    'statut' => 'en_attente'
                ]);
            }
        } else {
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
                    'ecole_id' => (string)$ecole_id,
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

        /* Machine à états : Empêcher la modification d'un rendez-vous déjà finalisé */
        if (in_array($appointment->statut, ['accepte', 'refuse', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Le statut de ce rendez-vous est déjà définitif et ne peut plus être modifié.'
            ], 400);
        }

        /* Sécurité : Vérifier que c'est le bon interlocuteur qui répond (si l'app client envoie user_type) */
        if ($request->has('user_type')) {
            $expectedResponder = $appointment->requester === 'parent' ? 'enseignant' : 'parent';
            if ($request->user_type !== $expectedResponder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé. Vous ne pouvez pas répondre à cette demande.'
                ], 403);
            }
        }
        $updateData = ['statut' => $request->statut];

        /* Gestion du cycle de vie : Ajout de la contre-proposition lors d'un report */
        if ($request->statut === 'reporte') {
            $updateData['new_proposed_date'] = $request->new_proposed_date;
            $updateData['report_reason'] = $request->report_reason;
        }

        $appointment->update($updateData);


        $parent = $appointment->parent;
        $enseignant = $appointment->enseignant;
        $eleve = $appointment->eleve;

        $enseignantNom = $enseignant ? trim("{$enseignant->prenom} {$enseignant->nom}") : 'Enseignant';
        $parentNom = $parent ? trim("{$parent->prenom} {$parent->nom}") : 'Parent';
        $eleveNom = $eleve ? trim("{$eleve->prenom} {$eleve->nom}") : null;

        /* Routage de la notification de mise à jour vers la partie inverse de l'initiateur */
        if ($appointment->requester === 'parent') {
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

        /* Sécurité : L'initiateur de la demande initiale est celui qui doit accepter la contre-proposition */
        if ($request->has('user_type')) {
            $expectedResponder = $appointment->requester; // Si le parent a demandé, le parent accepte la nouvelle date
            if ($request->user_type !== $expectedResponder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé. Vous ne pouvez pas accepter cette contre-proposition.'
                ], 403);
            }
        }

        /* Validation de la contre-proposition et réinitialisation de l'état de report */
        $appointment->update([
            'date_heure' => $appointment->new_proposed_date,
            'statut' => 'accepte',
            'new_proposed_date' => null,
            'report_reason' => null
        ]);

        $parent = ParentUser::find($appointment->parent_id);
        $enseignant = Enseignant::find($appointment->enseignant_id);

        if ($appointment->requester === 'parent') {
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

        /* Machine à états : Empêcher d'annuler un rendez-vous déjà annulé ou refusé */
        if (in_array($appointment->statut, ['cancelled', 'refuse'])) {
            return response()->json([
                'success' => false,
                'message' => 'Ce rendez-vous est déjà annulé ou refusé.'
            ], 400);
        }
        /* Traitement de l'annulation par l'une des parties */
        $appointment->update([
            'statut' => 'cancelled',
            'motif' => $request->reason ?? 'Rendez-vous annulé'
        ]);


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

    /**
     * Marquer un rendez-vous comme lu par l'enseignant.
     */
    public function markAsRead($id)
    {
        $appointment = Appointment::findOrFail($id);

        /* Synchronisation : Acquittement automatique des notifications liées au rendez-vous */
        \App\Models\Notification::where('user_type', 'enseignant')
            ->where('user_id', $appointment->enseignant_id)
            ->whereIn('type', ['appointment_request', 'appointment_update'])
            ->whereJsonContains('data->appointment_id', (string)$id)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous marqué comme lu'
        ]);
    }
}
