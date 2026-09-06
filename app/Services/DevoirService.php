<?php

namespace App\Services;

use App\Models\Devoir;
use App\Models\ParentUser;
use Illuminate\Support\Facades\DB;

class DevoirService
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Crée un devoir et notifie les parents concernés.
     *
     * @param array $data Données validées du devoir
     * @return Devoir
     */
    public function createDevoir(array $data): Devoir
    {
        $devoir = Devoir::create([
            'classe_id' => $data['classe_id'],
            'enseignant_id' => $data['enseignant_id'],
            'matiere' => $data['matiere'],
            'type' => $data['type'],
            'titre' => $data['titre'],
            'description' => $data['description'],
            'date_remise' => $data['date_remise'] ?? null,
            'date_realisation' => $data['date_realisation'] ?? null,
            'cahier_texte_id' => $data['cahier_texte_id'] ?? null,
        ]);

        /* Attachement sélectif : Création des liaisons de devoir pour un sous-groupe d'élèves */
        $selectedEleves = $data['eleves'] ?? [];
        if (!empty($selectedEleves)) {
            $insertData = [];
            foreach ($selectedEleves as $eleveId) {
                $insertData[] = [
                    'devoir_id' => $devoir->id,
                    'eleve_id' => $eleveId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('devoir_eleve')->insert($insertData);
        }

        $this->notifyParents($devoir, $selectedEleves);

        return $devoir;
    }

    /**
     * Notifie les parents pour un nouveau devoir.
     */
    protected function notifyParents(Devoir $devoir, array $selectedEleves = [])
    {
        /* Récupération des informations de l'enseignant et de l'école */
        $devoir->load(['enseignant', 'classe.ecole']);
        $teacherName = trim(($devoir->enseignant->prenom ?? '') . ' ' . ($devoir->enseignant->nom ?? ''));
        $schoolName = $devoir->classe->ecole->nom ?? '';

        /* Résolution des cibles de notification */
        $targetsQuery = DB::table('eleve_parents')
            ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id');

        if (!empty($selectedEleves)) {
            $targetsQuery->whereIn('eleves.id', $selectedEleves);
        } else {
            $targetsQuery->where('eleves.classe_id', $devoir->classe_id);
        }

        $targets = $targetsQuery
            ->select(
                'eleve_parents.parent_id',
                'eleve_parents.eleve_id',
                'eleves.prenom as eleve_prenom',
                'eleves.nom as eleve_nom'
            )
            ->get();

        /* Agrégation par famille */
        $parentsGrouped = collect($targets)->groupBy('parent_id');

        $typeLabels = [
            'maison' => 'Devoir de maison',
            'classe' => 'Devoir de classe',
            'exercice' => 'Exercice',
            'recherche' => 'Recherche',
            'revision' => 'Révision',
            'autre' => 'Autre'
        ];
        $typeLabel = $typeLabels[$devoir->type] ?? 'Devoir';
        $title = "{$typeLabel} - {$devoir->matiere}";
        
        $dateText = '';
        if ($devoir->date_remise) {
            $dateText = "\nÀ rendre pour le " . date('d/m/Y', strtotime($devoir->date_remise));
        } elseif ($devoir->date_realisation) {
            $dateText = "\nPrévu pour le " . date('d/m/Y', strtotime($devoir->date_realisation));
        }
        $body = "{$devoir->titre}{$dateText}";

        foreach ($parentsGrouped as $parentId => $children) {
            $parent = ParentUser::find($parentId);
            if (!$parent) continue;

            /* Persistance de la notification unitaire rattachée spécifiquement à chaque profil élève */
            foreach ($children as $childTarget) {
                $data = [
                    'devoir_id' => (string) $devoir->id,
                    'type' => 'new_homework',
                    'homework_type' => $devoir->type,
                    'classe_id' => (string) $devoir->classe_id,
                    'matiere' => $devoir->matiere,
                    'titre' => $devoir->titre,
                    'date_remise' => $devoir->date_remise ?? $devoir->date_realisation,
                    'eleve_id' => (string) $childTarget->eleve_id,
                    'eleve_nom' => trim(($childTarget->eleve_prenom ?? '') . ' ' . ($childTarget->eleve_nom ?? '')),
                    'sender_name' => $teacherName,
                    'school_name' => $schoolName,
                ];

                \App\Models\Notification::create([
                    'user_type' => 'parent',
                    'user_id' => $parentId,
                    'type' => 'new_homework',
                    'title' => $title,
                    'message' => $body,
                    'data' => $data,
                ]);
            }

            /* Déclenchement d'une notification Push unique et générique vers le terminal du parent */
            if (!empty($parent->fcm_token) && count($children) > 0) {
                $firstChild = $children->first();
                $pushBody = count($children) > 1 
                    ? "Nouveau devoir disponible pour vos enfants."
                    : "Nouveau devoir disponible pour " . trim($firstChild->eleve_prenom ?? '');

                $this->notificationService->sendPushOnly(
                    $parent->fcm_token,
                    $title,
                    $pushBody,
                    [
                        'type' => 'new_homework',
                        'devoir_id' => (string) $devoir->id,
                        'eleve_id' => (string) $firstChild->eleve_id,
                        'child_name' => trim(($firstChild->eleve_prenom ?? '') . ' ' . ($firstChild->eleve_nom ?? '')),
                        'matiere' => $devoir->matiere,
                        'sender_name' => $teacherName,
                        'school_name' => $schoolName,
                    ]
                );
            }
        }
    }
}
