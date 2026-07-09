<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Models\ParentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function loginParent(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password'   => 'required|string',
        ]);

        $schoolId = $request->attributes->get('school')?->id;

        $parent = ParentUser::where(function ($q) use ($request) {
                $q->where('email', $request->identifier)
                  ->orWhere('telephone', $request->identifier);
            })
            ->when($schoolId, fn ($q) => $q->where('ecole_id', $schoolId))
            ->first();

        if (!$parent || !Hash::check($request->password, $parent->password)) {
            return response()->json(['success' => false, 'message' => 'Identifiant ou mot de passe incorrect.'], 401);
        }

        $parent->tokens()->where('name', 'parent_token')->delete();
        $token = $parent->createToken('parent_token')->plainTextToken;

        $nb_enfants = $parent->eleves()->count();

        return response()->json([
            'success' => true,
            'token'   => $token,
            'parent'  => [
                'id'         => $parent->id,
                'nom'        => $parent->nom,
                'prenom'     => $parent->prenom,
                'email'      => $parent->email,
                'telephone'  => $parent->telephone,
                'ecole_id'   => $parent->ecole_id,
                'nb_enfants' => $nb_enfants,
            ],
        ]);
    }

    public function loginTeacher(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password'   => 'required|string',
        ]);

        $schoolId = $request->attributes->get('school')?->id;

        $teacher = Enseignant::where(function ($q) use ($request) {
                $q->where('email', $request->identifier)
                  ->orWhere('telephone', $request->identifier);
            })
            ->when($schoolId, fn ($q) => $q->where('ecole_id', $schoolId))
            ->first();

        if (!$teacher || !Hash::check($request->password, $teacher->password)) {
            return response()->json(['success' => false, 'message' => 'Identifiant ou mot de passe incorrect.'], 401);
        }

        $teacher->tokens()->where('name', 'teacher_token')->delete();
        $token = $teacher->createToken('teacher_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'teacher' => [
                'id'       => $teacher->id,
                'nom'      => $teacher->nom,
                'prenom'   => $teacher->prenom,
                'email'    => $teacher->email,
                'telephone'=> $teacher->telephone,
                'matiere'  => $teacher->matiere,
                'ecole_id' => $teacher->ecole_id,
            ],
        ]);
    }

    public function loginAdmin(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $school = \App\Models\Ecole::where('email', $request->email)->first();

        if (!$school || !\Illuminate\Support\Facades\Hash::check($request->password, $school->password)) {
            return response()->json(['success' => false, 'message' => 'Email ou mot de passe incorrect.'], 401);
        }

        $school->tokens()->where('name', 'admin_token')->delete();
        $token = $school->createToken('admin_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'       => $school->id,
                'nom'      => $school->nom,
                'email'    => $school->email,
                'role'     => 'admin',
                'ecole_id' => $school->id, // They manage their own school
                'code'     => $school->code,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'Déconnecté avec succès.']);
    }
}
