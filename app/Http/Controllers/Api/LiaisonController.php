<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Eleve;
use App\Models\ParentUser;

class LiaisonController extends Controller
{
    /**
     * Lier un enfant via le Code Secret
     */
    public function linkWithSecretCode(Request $request)
    {
        $request->validate([
            'code_secret' => 'required|string',
            'parent_id' => 'required|exists:parent_users,id'
        ]);

        $eleve = Eleve::where('code_secret', $request->code_secret)->first();

        if (!$eleve) {
            return response()->json(['success' => false, 'message' => 'Code secret invalide.'], 404);
        }

        $parent = ParentUser::find($request->parent_id);

        // Vérifier si la liaison existe déjà
        if ($parent->enfants()->where('eleve_id', $eleve->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Cet enfant est déjà lié à votre compte.'], 400);
        }

        // Lier
        $parent->enfants()->attach($eleve->id, ['relation' => 'Parent']);

        return response()->json([
            'success' => true, 
            'message' => 'Enfant lié avec succès.',
            'eleve' => $eleve
        ]);
    }

    /**
     * Lier un enfant via le QR Code
     */
    public function linkWithQrCode(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string',
            'parent_id' => 'required|exists:parent_users,id'
        ]);

        $eleve = Eleve::where('qr_code', $request->qr_code)->first();

        if (!$eleve) {
            return response()->json(['success' => false, 'message' => 'QR Code invalide ou non reconnu.'], 404);
        }

        $parent = ParentUser::find($request->parent_id);

        if ($parent->enfants()->where('eleve_id', $eleve->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Cet enfant est déjà lié à votre compte.'], 400);
        }

        $parent->enfants()->attach($eleve->id, ['relation' => 'Parent']);

        return response()->json([
            'success' => true, 
            'message' => 'Enfant lié avec succès via QR Code.',
            'eleve' => $eleve
        ]);
    }
}
