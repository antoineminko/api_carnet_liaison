<?php

namespace App\Services;

use App\Models\Devoir;
use App\Models\Note;
use App\Models\ParentUser;
use Illuminate\Support\Facades\DB;

class NoteService
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Publie des notes pour une classe et notifie les parents.
     *
     * @param array $data Données validées
     * @param int|null $enseignantId Identifiant de l'enseignant
     * @return array Résultat contenant le devoir créé et le nombre de notifications
     */
    public function createNotes(array $data, ?int $enseignantId = null): array
    {
        $enseignantId = $enseignantId ?? (auth()->id() ?? 1);

        // 1. Create the Devoir (acts as Evaluation metadata)
        $devoir = Devoir::create([
            'classe_id' => $data['classe_id'],
            'enseignant_id' => $enseignantId,
            'matiere' => $data['matiere'],
            'titre' => $data['titre'] . ' (' . $data['numero'] . ')',
            'description' => 'Évaluation du ' . $data['trimestre'],
            'type' => $data['type'],
            'date_remise' => $data['date'] ?? date('Y-m-d'),
        ]);

        $targets = collect();

        // 2. Insert the notes
        $eleveIds = collect($data['notes'])->pluck('eleve_id')->toArray();
        $eleves = DB::table('eleves')->whereIn('id', $eleveIds)->get()->keyBy('id');
        $eleveParents = DB::table('eleve_parents')->whereIn('eleve_id', $eleveIds)->get()->groupBy('eleve_id');

        foreach ($data['notes'] as $noteData) {
            $eleveId = $noteData['eleve_id'];
            Note::create([
                'devoir_id' => $devoir->id,
                'eleve_id' => $eleveId,
                'valeur' => $noteData['note'],
                'trimestre' => $data['trimestre'],
                'commentaires' => $noteData['commentaires'] ?? null,
            ]);

            // Attach to devoir_eleve pivot
            DB::table('devoir_eleve')->insertOrIgnore([
                'devoir_id' => $devoir->id,
                'eleve_id' => $eleveId,
                'statut' => 'publié',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Find parents for this child (optimisé)
            $parentsForChild = $eleveParents->get($eleveId, collect());
            $eleve = $eleves->get($eleveId);

            foreach ($parentsForChild as $pivot) {
                $targets->push([
                    'parent_id' => $pivot->parent_id,
                    'eleve_id' => $eleveId,
                    'eleve_nom' => $eleve->prenom ?? 'votre enfant',
                ]);
            }
        }

        $sentCount = $this->notifyParents($targets, $devoir, $data);

        return [
            'devoir_id' => $devoir->id,
            'notifications_envoyees' => $sentCount
        ];
    }

    /**
     * Notifie les parents pour les nouvelles notes.
     */
    protected function notifyParents($targets, Devoir $devoir, array $data): int
    {
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
                    'type' => 'new_grade',
                    'title' => "Nouvelle Note : {$data['matiere']}",
                    'message' => "Une note a été publiée pour " . $childTarget['eleve_nom'] . ".\nÉvaluation: {$data['titre']}",
                    'data' => json_encode([
                        'type' => 'new_grade',
                        'devoir_id' => (string) $devoir->id,
                        'eleve_id' => (string) $childTarget['eleve_id'],
                        'matiere' => $data['matiere'],
                    ]),
                    'is_read' => false,
                ]);
            }

            // Fallback: Send 1 push notification per parent using old mobile-compatible format (new_grade)
            if (!empty($parent->fcm_token) && count($childrenTargets) > 0) {
                $firstChild = $childrenTargets->first();
                $title = "Nouvelles évaluations : {$data['matiere']}";
                
                $pushBody = count($childrenTargets) > 1 
                    ? "Les résultats scolaires de vos enfants sont disponibles.\nÉvaluation: {$data['titre']}"
                    : "Les résultats scolaires de " . $firstChild['eleve_nom'] . " sont disponibles.\nÉvaluation: {$data['titre']}";

                $this->notificationService->sendPushOnly(
                    $parent->fcm_token,
                    $title,
                    $pushBody,
                    [
                        'type' => 'new_grade',
                        'devoir_id' => (string) $devoir->id,
                        'eleve_id' => (string) $firstChild['eleve_id'],
                        'child_name' => $firstChild['eleve_nom'],
                        'matiere' => $data['matiere'],
                    ]
                );
                $sentCount++;
            }
        }

        return $sentCount;
    }
}
