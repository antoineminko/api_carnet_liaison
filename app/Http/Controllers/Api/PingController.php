<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PingController extends Controller
{
    /**
     * Update the last_seen_at timestamp for the given user.
     */
    public function ping(Request $request)
    {
        $request->validate([
            'role' => 'required|in:parent,enseignant',
            'user_id' => 'required|integer',
        ]);

        $table = $request->role === 'parent' ? 'parent_users' : 'enseignants';
        
        $updateData = ['last_seen_at' => Carbon::now()];

        if ($request->role === 'enseignant' && $request->hasHeader('X-School-Code')) {
            $schoolCode = $request->header('X-School-Code');
            $ecole = DB::table('ecoles')->where('code', $schoolCode)->first();
            if ($ecole) {
                $updateData['active_ecole_id'] = $ecole->id;
            }
        }
        
        DB::table($table)
            ->where('id', $request->user_id)
            ->update($updateData);

        return response()->json(['success' => true]);
    }

    /**
     * Marque l'utilisateur en ligne ou hors ligne dès qu'il quitte l'application.
     */
    public function presence(Request $request)
    {
        $request->validate([
            'role' => 'required|in:parent,enseignant',
            'user_id' => 'required|integer',
            'is_online' => 'required|boolean',
        ]);

        $table = $request->role === 'parent' ? 'parent_users' : 'enseignants';
        $seenAt = $request->boolean('is_online')
            ? Carbon::now()
            : Carbon::now()->subMinutes(10);

        DB::table($table)
            ->where('id', $request->user_id)
            ->update(['last_seen_at' => $seenAt]);

        return response()->json(['success' => true, 'is_online' => $request->boolean('is_online')]);
    }
}
