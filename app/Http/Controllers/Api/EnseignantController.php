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
            return response()->json([]);
        }
    }

    public function store(Request $request)
    {
        try {
            $id = DB::table('enseignants')->insertGetId([
                'nom' => $request->input('nom', 'N/A'),
                'prenom' => $request->input('prenom', 'N/A'),
                'email' => $request->input('email', null),
                'telephone' => $request->input('telephone', null),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $enseignant = DB::table('enseignants')->where('id', $id)->first();
            return response()->json($enseignant, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
