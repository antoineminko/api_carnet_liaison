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
            'type' => 'required|in:maison,classe,exercice,recherche,revision,autre',
            'titre' => 'required|string',
            'description' => 'required|string',
            'date_remise' => 'nullable|date',
            'date_realisation' => 'nullable|date',
            'cahier_texte_id' => 'nullable|integer|exists:cahier_textes,id',
            'eleves' => 'nullable|array', // IDs des élèves sélectionnés (optionnel)
            'eleves.*' => 'integer|exists:eleves,id',
        ]);

        $ecoleId = $request->attributes->get('school')?->id;
        if ($ecoleId) {
            $classe = \App\Models\Classe::find($request->classe_id);
            if (!$classe || $classe->ecole_id != $ecoleId) {
                return response()->json(['error' => 'Classe non trouvée ou accès refusé'], 403);
            }
        }

        $devoir = Devoir::create([
            'classe_id' => $request->classe_id,
            'enseignant_id' => $request->enseignant_id,
            'matiere' => $request->matiere,
            'type' => $request->type,
            'titre' => $request->titre,
            'description' => $request->description,
            'date_remise' => $request->date_remise,
            'date_realisation' => $request->date_realisation,
            'cahier_texte_id' => $request->cahier_texte_id,
        ]);

        // Si des élèves spécifiques sont sélectionnés, les lier au devoir
        $selectedEleves = $request->input('eleves', []);
        if (!empty($selectedEleves)) {
            foreach ($selectedEleves as $eleveId) {
                DB::table('devoir_eleve')->insert([
                    'devoir_id' => $devoir->id,
                    'eleve_id' => $eleveId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Déterminer les couples parent/enfant à notifier
        $targetsQuery = DB::table('eleve_parents')
            ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id');

        if (!empty($selectedEleves)) {
            $targetsQuery->whereIn('eleves.id', $selectedEleves);
        } else {
            $targetsQuery->where('eleves.classe_id', $request->classe_id);
        }

        $targets = $targetsQuery
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

        $typeLabels = [
            'maison' => 'Devoir de maison',
            'classe' => 'Devoir de classe',
            'exercice' => 'Exercice',
            'recherche' => 'Recherche',
            'revision' => 'Révision',
            'autre' => 'Autre'
        ];
        $typeLabel = $typeLabels[$request->type] ?? 'Devoir';
        $title = "{$typeLabel} - {$request->matiere}";
        
        $dateText = '';
        if ($request->date_remise) {
            $dateText = "\nÀ rendre pour le " . date('d/m/Y', strtotime($request->date_remise));
        } elseif ($request->date_realisation) {
            $dateText = "\nPrévu pour le " . date('d/m/Y', strtotime($request->date_realisation));
        }
        $body = "{$request->titre}{$dateText}";

        foreach ($parentsGrouped as $parentId => $children) {
            $parent = ParentUser::find($parentId);
            if (!$parent) continue;

            // Enregistrer une notification par enfant dans la base de données
            foreach ($children as $childTarget) {
                $data = [
                    'devoir_id' => (string) $devoir->id,
                    'type' => 'new_homework',
                    'homework_type' => $request->type,
                    'classe_id' => (string) $request->classe_id,
                    'matiere' => $request->matiere,
                    'titre' => $request->titre,
                    'date_remise' => $request->date_remise ?? $request->date_realisation,
                    'eleve_id' => (string) $childTarget->eleve_id,
                    'eleve_nom' => trim(($childTarget->eleve_prenom ?? '') . ' ' . ($childTarget->eleve_nom ?? '')),
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

            // Fallback: Envoyer un seul push FCM pour le parent compatible avec mobile
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
                        'matiere' => $request->matiere,
                    ]
                );
                $sentCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Devoir créé avec succès.',
            'devoir' => $devoir,
            'eleves_concernes' => count($selectedEleves),
            'notifications_envoyees' => $sentCount
        ], 201);
    }

    /**
     * Récupérer les classes d'un enseignant
     */
    public function getTeacherClasses($teacherId)
    {
        // Classes où l'enseignant est prof principal
        $principalClasses = DB::table('classes')
            ->where('prof_principal_id', $teacherId)
            ->select('id', 'nom', 'ecole_id')
            ->get();

        // Classes où l'enseignant a déjà assigné des devoirs
        $devoirClasses = DB::table('devoirs')
            ->where('enseignant_id', $teacherId)
            ->distinct()
            ->pluck('classe_id');

        $allClassIds = $principalClasses->pluck('id')
            ->merge($devoirClasses)
            ->unique()
            ->values();

        $ecoleId = request()->attributes->get('school')?->id;

        // Récupérer toutes les classes uniques avec info école
        $query = DB::table('classes')
            ->leftJoin('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
            ->whereIn('classes.id', $allClassIds);

        if ($ecoleId) {
            $query->where('classes.ecole_id', $ecoleId);
        }

        $classes = $query->select('classes.id', 'classes.nom', 'ecoles.nom as ecole_nom')->get();

        return response()->json([
            'classes' => $classes
        ]);
    }

    /**
     * Récupérer les élèves d'une classe
     */
    public function getClassStudents($classeId)
    {
        $ecoleId = request()->attributes->get('school')?->id;

        $query = DB::table('eleves')
            ->where('classe_id', $classeId);

        if ($ecoleId) {
            $query->where('ecole_id', $ecoleId);
        }

        $eleves = $query->select('id', 'prenom', 'nom', 'photo')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        // Ajouter l'URL complète de la photo
        foreach ($eleves as $eleve) {
            if (!empty($eleve->photo)) {
                $eleve->photo_url = url('storage/' . $eleve->photo);
            } else {
                $eleve->photo_url = null;
            }
        }

        return response()->json([
            'eleves' => $eleves
        ]);
    }
}
