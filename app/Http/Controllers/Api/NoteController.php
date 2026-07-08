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
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // 1. Create the Devoir (acts as Evaluation metadata)
            $devoir = Devoir::create([
                'classe_id' => $request->classe_id,
                'enseignant_id' => 1, // Defaulting to 1 for API tests
                'matiere' => $request->matiere,
                'titre' => $request->titre . ' (' . $request->numero . ')',
                'description' => 'Évaluation du ' . $request->trimestre,
                'type' => $request->type,
                'date_remise' => $request->date ?? date('Y-m-d'),
            ]);

            $sentCount = 0;

            // 2. Insert the notes
            foreach ($request->notes as $noteData) {
                Note::create([
                    'devoir_id' => $devoir->id,
                    'eleve_id' => $noteData['eleve_id'],
                    'valeur' => $noteData['note'],
                    'trimestre' => $request->trimestre,
                ]);

                // Attach to devoir_eleve pivot
                DB::table('devoir_eleve')->insertOrIgnore([
                    'devoir_id' => $devoir->id,
                    'eleve_id' => $noteData['eleve_id'],
                    'statut' => 'publié',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Find parents and notify them
                $parentIds = DB::table('eleve_parents')
                               ->where('eleve_id', $noteData['eleve_id'])
                               ->pluck('parent_id');

                $eleve = DB::table('eleves')->where('id', $noteData['eleve_id'])->first();

                foreach ($parentIds as $parentId) {
                    $parent = ParentUser::find($parentId);
                    if ($parent && !empty($parent->fcm_token)) {
                        $title = "Nouvelle Note : {$request->matiere}";
                        $body = "Une note a été publiée pour " . ($eleve->prenom ?? 'votre enfant') . ".\nÉvaluation: {$request->titre}";

                        $data = [
                            'type' => 'new_grade',
                            'devoir_id' => (string) $devoir->id,
                            'eleve_id' => (string) $noteData['eleve_id'],
                            'matiere' => $request->matiere,
                        ];

                        $this->notificationService->sendAndSave(
                            'parent',
                            $parentId,
                            $parent->fcm_token,
                            $title,
                            $body,
                            $data
                        );
                        $sentCount++;
                    }
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
