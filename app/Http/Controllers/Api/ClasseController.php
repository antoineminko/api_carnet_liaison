<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            return response()->json($classes);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    public function store(Request $request)
    {
        try {
            $id = DB::table('classes')->insertGetId([
                'nom'      => $request->input('nom'),
                'code'     => $request->input('code'),
                'ecole_id' => $request->input('ecole_id'),
                'prof_principal_id' => $request->input('prof_principal_id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

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

            return response()->json($classe, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
