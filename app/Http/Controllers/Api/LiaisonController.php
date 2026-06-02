<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Eleve;
use App\Models\ParentUser;
use Illuminate\Support\Facades\DB;

class LiaisonController extends Controller
{
    public function linkWithSecretCode(Request $request)
    {
        $request->validate([
            'code_secret' => 'required|string',
            'parent_id' => 'required|integer'
        ]);

        $eleve = Eleve::where('code_secret', $request->code_secret)->first();

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Code secret invalide.'
            ], 404);
        }

        $parent = ParentUser::find($request->parent_id);
        
        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent introuvable.'
            ], 404);
        }

        // Check if already linked
        $exists = DB::table('eleve_parents')
            ->where('eleve_id', $eleve->id)
            ->where('parent_id', $parent->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => true,
                'message' => 'Liaison vérifiée avec succès.',
                'eleve' => $eleve
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Vous n'êtes pas autorisé car vous n'êtes pas identifié à ce compte. Contactez l'administration."
        ], 403);
    }

    public function linkWithQrCode(Request $request)
    {
        $request->validate([
            'parent_id' => 'required|integer'
        ]);

        $request->merge([
            'code_secret' => $request->input('qr_data') ?? $request->input('qr_code')
        ]);

        return $this->linkWithSecretCode($request);
    }
}
