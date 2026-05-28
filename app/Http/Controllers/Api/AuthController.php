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
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = $request->input('identifier');

        $parent = ParentUser::where('email', $identifier)
            ->orWhere('telephone', $identifier)
            ->first();

        if (!$parent) {
            $parent = ParentUser::create([
                'nom' => 'Demo',
                'prenom' => 'Parent',
                'email' => str_contains($identifier, '@') ? $identifier : null,
                'password' => Hash::make($request->password),
                'telephone' => str_contains($identifier, '@') ? '0000000000' : $identifier
            ]);
        }

        return response()->json([
            'success' => true,
            'token' => 'mock-token-12345',
            'parent' => [
                'id' => $parent->id,
                'nom' => $parent->nom,
                'prenom' => $parent->prenom,
                'email' => $parent->email,
                'telephone' => $parent->telephone,
            ],
        ]);
    }

    public function loginTeacher(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = $request->input('identifier');

        $teacher = \Illuminate\Support\Facades\DB::table('enseignants')
            ->where('email', $identifier)
            ->orWhere('telephone', $identifier)
            ->first();

        if (!$teacher) {
            // Pour le démo, si l'enseignant n'existe pas, on le simule ou on retourne erreur.
            return response()->json(['success' => false, 'message' => 'Enseignant introuvable'], 404);
        }

        // On ne vérifie pas le hash strict pour l'instant si password123 est utilisé et c'est une démo.
        // Mais idéalement, Hash::check($request->password, $teacher->password)
        if (!Hash::check($request->password, $teacher->password)) {
            return response()->json(['success' => false, 'message' => 'Mot de passe incorrect'], 401);
        }

        return response()->json([
            'success' => true,
            'token' => 'mock-token-teacher-12345',
            'teacher' => [
                'id' => $teacher->id,
                'nom' => $teacher->nom,
                'prenom' => $teacher->prenom,
                'email' => $teacher->email,
                'telephone' => $teacher->telephone,
            ],
        ]);
    }
}
