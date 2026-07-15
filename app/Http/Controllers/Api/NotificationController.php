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
                // Update all parent profiles for this user (same email/telephone)
                ParentUser::where(function ($query) use ($parent) {
                    if (!empty($parent->email)) {
                        $query->orWhere('email', $parent->email);
                    }
                    if (!empty($parent->telephone)) {
                        $query->orWhere('telephone', $parent->telephone);
                    }
                })->update(['fcm_token' => $request->token]);
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

        $userIds = [$user_id];
        
        if ($role === 'parent') {
            $parent = ParentUser::find($user_id);
            if ($parent) {
                $userIds = ParentUser::where(function ($query) use ($parent) {
                    if (!empty($parent->email)) {
                        $query->orWhere('email', $parent->email);
                    }
                    if (!empty($parent->telephone)) {
                        $query->orWhere('telephone', $parent->telephone);
                    }
                })->pluck('id')->toArray();
            }
        }

        $notifications = \App\Models\Notification::where('user_type', $role)
            ->whereIn('user_id', $userIds)
            ->where('is_read', false)
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

    public function markAllRead(Request $request)
    {
        $userId = $request->input('user_id');
        $role = $request->input('role');
        
        if ($userId && $role) {
            $userIds = [$userId];
            if ($role === 'parent') {
                $parent = ParentUser::find($userId);
                if ($parent) {
                    $userIds = ParentUser::where(function ($query) use ($parent) {
                        if (!empty($parent->email)) {
                            $query->orWhere('email', $parent->email);
                        }
                        if (!empty($parent->telephone)) {
                            $query->orWhere('telephone', $parent->telephone);
                        }
                    })->pluck('id')->toArray();
                }
            }

            \App\Models\Notification::where('user_type', $role)
                ->whereIn('user_id', $userIds)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }
            
        return response()->json(['success' => true]);
    }

    /**
     * Marquer toutes les notifications d'un élève comme lues
     * Appelé quand le parent clique sur la carte d'un enfant
     */
    public function markAllReadForChild(Request $request, $eleveId)
    {
        $parentId = $request->input('parent_id');

        // Marquer toutes les admin_informations de cet élève comme lues
        \Illuminate\Support\Facades\DB::table('admin_informations')
            ->where('eleve_id', $eleveId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        // Marquer aussi les notifications API liées à cet élève comme lues
        if ($parentId) {
            $userIds = [$parentId];
            $parent = ParentUser::find($parentId);
            if ($parent) {
                $userIds = ParentUser::where(function ($query) use ($parent) {
                    if (!empty($parent->email)) {
                        $query->orWhere('email', $parent->email);
                    }
                    if (!empty($parent->telephone)) {
                        $query->orWhere('telephone', $parent->telephone);
                    }
                })->pluck('id')->toArray();
            }

            $notifications = \App\Models\Notification::where('user_type', 'parent')
                ->whereIn('user_id', $userIds)
                ->where('is_read', false)
                ->get();

            foreach ($notifications as $notif) {
                $data = is_string($notif->data)
                    ? json_decode($notif->data, true)
                    : (array) $notif->data;
                $notifEleveId = $data['eleve_id'] ?? null;
                if ($notifEleveId && (string)$notifEleveId === (string)$eleveId) {
                    $notif->is_read = true;
                    $notif->save();
                }
            }
        }

        return response()->json(['success' => true]);
    }
}

