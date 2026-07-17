<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Devoir;
use App\Models\Note;
use App\Models\ParentUser;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NoteController extends Controller
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'classe_id' => 'required|exists:classes,id',
            'titre' => 'required|string|max:255',
            'matiere' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'trimestre' => 'required|string|max:255',
            'numero' => 'nullable|string|max:255',
            'date' => 'nullable|date',
            'notes' => 'required|array',
            'notes.*.eleve_id' => 'required|exists:eleves,id',
            'notes.*.note' => 'required|numeric|min:0|max:20',
            'notes.*.commentaires' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $enseignantId = auth()->id() ?? 1;

            // 1. Create the Devoir (acts as Evaluation metadata)
            $devoir = Devoir::create([
                'classe_id' => $request->classe_id,
                'enseignant_id' => $enseignantId,
                'matiere' => $request->matiere,
                'titre' => $request->titre . ' (' . $request->numero . ')',
                'description' => 'Évaluation du ' . $request->trimestre,
                'type' => $request->type,
                'date_remise' => $request->date ?? date('Y-m-d'),
            ]);

            $targets = collect();

            // 2. Insert the notes
            foreach ($request->notes as $noteData) {
                Note::create([
                    'devoir_id' => $devoir->id,
                    'eleve_id' => $noteData['eleve_id'],
                    'valeur' => $noteData['note'],
                    'trimestre' => $request->trimestre,
                    'commentaires' => $noteData['commentaires'] ?? null,
                ]);

                // Attach to devoir_eleve pivot
                DB::table('devoir_eleve')->insertOrIgnore([
                    'devoir_id' => $devoir->id,
                    'eleve_id' => $noteData['eleve_id'],
                    'statut' => 'publié',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Find parents for this child
                $parentIds = DB::table('eleve_parents')
                               ->where('eleve_id', $noteData['eleve_id'])
                               ->pluck('parent_id');

                $eleve = DB::table('eleves')->where('id', $noteData['eleve_id'])->first();

                foreach ($parentIds as $parentId) {
                    $targets->push([
                        'parent_id' => $parentId,
                        'eleve_id' => $noteData['eleve_id'],
                        'eleve_nom' => $eleve->prenom ?? 'votre enfant',
                    ]);
                }
            }

            // 3. Group targets by parent to send 1 push per parent
            $groupedTargets = $targets->groupBy('parent_id');
            $sentCount = 0;

            foreach ($groupedTargets as $parentId => $childrenTargets) {
                $parent = ParentUser::find($parentId);
                if (!$parent) continue;

                // Save individual notifications in DB for each child so badges increment per child
                foreach ($childrenTargets as $childTarget) {
                    \App\Models\Notification::create([
                        'user_type' => 'parent',
                        'user_id' => $parentId,
                        'title' => "Nouvelle Note : {$request->matiere}",
                        'body' => "Une note a été publiée pour " . $childTarget['eleve_nom'] . ".\nÉvaluation: {$request->titre}",
                        'data' => json_encode([
                            'type' => 'new_grade',
                            'devoir_id' => (string) $devoir->id,
                            'eleve_id' => (string) $childTarget['eleve_id'],
                            'matiere' => $request->matiere,
                        ]),
                        'is_read' => false,
                    ]);
                }

                // Send 1 push notification per parent
                if (!empty($parent->fcm_token)) {
                    $title = "Nouvelles évaluations : {$request->matiere}";
                    
                    $pushBody = count($childrenTargets) > 1 
                        ? "Les résultats scolaires de vos enfants sont disponibles.\nÉvaluation: {$request->titre}"
                        : "Les résultats scolaires de " . $childrenTargets->first()['eleve_nom'] . " sont disponibles.\nÉvaluation: {$request->titre}";

                    $this->notificationService->sendPushOnly(
                        $parent->fcm_token,
                        $title,
                        $pushBody,
                        [
                            'type' => 'new_grade_group',
                            'classe_id' => (string) $request->classe_id,
                            'matiere' => $request->matiere,
                        ]
                    );
                    $sentCount++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Notes publiées avec succès.',
                'devoir_id' => $devoir->id,
                'notifications_envoyees' => $sentCount
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }
}
