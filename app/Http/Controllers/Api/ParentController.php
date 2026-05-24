<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ParentUser;
use App\Models\Eleve;
use Illuminate\Support\Facades\DB;

class ParentController extends Controller
{
    public function getChildren($parentId)
    {
        $parent = ParentUser::find($parentId);
        
        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent introuvable'
            ], 404);
        }

        // Récupérer les élèves liés au parent avec leurs classes
        $eleves = DB::table('eleves')
            ->join('eleve_parents', 'eleves.id', '=', 'eleve_parents.eleve_id')
            ->join('classes', 'eleves.classe_id', '=', 'classes.id')
            ->where('eleve_parents.parent_id', $parentId)
            ->select(
                'eleves.id',
                'eleves.nom',
                'eleves.prenom',
                'eleves.matricule',
                'classes.nom as classe_nom'
            )
            ->get();

        // Format data for the mobile app
        $childrenData = $eleves->map(function ($eleve) {
            return [
                'id' => $eleve->id,
                'name' => $eleve->prenom . ' ' . $eleve->nom,
                'grade' => $eleve->classe_nom,
                'school' => 'Skooly School', // Mocker pour l'instant si on ne gère pas la table école
                'image' => 'assets/images/profil/eleve1.jpg',
                'notif' => 0,
                'color' => 0xFF2596be // int color format used by Flutter (blue)
            ];
        });

        return response()->json([
            'success' => true,
            'children' => $childrenData
        ]);
    }
}
