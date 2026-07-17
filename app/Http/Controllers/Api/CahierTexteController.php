<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CahierTexte;
use App\Models\ParentUser;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\DB;

class CahierTexteController extends Controller
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
            'enseignant_id' => 'required|integer',
            'titre' => 'required|string|max:255',
            'matiere' => 'required|string',
            'date_cours' => 'required|date',
            'contenu_realise' => 'required|string',
            'resume_cours' => 'nullable|string',
            'exercices_donnes' => 'nullable|string', // JSON string from flutter
        ]);

        $ecoleId = $request->attributes->get('school')?->id;
        if ($ecoleId) {
            $classe = \App\Models\Classe::find($request->classe_id);
            if (!$classe || $classe->ecole_id != $ecoleId) {
                return response()->json(['error' => 'Classe non trouvée ou accès refusé'], 403);
            }
        }

        $cahierTexte = CahierTexte::create([
            'classe_id' => $request->classe_id,
            'enseignant_id' => $request->enseignant_id,
            'titre' => $request->titre,
            'matiere' => $request->matiere,
            'date_cours' => $request->date_cours,
            'contenu_realise' => $request->contenu_realise,
            'resume_cours' => $request->resume_cours,
            'exercices_donnes' => $request->exercices_donnes,
        ]);

        // Déterminer les couples parent/enfant à notifier
        $targets = DB::table('eleve_parents')
            ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
            ->where('eleves.classe_id', $request->classe_id)
            ->select(
                'eleve_parents.parent_id',
                'eleve_parents.eleve_id',
                'eleves.prenom as eleve_prenom',
                'eleves.nom as eleve_nom'
            )
            ->get();

        $sentCount = 0;
        
        // Grouper les cibles par parent_id
        $parentsGrouped = collect($targets)->groupBy('parent_id');

        $title = "Cahier de textes - {$request->matiere}";
        $body = "Séance du " . date('d/m/Y', strtotime($request->date_cours));

        foreach ($parentsGrouped as $parentId => $children) {
            $parent = ParentUser::find($parentId);
            if (!$parent) continue;

            // Enregistrer une notification par enfant dans la base de données
            foreach ($children as $childTarget) {
                $data = [
                    'cahier_texte_id' => (string) $cahierTexte->id,
                    'type' => 'new_textbook',
                    'classe_id' => (string) $request->classe_id,
                    'matiere' => $request->matiere,
                    'titre' => $request->titre,
                    'resume_cours' => $request->resume_cours,
                    'date_cours' => $request->date_cours,
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

            // Fallback: Envoyer un seul push FCM pour le parent, compatible avec l'application mobile
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
                        'matiere' => $request->matiere,
                    ]
                );
                $sentCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Cahier de textes enregistré avec succès.',
            'cahier_texte' => $cahierTexte,
            'notifications_envoyees' => $sentCount
        ], 201);
    }

    public function getByEleve($eleveId)
    {
        $eleve = \App\Models\Eleve::find($eleveId);
        if (!$eleve) {
            return response()->json(['error' => 'Élève non trouvé'], 404);
        }

        $cahiers = CahierTexte::where('classe_id', $eleve->classe_id)
            ->orderBy('date_cours', 'desc')
            ->get();

        return response()->json(['success' => true, 'cahiers' => $cahiers]);
    }
}
