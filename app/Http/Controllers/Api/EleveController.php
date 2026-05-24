<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EleveController extends Controller
{
    public function index()
    {
        try {
            $eleves = DB::table('eleves')
                ->leftJoin('classes', 'eleves.classe_id', '=', 'classes.id')
                ->select(
                    'eleves.*',
                    'classes.nom as classe_nom',
                    'classes.code as classe_code'
                )
                ->get();
            return response()->json($eleves);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    public function store(Request $request)
    {
        try {
            // Résoudre classe_id depuis classe_code ou classe_id direct
            $classeId = $request->input('classe_id');
            if (!$classeId && $request->input('classe_code')) {
                $classe = DB::table('classes')->where('code', $request->input('classe_code'))->first();
                $classeId = $classe ? $classe->id : null;
            }

            // Générer matricule automatique
            $matricule = $request->input('matricule', 'MAT-' . strtoupper(uniqid()));

            $id = DB::table('eleves')->insertGetId([
                'nom'            => $request->input('nom'),
                'prenom'         => $request->input('prenom'),
                'matricule'      => $matricule,
                'classe_id'      => $classeId ?? 1,
                'code_secret'    => $request->input('code_secret'),
                'date_naissance' => $request->input('date_naissance'),
                'lieu_naissance' => $request->input('lieu_naissance'),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $eleve = DB::table('eleves')
                ->leftJoin('classes', 'eleves.classe_id', '=', 'classes.id')
                ->select('eleves.*', 'classes.nom as classe_nom')
                ->where('eleves.id', $id)
                ->first();

            return response()->json($eleve, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
