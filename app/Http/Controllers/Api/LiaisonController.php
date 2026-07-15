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
            'code_secret' => 'required|string'
            // 'parent_id' n'est plus requis ici, la sécurité Sanctum nous le donne de manière infaillible.
        ]);

        $eleve = Eleve::where('code_secret', $request->code_secret)->first();

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Code secret invalide.'
            ], 404);
        }

        // Clean Architecture : On récupère le parent depuis son token de connexion sécurisé
        $parent = clone $request->user();
        
        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent introuvable ou session expirée.'
            ], 401);
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
            'message' => "Vous n'êtes pas autorisé car vous n'êtes pas identifié à ce compte. Contactez l'administration. (Debug: eleve={$eleve->id}, auth_parent={$parent->id})"
        ], 403);
    }

    public function linkWithQrCode(Request $request)
    {
        // Plus besoin de valider parent_id
        
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

        // Isolation multi-tenant : récupérer l'école depuis le middleware
        $ecole = $request->attributes->get('school');
        $ecoleId = $ecole?->id;

        $parentId = $request->parent_id;
        $eleveIds = $request->eleve_ids;
        $relation = $request->input('relation', 'Tuteur');
        $linkedCount = 0;
        $errors = [];

        // Vérifier que le parent appartient à cette école
        $parent = \App\Models\ParentUser::where('id', $parentId)
            ->where('ecole_id', $ecoleId)
            ->first();

        if (!$parent) {
            return response()->json([
                'success' => false,
                'error' => 'Parent introuvable ou accès refusé.'
            ], 404);
        }

        foreach ($eleveIds as $eleveId) {
            // Vérifier que l'élève appartient à cette école
            $eleve = Eleve::with('classe')
                ->where('id', $eleveId)
                ->whereHas('classe', fn($q) => $q->where('ecole_id', $ecoleId))
                ->first();

            if (!$eleve) {
                $errors[] = "L'élève #$eleveId est introuvable ou n'appartient pas à cette école.";
                continue;
            }

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
    public function adminUnlinkChild(Request $request)
    {
        $request->validate([
            'eleve_id' => 'required|integer',
            'parent_id' => 'required|integer'
        ]);

        $ecole = $request->attributes->get('school');
        $ecoleId = $ecole?->id;

        $parent = \App\Models\ParentUser::where('id', $request->parent_id)
            ->where('ecole_id', $ecoleId)
            ->first();

        if (!$parent) {
            return response()->json(['error' => 'Parent introuvable ou accès refusé.'], 403);
        }

        $eleve = Eleve::where('id', $request->eleve_id)->first();
        if (!$eleve || $eleve->classe->ecole_id !== $ecoleId) {
             return response()->json(['error' => 'Élève introuvable.'], 403);
        }

        DB::table('eleve_parents')
            ->where('parent_id', $request->parent_id)
            ->where('eleve_id', $request->eleve_id)
            ->delete();

        return response()->json(['success' => true]);
    }

}
