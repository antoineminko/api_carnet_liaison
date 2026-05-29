<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
                'password' => Hash::make('password123'), // Fixé par défaut
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assignation comme prof principal si demandÃ©
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

    public function update(Request $request, $id)
    {
        try {
            $data = [
                'nom' => $request->input('nom'),
                'prenom' => $request->input('prenom'),
                'matiere' => $request->input('matiere'),
                'email' => $request->input('email'),
                'telephone' => $request->input('telephone'),
                'updated_at' => now(),
            ];

            DB::table('enseignants')->where('id', $id)->update($data);

            if ($request->input('est_prof_principal') && $request->input('classe_principale_id')) {
                // Retirer cet enseignant des autres classes s'il était déjà prof principal ailleurs
                DB::table('classes')->where('prof_principal_id', $id)->update(['prof_principal_id' => null]);
                
                // Assigner à la nouvelle classe
                DB::table('classes')
                    ->where('id', $request->input('classe_principale_id'))
                    ->update(['prof_principal_id' => $id]);
            } elseif ($request->has('est_prof_principal') && !$request->input('est_prof_principal')) {
                // S'il ne doit plus être prof principal
                DB::table('classes')->where('prof_principal_id', $id)->update(['prof_principal_id' => null]);
            }

            $enseignant = DB::table('enseignants')->where('id', $id)->first();
            return response()->json($enseignant);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::table('enseignants')->where('id', $id)->delete();
            return response()->json(['message' => 'Deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
