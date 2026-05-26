<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ParentUser;

class NotificationController extends Controller
{
    /**
     * Enregistrer le token Firebase d'un parent
     */
    public function registerToken(Request $request)
    {
        $request->validate([
            'parent_id' => 'required|integer', 
            'token'     => 'required|string',
            'platform'  => 'nullable|string',
        ]);

        $parent = ParentUser::find($request->parent_id);
        
        if (!$parent) {
            return response()->json(['success' => false, 'message' => 'Parent introuvable'], 404);
        }

        $parent->fcm_token = $request->token;
        $parent->save();

        return response()->json([
            'success' => true,
            'message' => 'Token FCM enregistré avec succès.',
        ]);
    }
}

