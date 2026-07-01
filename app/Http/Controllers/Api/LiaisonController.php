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
            // METTRE A JOUR is_verified !
            DB::table('eleve_parents')
                ->where('eleve_id', $eleve->id)
                ->where('parent_id', $parent->id)
                ->update(['is_verified' => 1]);

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
            'parent_id' => 'required|integer',
            'relation' => 'nullable|string'
        ]);

        $parentId = $request->parent_id;
        $eleveIds = $request->eleve_ids;
        $relation = $request->input('relation', 'Tuteur');
        $linkedCount = 0;
        $errors = [];

        foreach ($eleveIds as $eleveId) {
            $exists = DB::table('eleve_parents')
                ->where('eleve_id', $eleveId)
                ->where('parent_id', $parentId)
                ->exists();

            if (!$exists) {
                // Check max 3 limit
                $count = DB::table('eleve_parents')->where('eleve_id', $eleveId)->count();
                if ($count >= 3) {
                    $errors[] = "L'élève #$eleveId a déjà 3 parents/tuteurs.";
                    continue;
                }

                DB::table('eleve_parents')->insert([
                    'eleve_id' => $eleveId,
                    'parent_id' => $parentId,
                    'relation' => $relation,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $linkedCount++;
            }
        }

        if (count($errors) > 0 && $linkedCount == 0) {
            return response()->json([
                'success' => false,
                'error' => implode(' ', $errors)
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $linkedCount > 0 
                ? "$linkedCount enfant(s) lié(s) avec succès." . (count($errors) > 0 ? " (" . implode(' ', $errors) . ")" : "")
                : "Les enfants sélectionnés sont déjà liés à ce parent."
        ]);
    }
}
