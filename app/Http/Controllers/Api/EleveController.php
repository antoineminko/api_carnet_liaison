<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use Illuminate\Http\Request;

class EleveController extends Controller
{
    public function index()
    {
        return response()->json(Eleve::with('classe.ecole')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'classe_id' => 'required|exists:classes,id',
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'matricule' => 'required|string|max:50|unique:eleves',
        ]);

        $eleve = Eleve::create($validated);

        return response()->json([
            'success' => true,
            'eleve' => $eleve,
        ]);
    }
}
