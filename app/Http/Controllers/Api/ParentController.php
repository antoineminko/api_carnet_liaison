<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\ParentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ParentController extends Controller
{
    public function index()
    {
        try {
            $parents = ParentUser::with(['eleves.classe.ecole'])->get();

            return response()->json($parents->map(function ($parent) {
                return [
                    'id'         => $parent->id,
                    'nom'        => $parent->nom,
                    'prenom'     => $parent->prenom,
                    'email'      => $parent->email,
                    'telephone'  => $parent->telephone,
                    'ecole_id'   => $parent->ecole_id,
                    'nb_enfants' => $parent->eleves->count(),
                    'enfants'    => $parent->eleves->map(fn ($e) => [
                        'id'         => $e->id,
                        'nom'        => $e->nom,
                        'prenom'     => $e->prenom,
                        'classe_nom' => $e->classe?->nom,
                        'relation'   => $e->pivot?->relation,
                        'is_verified'=> $e->pivot?->is_verified,
                    ])->values(),
                ];
            })->values());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $ecoleId = $request->input('ecole_id');

            if ($request->input('eleve_id')) {
                $count = DB::table('eleve_parents')->where('eleve_id', $request->input('eleve_id'))->count();
                if ($count >= 3) {
                    return response()->json(['error' => "Cet élève a déjà le nombre maximum de 3 parents/tuteurs."], 400);
                }
            }

            if (!$ecoleId && $request->input('eleve_id')) {
                $eleve = Eleve::with('classe')->find($request->input('eleve_id'));
                $ecoleId = $eleve?->classe?->ecole_id;
            }

            $email = $request->input('email') ?: 'parent_' . time() . '_' . rand(1000, 9999) . '@carnet.local';

            $parent = ParentUser::create([
                'nom'       => $request->input('nom', 'N/A'),
                'prenom'    => $request->input('prenom', 'N/A'),
                'email'     => $email,
                'password'  => Hash::make('parent123'),
                'telephone' => $request->input('telephone'),
                'ecole_id'  => $ecoleId,
            ]);

            if ($request->input('eleve_id')) {
                DB::table('eleve_parents')->insert([
                    'eleve_id'   => $request->input('eleve_id'),
                    'parent_id'  => $parent->id,
                    'relation'   => $request->input('relation', 'Tuteur'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $parent->load('eleves.classe.ecole');
            $nb_enfants = $parent->eleves->count();

            return response()->json(array_merge($parent->toArray(), [
                'nb_enfants' => $nb_enfants,
                'enfants'    => DB::table('eleve_parents')->where('parent_id', $parent->id)->get(),
            ]), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getChildren(int $id)
    {
        try {
            $parent = ParentUser::findOrFail($id);
            $today  = date('Y-m-d');

            $eleves = $parent->eleves()
                ->with(['classe.ecole'])
                ->get()
                ->map(function ($eleve) use ($id, $today) {
                    $attendance = DB::table('attendances')
                        ->where('eleve_id', $eleve->id)
                        ->where('date', $today)
                        ->first();

                    $notifCount = DB::table('admin_informations')
                        ->where('eleve_id', $eleve->id)
                        ->where('is_read', false)
                        ->count();

                    $photoUrl = $eleve->photo
                        ? rtrim(env('APP_URL'), '/') . '/storage/' . $eleve->photo
                        : null;

                    return [
                        'id'                => $eleve->id,
                        'nom'               => $eleve->nom,
                        'prenom'            => $eleve->prenom,
                        'matricule'         => $eleve->matricule,
                        'photo_url'         => $photoUrl,
                        'classe_id'         => $eleve->classe_id,
                        'classe_nom'        => $eleve->classe?->nom,
                        'classe_code'       => $eleve->classe?->code,
                        'ecole_nom'         => $eleve->classe?->ecole?->nom,
                        'ecole_code'        => $eleve->classe?->ecole?->code,
                        'relation'          => $eleve->pivot->relation,
                        'is_verified'       => $eleve->pivot->is_verified,
                        'attendance_status' => $attendance?->status,
                        'arrival_time'      => $attendance?->created_at,
                        'notif_count'       => $notifCount,
                    ];
                });

            return response()->json($eleves->values());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function verifyChildAccess(Request $request, int $parentId, int $eleveId)
    {
        try {
            $eleve = Eleve::find($eleveId);
            if (!$eleve) {
                return response()->json(['success' => false, 'error' => 'Élève introuvable.'], 404);
            }

            if ($eleve->code_secret !== $request->input('code')) {
                return response()->json(['success' => false, 'error' => 'Code secret incorrect.'], 400);
            }

            $updated = DB::table('eleve_parents')
                ->where('parent_id', $parentId)
                ->where('eleve_id', $eleveId)
                ->update(['is_verified' => true]);

            if (!$updated) {
                return response()->json(['success' => false, 'error' => 'Liaison introuvable.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Enfant déverrouillé avec succès.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $parent = ParentUser::findOrFail($id);

            $parent->update(array_filter([
                'nom'       => $request->input('nom'),
                'prenom'    => $request->input('prenom'),
                'email'     => $request->input('email'),
                'telephone' => $request->input('telephone'),
            ], fn ($v) => $v !== null));

            if ($request->has('eleve_id')) {
                $eleveId = $request->input('eleve_id');
                DB::table('eleve_parents')->where('parent_id', $id)->delete();
                if ($eleveId) {
                    $count = DB::table('eleve_parents')->where('eleve_id', $eleveId)->where('parent_id', '!=', $id)->count();
                    if ($count >= 3) {
                        return response()->json(['error' => "Cet élève a déjà 3 parents/tuteurs."], 400);
                    }
                    DB::table('eleve_parents')->insert([
                        'eleve_id'   => $eleveId,
                        'parent_id'  => $id,
                        'relation'   => $request->input('relation', 'Tuteur'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $parent->refresh();
            $nb_enfants = DB::table('eleve_parents')->where('parent_id', $id)->count();

            return response()->json(array_merge($parent->toArray(), [
                'nb_enfants' => $nb_enfants,
                'enfants'    => DB::table('eleve_parents')->where('parent_id', $id)->get(),
            ]));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            DB::table('eleve_parents')->where('parent_id', $id)->delete();
            ParentUser::findOrFail($id)->delete();
            return response()->json(['message' => 'Deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getEvents(int $id)
    {
        $appointments = DB::table('appointments')
            ->leftJoin('enseignants', 'appointments.enseignant_id', '=', 'enseignants.id')
            ->leftJoin('eleves', 'appointments.eleve_id', '=', 'eleves.id')
            ->where('appointments.parent_id', $id)
            ->whereIn('appointments.statut', ['en_attente', 'accepte'])
            ->select(
                'appointments.*',
                'enseignants.nom as enseignant_nom',
                'enseignants.prenom as enseignant_prenom',
                'enseignants.matiere as enseignant_matiere',
                'eleves.nom as eleve_nom',
                'eleves.prenom as eleve_prenom'
            )
            ->get();

        $conversations = DB::table('conversations')
            ->leftJoin('enseignants', 'conversations.enseignant_id', '=', 'enseignants.id')
            ->leftJoin('ecoles', 'conversations.ecole_id', '=', 'ecoles.id')
            ->where('conversations.parent_id', $id)
            ->whereIn('conversations.status', ['pending', 'accepted', 'rejected'])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('messages')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->where('messages.sender_type', '!=', 'parent')
            )
            ->select(
                'conversations.*',
                'enseignants.nom as enseignant_nom',
                'enseignants.prenom as enseignant_prenom',
                'enseignants.matiere as enseignant_matiere',
                'ecoles.nom as ecole_nom'
            )
            ->get();

        return response()->json([
            'success'       => true,
            'appointments'  => $appointments,
            'conversations' => $conversations,
        ]);
    }
}
