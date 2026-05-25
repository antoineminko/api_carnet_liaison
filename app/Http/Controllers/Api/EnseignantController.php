<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnseignantController extends Controller
{
    public function index()
    {
        try {
            $enseignants = DB::table('enseignants')->get();
            return response()->json($enseignants);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $id = DB::table('enseignants')->insertGetId([
                'nom' => $request->input('nom', 'N/A'),
                'prenom' => $request->input('prenom', 'N/A'),
                'matiere' => $request->input('matiere', null),
                'email' => $request->input('email', null),
                'telephone' => $request->input('telephone', null),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assignation comme prof principal si demandé
            if ($request->input('est_prof_principal') && $request->input('classe_principale_id')) {
                DB::table('classes')
                    ->where('id', $request->input('classe_principale_id'))
                    ->update(['prof_principal_id' => $id]);
            }

            $enseignant = DB::table('enseignants')->where('id', $id)->first();
            return response()->json($enseignant, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
