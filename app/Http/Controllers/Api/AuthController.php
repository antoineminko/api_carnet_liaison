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

        $parents = ParentUser::with('ecole')->where(function ($q) use ($request) {
                $q->where('email', $request->identifier)
                  ->orWhere('telephone', $request->identifier);
            })
            ->when($schoolId, fn ($q) => $q->where('ecole_id', $schoolId))
            ->get();

        if ($parents->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Identifiant ou mot de passe incorrect.'], 401);
        }

        $validParents = $parents->filter(function($parent) use ($request) {
            return Hash::check($request->password, $parent->password);
        });

        if ($validParents->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Identifiant ou mot de passe incorrect.'], 401);
        }

        $responseData = [];
        $mainToken = null;

        foreach ($validParents as $parent) {
            $parent->tokens()->where('name', 'parent_token')->delete();
            $currentToken = $parent->createToken('parent_token')->plainTextToken;
            if (!$mainToken) $mainToken = $currentToken;

            $responseData[] = [
                'id'         => $parent->id,
                'nom'        => $parent->nom,
                'prenom'     => $parent->prenom,
                'email'      => $parent->email,
                'telephone'  => $parent->telephone,
                'ecole_id'      => $parent->ecole_id,
                'school_code'   => $parent->ecole->code ?? null,
                'school_acronym'=> $parent->ecole->acronyme ?? null,
                'nb_enfants'    => $parent->eleves()->count(),
                'token'      => $currentToken,
            ];
        }

        return response()->json([
            'success' => true,
            'token'   => $mainToken,
            'parent'  => $responseData[0],
            'parents' => $responseData,
        ]);
    }

    public function loginTeacher(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password'   => 'required|string',
        ]);

        $schoolId = $request->attributes->get('school')?->id;

        $teachers = Enseignant::with('ecole')->where(function ($q) use ($request) {
                $q->where('email', $request->identifier)
                  ->orWhere('telephone', $request->identifier);
            })
            ->when($schoolId, fn ($q) => $q->where('ecole_id', $schoolId))
            ->get();

        if ($teachers->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Identifiant ou mot de passe incorrect.'], 401);
        }

        $validTeachers = $teachers->filter(function($teacher) use ($request) {
            return Hash::check($request->password, $teacher->password);
        });

        if ($validTeachers->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Identifiant ou mot de passe incorrect.'], 401);
        }

        $responseData = [];
        $mainToken = null;

        foreach ($validTeachers as $teacher) {
            $teacher->tokens()->where('name', 'teacher_token')->delete();
            $currentToken = $teacher->createToken('teacher_token')->plainTextToken;
            if (!$mainToken) $mainToken = $currentToken;

            $responseData[] = [
                'id'         => $teacher->id,
                'nom'        => $teacher->nom,
                'prenom'     => $teacher->prenom,
                'email'      => $teacher->email,
                'telephone'  => $teacher->telephone,
                'matiere'    => $teacher->matiere,
                'ecole_id'   => $teacher->ecole_id,
                'school_code'=> $teacher->ecole->code ?? null,
                'ecole'      => $teacher->ecole,
                'token'      => $currentToken,
            ];
        }

        return response()->json([
            'success' => true,
            'token'   => $mainToken,
            'teacher' => $responseData[0],
            'teachers'=> $responseData,
        ]);
    }

    public function loginAdmin(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // The admin credentials are stored on the ecoles table
        // as email_admin and password_admin (hashed)
        $school = \App\Models\Ecole::where('email_admin', $request->email)->first();

        if (!$school) {
            return response()->json(['success' => false, 'message' => 'Aucun établissement trouvé avec cet email.'], 401);
        }

        if (!Hash::check($request->password, $school->password_admin)) {
            return response()->json(['success' => false, 'message' => 'Mot de passe incorrect.'], 401);
        }

        // Revoke previous tokens to prevent session accumulation
        $school->tokens()->where('name', 'admin_token')->delete();
        $token = $school->createToken('admin_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'       => $school->id,
                'nom'      => $school->nom,
                'email'    => $school->email_admin,
                'role'     => 'admin',
                'ecole_id' => $school->id,
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
