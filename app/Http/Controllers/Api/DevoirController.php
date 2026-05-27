<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Devoir;
use App\Models\ParentUser;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\DB;

class DevoirController extends Controller
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'classe_id' => 'required|integer',
            'matiere' => 'required|string',
            'titre' => 'required|string',
            'description' => 'required|string',
            'date_remise' => 'required|date',
        ]);

        $devoir = Devoir::create([
            'classe_id' => $request->classe_id,
            'enseignant_id' => $request->enseignant_id, // can be null if not passed
            'matiere' => $request->matiere,
            'titre' => $request->titre,
            'description' => $request->description,
            'date_remise' => $request->date_remise,
        ]);

        // Envoyer une notification Push à tous les parents de cette classe
        $parentIds = DB::table('eleve_parents')
            ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
            ->where('eleves.classe_id', $request->classe_id)
            ->pluck('eleve_parents.parent_id')
            ->unique();

        $sentCount = 0;
        foreach ($parentIds as $parentId) {
            $parent = ParentUser::find($parentId);
            if ($parent && !empty($parent->fcm_token)) {
                $title = "Nouveau devoir : {$request->matiere}";
                $body = "À rendre pour le " . date('d/m/Y', strtotime($request->date_remise)) . "\n" . $request->titre;

                $this->notificationService->sendToToken($parent->fcm_token, $title, $body, [
                    'devoir_id' => (string) $devoir->id,
                    'type' => 'new_homework'
                ]);
                $sentCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Devoir créé avec succès.',
            'devoir' => $devoir,
            'notifications_envoyees' => $sentCount
        ], 201);
    }
}
