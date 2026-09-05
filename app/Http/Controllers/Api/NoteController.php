<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Devoir;
use App\Models\Note;
use App\Models\ParentUser;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Api\StoreNoteRequest;
use App\Services\NoteService;

class NoteController extends Controller
{
    protected $noteService;

    public function __construct(NoteService $noteService)
    {
        $this->noteService = $noteService;
    }

    public function store(StoreNoteRequest $request)
    {
        try {
            DB::beginTransaction();

            $result = $this->noteService->createNotes($request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Notes publiées avec succès.',
                'devoir_id' => $result['devoir_id'],
                'notifications_envoyees' => $result['notifications_envoyees']
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }
}
