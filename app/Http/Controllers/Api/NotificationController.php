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
            'parent_id'     => 'nullable|integer', 
            'enseignant_id' => 'nullable|integer', 
            'token'         => 'required|string',
            'platform'      => 'nullable|string',
        ]);

        if ($request->has('parent_id') && $request->parent_id) {
            $parent = ParentUser::find($request->parent_id);
            if ($parent) {
                $parent->fcm_token = $request->token;
                $parent->save();
            }
        }

        if ($request->has('enseignant_id') && $request->enseignant_id) {
            $enseignant = \App\Models\Enseignant::find($request->enseignant_id);
            if ($enseignant) {
                $enseignant->fcm_token = $request->token;
                $enseignant->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Token FCM enregistré avec succès.',
        ]);
    }

    public function index($role, $user_id)
    {
        if (!in_array($role, ['parent', 'enseignant', 'admin'])) {
            return response()->json(['success' => false, 'error' => 'Rôle invalide'], 400);
        }

        $notifications = \App\Models\Notification::where('user_type', $role)
            ->where('user_id', $user_id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'notifications' => $notifications
        ]);
    }

    public function markAsRead($id)
    {
        $notification = \App\Models\Notification::find($id);
        if ($notification) {
            $notification->is_read = true;
            $notification->save();

            if ($notification->type === 'admin_info' && isset($notification->data['admin_info_id'])) {
                \Illuminate\Support\Facades\DB::table('admin_informations')
                    ->where('id', $notification->data['admin_info_id'])
                    ->update(['is_read' => true]);
            }
        }

        return response()->json(['success' => true]);
    }
}

