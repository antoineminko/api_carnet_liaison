<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CahierTexte;
use App\Models\ParentUser;
use App\Services\PushNotificationService;
use App\Http\Requests\Api\StoreCahierTexteRequest;
use App\Services\CahierTexteService;

class CahierTexteController extends Controller
{
    protected $cahierTexteService;

    public function __construct(CahierTexteService $cahierTexteService)
    {
        $this->cahierTexteService = $cahierTexteService;
    }

    public function store(StoreCahierTexteRequest $request)
    {
        $ecoleId = $request->attributes->get('school')?->id;
        if ($ecoleId) {
            $classe = \App\Models\Classe::find($request->classe_id);
            if (!$classe || $classe->ecole_id != $ecoleId) {
                return response()->json(['error' => 'Classe non trouvée ou accès refusé'], 403);
            }
        }

        $cahierTexte = $this->cahierTexteService->createCahierTexte($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cahier de textes enregistré avec succès.',
            'cahier_texte' => $cahierTexte,
        ], 201);
    }

    public function getByEleve($eleveId)
    {
        $eleve = \App\Models\Eleve::find($eleveId);
        if (!$eleve) {
            return response()->json(['error' => 'Élève non trouvé'], 404);
        }

        $cahiers = CahierTexte::with('devoirs')
            ->where('classe_id', $eleve->classe_id)
            ->orderBy('date_cours', 'desc')
            ->get();

        return response()->json(['success' => true, 'cahiers' => $cahiers]);
    }

    public function getByClasse(Request $request, $classeId)
    {
        $query = CahierTexte::with('devoirs')->where('classe_id', $classeId);
        
        if ($request->has('matiere')) {
            $query->where('matiere', $request->matiere);
        }

        $cahiers = $query->orderBy('date_cours', 'desc')->get();

        return response()->json(['success' => true, 'cahiers' => $cahiers]);
    }
}
