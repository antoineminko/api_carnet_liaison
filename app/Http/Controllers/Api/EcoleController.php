<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ecole;
use Illuminate\Http\Request;

class EcoleController extends Controller
{
    public function index()
    {
        return response()->json(Ecole::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'code_ecole' => 'required|string|max:10|unique:ecoles',
            'annee_scolaire' => 'nullable|string|max:20',
            'logo' => 'nullable|string',
        ]);

        $ecole = Ecole::create($validated);

        return response()->json([
            'success' => true,
            'ecole' => $ecole,
        ]);
    }
}
