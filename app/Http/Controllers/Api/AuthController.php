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
}
