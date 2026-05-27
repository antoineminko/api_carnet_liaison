<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatiereController extends Controller
{
    public function index()
    {
        try {
            $matieres = DB::table('matieres')->get();
            return response()->json($matieres);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $id = DB::table('matieres')->insertGetId([
                'nom' => $request->input('nom', 'Nouvelle Matiere'),
                'coefficient' => $request->input('coefficient', 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $matiere = DB::table('matieres')->where('id', $id)->first();
            return response()->json($matiere, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::table('matieres')->where('id', $id)->update([
                'nom' => $request->input('nom'),
                'coefficient' => $request->input('coefficient', 1),
                'enseignant_id' => $request->input('enseignant_id'),
                'classe_id' => $request->input('classe_id'),
                'updated_at' => now(),
            ]);

            $matiere = DB::table('matieres')
                ->leftJoin('enseignants', 'matieres.enseignant_id', '=', 'enseignants.id')
                ->leftJoin('classes', 'matieres.classe_id', '=', 'classes.id')
                ->select(
                    'matieres.*',
                    DB::raw("CONCAT(enseignants.prenom, ' ', enseignants.nom) as enseignant_nom"),
                    'classes.nom as classe_nom'
                )
                ->where('matieres.id', $id)
                ->first();
            return response()->json($matiere);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::table('matieres')->where('id', $id)->delete();
            return response()->json(['message' => 'Deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
