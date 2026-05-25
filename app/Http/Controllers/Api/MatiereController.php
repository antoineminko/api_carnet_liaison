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
}
