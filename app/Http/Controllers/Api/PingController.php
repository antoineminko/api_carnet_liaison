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
}
