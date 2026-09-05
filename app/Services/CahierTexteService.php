<?php

namespace App\Services;

use App\Models\CahierTexte;
use App\Models\ParentUser;
use Illuminate\Support\Facades\DB;

class CahierTexteService
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Crée une entrée dans le cahier de textes et notifie les parents.
     *
     * @param array $data Données validées
     * @return CahierTexte
     */
    public function createCahierTexte(array $data): CahierTexte
    {
        $cahierTexte = CahierTexte::create([
            'classe_id' => $data['classe_id'],
            'enseignant_id' => $data['enseignant_id'],
            'titre' => $data['titre'],
            'matiere' => $data['matiere'],
            'date_cours' => $data['date_cours'],
            'contenu_realise' => $data['contenu_realise'],
            'resume_cours' => $data['resume_cours'] ?? null,
            'exercices_donnes' => $data['exercices_donnes'] ?? null,
        ]);

        $this->notifyParents($cahierTexte);

        return $cahierTexte;
    }

    /**
     * Notifie les parents de la classe.
     */
    protected function notifyParents(CahierTexte $cahierTexte)
    {
        /* Résolution des cibles de notification */
        $targets = DB::table('eleve_parents')
            ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
            ->where('eleves.classe_id', $cahierTexte->classe_id)
            ->select(
                'eleve_parents.parent_id',
                'eleve_parents.eleve_id',
                'eleves.prenom as eleve_prenom',
                'eleves.nom as eleve_nom'
            )
            ->get();

        /* Agrégation par famille */
        $parentsGrouped = collect($targets)->groupBy('parent_id');

        $title = "Cahier de textes - {$cahierTexte->matiere}";
        $body = "Séance du " . date('d/m/Y', strtotime($cahierTexte->date_cours));

        foreach ($parentsGrouped as $parentId => $children) {
            $parent = ParentUser::find($parentId);
            if (!$parent) continue;

            /* Persistance unitaire par élève */
            foreach ($children as $childTarget) {
                $data = [
                    'cahier_texte_id' => (string) $cahierTexte->id,
                    'type' => 'new_textbook',
                    'classe_id' => (string) $cahierTexte->classe_id,
                    'matiere' => $cahierTexte->matiere,
                    'titre' => $cahierTexte->titre,
                    'resume_cours' => $cahierTexte->resume_cours,
                    'date_cours' => $cahierTexte->date_cours,
                    'eleve_id' => (string) $childTarget->eleve_id,
                    'eleve_nom' => trim(($childTarget->eleve_prenom ?? '') . ' ' . ($childTarget->eleve_nom ?? '')),
                ];

                \App\Models\Notification::create([
                    'user_type' => 'parent',
                    'user_id' => $parentId,
                    'type' => 'new_textbook',
                    'title' => $title,
                    'message' => $body,
                    'data' => $data,
                ]);
            }

            /* Push générique */
            if (!empty($parent->fcm_token) && count($children) > 0) {
                $firstChild = $children->first();
                $pushBody = count($children) > 1 
                    ? "Le cahier de textes a été mis à jour pour vos enfants."
                    : "Le cahier de textes a été mis à jour pour " . trim($firstChild->eleve_prenom ?? '');

                $this->notificationService->sendPushOnly(
                    $parent->fcm_token,
                    $title,
                    $pushBody,
                    [
                        'type' => 'new_textbook',
                        'cahier_texte_id' => (string) $cahierTexte->id,
                        'eleve_id' => (string) $firstChild->eleve_id,
                        'child_name' => trim(($firstChild->eleve_prenom ?? '') . ' ' . ($firstChild->eleve_nom ?? '')),
                        'matiere' => $cahierTexte->matiere,
                    ]
                );
            }
        }
    }
}
