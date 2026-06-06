<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Classe;

class ClasseController extends Controller
{
    public function index()
    {
        try {
            $classes = DB::table('classes')
                ->leftJoin('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
                ->leftJoin('enseignants', 'classes.prof_principal_id', '=', 'enseignants.id')
                ->select(
                    'classes.*',
                    'ecoles.nom as ecole_nom',
                    'ecoles.code as ecole_code',
                    DB::raw("CONCAT(enseignants.prenom, ' ', enseignants.nom) as prof_principal_nom")
                )
                ->get();
                
            foreach ($classes as $classe) {
                $classe->enseignants = DB::table('classe_enseignant')
                    ->join('enseignants', 'classe_enseignant.enseignant_id', '=', 'enseignants.id')
                    ->where('classe_enseignant.classe_id', $classe->id)
                    ->select('enseignants.id', 'enseignants.prenom', 'enseignants.nom')
                    ->get();
            }

            return response()->json($classes);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $code = $request->input('code');
            if (!$code) {
                $nom = $request->input('nom', '');
                $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nom));
                $code = substr($code, 0, 5);
                $suffix = 1;
                $baseCode = $code;
                while (DB::table('classes')->where('code', $code)->exists()) {
                    $code = $baseCode . $suffix;
                    $suffix++;
                }
            }

            $ecoleId = $request->input('ecole_id');
            if (!$ecoleId) {
                $ecole = DB::table('ecoles')->first();
                $ecoleId = $ecole ? $ecole->id : null;
            }

            $id = DB::table('classes')->insertGetId([
                'nom'      => $request->input('nom'),
                'code'     => $code,
                'ecole_id' => $ecoleId,
                'prof_principal_id' => $request->input('prof_principal_id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $classeModel = Classe::find($id);
            if ($request->has('enseignant_ids')) {
                $classeModel->enseignants()->sync($request->input('enseignant_ids', []));
            }

            $classe = DB::table('classes')
                ->leftJoin('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
                ->leftJoin('enseignants', 'classes.prof_principal_id', '=', 'enseignants.id')
                ->select(
                    'classes.*', 
                    'ecoles.nom as ecole_nom',
                    DB::raw("CONCAT(enseignants.prenom, ' ', enseignants.nom) as prof_principal_nom")
                )
                ->where('classes.id', $id)
                ->first();
                
            $classe->enseignants = DB::table('classe_enseignant')
                ->join('enseignants', 'classe_enseignant.enseignant_id', '=', 'enseignants.id')
                ->where('classe_enseignant.classe_id', $classe->id)
                ->select('enseignants.id', 'enseignants.prenom', 'enseignants.nom')
                ->get();

            return response()->json($classe, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::table('classes')->where('id', $id)->update([
                'nom' => $request->input('nom'),
                'prof_principal_id' => $request->input('prof_principal_id'),
                'updated_at' => now(),
            ]);
            
            $classeModel = Classe::find($id);
            if ($request->has('enseignant_ids')) {
                $classeModel->enseignants()->sync($request->input('enseignant_ids', []));
            }

            $classe = DB::table('classes')
                ->leftJoin('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
                ->leftJoin('enseignants', 'classes.prof_principal_id', '=', 'enseignants.id')
                ->select(
                    'classes.*', 
                    'ecoles.nom as ecole_nom',
                    DB::raw("CONCAT(enseignants.prenom, ' ', enseignants.nom) as prof_principal_nom")
                )
                ->where('classes.id', $id)
                ->first();
                
            $classe->enseignants = DB::table('classe_enseignant')
                ->join('enseignants', 'classe_enseignant.enseignant_id', '=', 'enseignants.id')
                ->where('classe_enseignant.classe_id', $classe->id)
                ->select('enseignants.id', 'enseignants.prenom', 'enseignants.nom')
                ->get();
                
            return response()->json($classe);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::table('classes')->where('id', $id)->delete();
            return response()->json(['message' => 'Deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
