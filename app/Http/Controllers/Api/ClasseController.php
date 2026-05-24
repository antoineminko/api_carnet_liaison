<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use Illuminate\Http\Request;

class ClasseController extends Controller
{
    public function index()
    {
        return response()->json(Classe::with('ecole')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ecole_id' => 'required|exists:ecoles,id',
            'nom' => 'required|string|max:255',
            'code_classe' => 'required|string|max:10',
            'niveau' => 'nullable|string|max:100',
            'annee_scolaire' => 'nullable|string|max:20',
        ]);

        $classe = Classe::create($validated);

        return response()->json([
            'success' => true,
            'classe' => $classe,
        ]);
    }
}
