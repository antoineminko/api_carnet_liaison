<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcoleController extends Controller
{
    public function index()
    {
        try {
            $ecoles = DB::table('ecoles')->get();
            return response()->json($ecoles);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    public function store(Request $request)
    {
        try {
            $id = DB::table('ecoles')->insertGetId([
                'nom'            => $request->input('nom', 'École'),
                'code'           => $request->input('code', 'ECO'),
                'annee_scolaire' => $request->input('annee_scolaire', date('Y') . '-' . (date('Y') + 1)),
                'nb_classes'     => $request->input('nb_classes', 0),
                'nb_profs'       => $request->input('nb_profs', 0),
                'nb_eleves'      => $request->input('nb_eleves', 0),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $ecole = DB::table('ecoles')->where('id', $id)->first();
            return response()->json($ecole, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
