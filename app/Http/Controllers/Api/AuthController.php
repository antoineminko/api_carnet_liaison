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
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $parent = ParentUser::where('email', $request->email)->first();

        // For demo purposes, if parent doesn't exist, create one so the demo always works
        if (!$parent) {
            $parent = ParentUser::create([
                'nom' => 'Demo',
                'prenom' => 'Parent',
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'telephone' => '0000000000'
            ]);
        }

        // Mock token return for demo
        return response()->json([
            'success' => true,
            'token' => 'mock-token-12345',
            'parent_id' => $parent->id,
            'parent_name' => $parent->prenom . ' ' . $parent->nom,
        ]);
    }
}
