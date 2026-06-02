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

    public function adminLinkChild(Request $request)
    {
        $request->validate([
            'eleve_ids' => 'required|array',
            'eleve_ids.*' => 'integer',
            'parent_id' => 'required|integer'
        ]);

        $parentId = $request->parent_id;
        $eleveIds = $request->eleve_ids;
        $linkedCount = 0;

        foreach ($eleveIds as $eleveId) {
            $exists = DB::table('eleve_parents')
                ->where('eleve_id', $eleveId)
                ->where('parent_id', $parentId)
                ->exists();

            if (!$exists) {
                DB::table('eleve_parents')->insert([
                    'eleve_id' => $eleveId,
                    'parent_id' => $parentId,
                    'relation' => 'Parent',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $linkedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => $linkedCount > 0 
                ? "$linkedCount enfant(s) lié(s) avec succès."
                : "Les enfants sélectionnés sont déjà liés à ce parent."
        ]);
    }
}
