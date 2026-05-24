<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ParentUser;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function loginParent(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string', // Peut être email ou telephone
            'password' => 'required|string',
        ]);

        $parent = ParentUser::where('email', $request->identifier)
                            ->orWhere('telephone', $request->identifier)
                            ->first();

        if (!$parent || !Hash::check($request->password, $parent->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects.'
            ], 401);
        }

        // Pour la démo, on retourne juste l'ID du parent. 
        // Dans une vraie app, on utiliserait Sanctum pour créer un token.
        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'parent' => $parent
        ]);
    }
}
