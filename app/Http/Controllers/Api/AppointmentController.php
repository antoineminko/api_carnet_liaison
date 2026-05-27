<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\ParentUser;
use App\Models\Enseignant;
use App\Services\PushNotificationService;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'enseignant_id' => 'required|integer',
            'parent_id'     => 'required|integer',
            'eleve_id'      => 'nullable|integer',
            'date_heure'    => 'required|date',
            'type'          => 'required|in:physique,video',
            'motif'         => 'nullable|string',
            'requester'     => 'required|in:parent,enseignant'
        ]);

        $lien_video = null;
        if ($request->type === 'video') {
            // Générer un lien Jitsi unique
            $roomName = "RendezVous-" . Str::random(10);
            $lien_video = "https://meet.jit.si/" . $roomName;
        }

        $appointment = Appointment::create([
            'enseignant_id' => $request->enseignant_id,
            'parent_id'     => $request->parent_id,
            'eleve_id'      => $request->eleve_id,
            'date_heure'    => $request->date_heure,
            'type'          => $request->type,
            'lien_video'    => $lien_video,
            'statut'        => 'en_attente',
            'motif'         => $request->motif,
        ]);

        // Envoi notification
        if ($request->requester === 'enseignant') {
            // Notifier le parent
            $parent = ParentUser::find($request->parent_id);
            $enseignant = Enseignant::find($request->enseignant_id);
            
            if ($parent && !empty($parent->fcm_token)) {
                $title = "Nouvelle demande de rendez-vous";
                $body = "L'enseignant {$enseignant->nom} a demandé un RDV pour le " . date('d/m/Y à H:i', strtotime($request->date_heure));
                
                $this->notificationService->sendToToken($parent->fcm_token, $title, $body, [
                    'appointment_id' => (string)$appointment->id,
                    'type' => 'appointment_request'
                ]);
            }
        } else {
            // Notifier l'enseignant (si l'enseignant avait une app avec FCM, on le mettrait ici)
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande de rendez-vous envoyée avec succès.',
            'appointment' => $appointment
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:accepte,refuse,reporte'
        ]);

        $appointment = Appointment::findOrFail($id);
        $appointment->update([
            'statut' => $request->statut
        ]);

        // Envoyer une notification de mise à jour au parent
        $parent = ParentUser::find($appointment->parent_id);
        if ($parent && !empty($parent->fcm_token)) {
            $title = "Mise à jour de votre rendez-vous";
            $body = "Votre rendez-vous a été " . $request->statut;
            
            $this->notificationService->sendToToken($parent->fcm_token, $title, $body, [
                'appointment_id' => (string)$appointment->id,
                'type' => 'appointment_update'
            ]);
        }

        return response()->json([
            'success' => true,
            'appointment' => $appointment
        ]);
    }

    public function index(Request $request)
    {
        // On peut filtrer par parent ou enseignant
        $query = Appointment::with(['parent', 'enseignant', 'eleve']);

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        if ($request->has('enseignant_id')) {
            $query->where('enseignant_id', $request->enseignant_id);
        }

        return response()->json([
            'success' => true,
            'appointments' => $query->orderBy('date_heure', 'desc')->get()
        ]);
    }
}
