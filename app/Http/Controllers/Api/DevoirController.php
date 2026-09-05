<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Devoir;
use App\Models\ParentUser;
use App\Services\PushNotificationService;
use App\Http\Requests\Api\StoreDevoirRequest;
use App\Services\DevoirService;

class DevoirController extends Controller
{
    protected $devoirService;

    public function __construct(DevoirService $devoirService)
    {
        $this->devoirService = $devoirService;
    }

    public function store(StoreDevoirRequest $request)
    {
        $ecoleId = $request->attributes->get('school')?->id;
        if ($ecoleId) {
            $classe = \App\Models\Classe::find($request->classe_id);
            if (!$classe || $classe->ecole_id != $ecoleId) {
                return response()->json(['error' => 'Classe non trouvée ou accès refusé'], 403);
            }
        }

        $devoir = $this->devoirService->createDevoir($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Devoir créé avec succès.',
            'devoir' => $devoir,
            'eleves_concernes' => count($selectedEleves),
            'notifications_envoyees' => $sentCount
        ], 201);
    }

    /* Extraction du périmètre académique d'un enseignant (Classes assignées et classes principales) */
    public function getTeacherClasses($teacherId)
    {
        /* Récupération des classes sous la responsabilité principale de l'enseignant */
        $principalClasses = DB::table('classes')
            ->where('prof_principal_id', $teacherId)
            ->select('id', 'nom', 'ecole_id')
            ->get();

        /* Récupération des classes où l'enseignant intervient factuellement (historique des devoirs) */
        $devoirClasses = DB::table('devoirs')
            ->where('enseignant_id', $teacherId)
            ->distinct()
            ->pluck('classe_id');

        $allClassIds = $principalClasses->pluck('id')
            ->merge($devoirClasses)
            ->unique()
            ->values();

        $ecoleId = request()->attributes->get('school')?->id;

        /* Consolidation et dédoublonnage des classes avec hydratation des informations de l'établissement */
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

    /* Extraction du trombinoscope et de la liste des élèves d'une classe spécifique */
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

        /* Formatage absolu de l'URI de la photographie pour les applications clientes */
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
