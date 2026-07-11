<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EnseignantController extends Controller
{
    public function index(Request $request)
    {
        try {
            $ecole = $request->attributes->get('school');
            $enseignants = DB::table('enseignants')
                ->where('ecole_id', $ecole->id)
                ->get();
            return response()->json($enseignants);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $ecole = $request->attributes->get('school');
            
            $id = DB::table('enseignants')->insertGetId([
                'ecole_id' => $ecole->id,
                'nom' => $request->input('nom', 'N/A'),
                'prenom' => $request->input('prenom', 'N/A'),
                'matiere' => $request->input('matiere', null),
                'email' => $request->input('email', null),
                'telephone' => $request->input('telephone', null),
                'password' => Hash::make('password123'), // Fixé par défaut
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assignation comme prof principal si demandé
            if ($request->input('est_prof_principal') && $request->input('classe_principale_id')) {
                DB::table('classes')
                    ->where('id', $request->input('classe_principale_id'))
                    ->where('ecole_id', $ecole->id)
                    ->update(['prof_principal_id' => $id]);
            }

            $enseignant = DB::table('enseignants')->where('id', $id)->where('ecole_id', $ecole->id)->first();
            return response()->json($enseignant, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $ecole = $request->attributes->get('school');
            
            $data = [
                'nom' => $request->input('nom'),
                'prenom' => $request->input('prenom'),
                'matiere' => $request->input('matiere'),
                'email' => $request->input('email'),
                'telephone' => $request->input('telephone'),
                'updated_at' => now(),
            ];

            DB::table('enseignants')->where('id', $id)->where('ecole_id', $ecole->id)->update($data);

            if ($request->input('est_prof_principal') && $request->input('classe_principale_id')) {
                // Retirer cet enseignant des autres classes s'il était déjà prof principal ailleurs
                DB::table('classes')->where('prof_principal_id', $id)->where('ecole_id', $ecole->id)->update(['prof_principal_id' => null]);
                
                // Assigner à la nouvelle classe
                DB::table('classes')
                    ->where('id', $request->input('classe_principale_id'))
                    ->where('ecole_id', $ecole->id)
                    ->update(['prof_principal_id' => $id]);
            } elseif ($request->has('est_prof_principal') && !$request->input('est_prof_principal')) {
                // S'il ne doit plus être prof principal
                DB::table('classes')->where('prof_principal_id', $id)->where('ecole_id', $ecole->id)->update(['prof_principal_id' => null]);
            }

            $enseignant = DB::table('enseignants')->where('id', $id)->where('ecole_id', $ecole->id)->first();
            return response()->json($enseignant);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $ecole = $request->attributes->get('school');
            DB::table('enseignants')->where('id', $id)->where('ecole_id', $ecole->id)->delete();
            return response()->json(['message' => 'Deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
