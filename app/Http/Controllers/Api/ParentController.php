<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentController extends Controller
{
    public function index()
    {
        try {
            $parents = DB::table('parent_users')
                ->leftJoin('eleve_parents', 'parent_users.id', '=', 'eleve_parents.parent_id')
                ->select(
                    'parent_users.*',
                    'eleve_parents.eleve_id',
                    DB::raw('(SELECT COUNT(*) FROM eleve_parents ep WHERE ep.parent_id = parent_users.id) as nb_enfants')
                )
                ->get();
            return response()->json($parents);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $email = $request->input('email');
            if (empty($email)) {
                $email = 'parent_' . time() . '_' . rand(1000, 9999) . '@carnet.local';
            }

            $id = DB::table('parent_users')->insertGetId([
                'nom' => $request->input('nom', 'N/A'),
                'prenom' => $request->input('prenom', 'N/A'),
                'email' => $email,
                'password' => bcrypt('parent123'),
                'telephone' => $request->input('telephone', null),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($request->input('eleve_id')) {
                DB::table('eleve_parents')->insert([
                    'eleve_id' => $request->input('eleve_id'),
                    'parent_id' => $id,
                    'relation' => 'Parent',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $parent = DB::table('parent_users')->where('id', $id)->first();
            return response()->json($parent, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getChildren($id)
    {
        try {
            $eleves = DB::table('eleves')
                ->join('eleve_parents', 'eleves.id', '=', 'eleve_parents.eleve_id')
                ->leftJoin('classes', 'eleves.classe_id', '=', 'classes.id')
                ->leftJoin('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
                ->leftJoin('attendances', function ($join) {
                    $join->on('eleves.id', '=', 'attendances.eleve_id')
                         ->where('attendances.date', '=', date('Y-m-d'));
                })
                ->select(
                    'eleves.*',
                    'classes.nom as classe_nom',
                    'classes.code as classe_code',
                    'ecoles.nom as ecole_nom',
                    'ecoles.code as ecole_code',
                    'eleve_parents.relation',
                    'attendances.status as attendance_status'
                )
                ->where('eleve_parents.parent_id', $id)
                ->get();

            $eleves = $eleves->map(function($eleve) {
                $eleve->photo_url = $eleve->photo ? (env('APP_URL') == 'http://localhost' ? 'https://sirh.alwaysdata.net/api_carnet_liaison' : env('APP_URL', 'https://sirh.alwaysdata.net/api_carnet_liaison')) . '/storage/' . $eleve->photo : null;
                // Mock notification count
                $eleve->notif_count = rand(0, 5); // Remplace par DB::table('messages')...
                return $eleve;
            });

            return response()->json($eleves);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::table('parent_users')->where('id', $id)->update([
                'nom' => $request->input('nom'),
                'prenom' => $request->input('prenom'),
                'email' => $request->input('email'),
                'telephone' => $request->input('telephone'),
                'updated_at' => now(),
            ]);

            if ($request->has('eleve_id')) {
                DB::table('eleve_parents')->where('parent_id', $id)->delete();
                if ($request->input('eleve_id')) {
                    DB::table('eleve_parents')->insert([
                        'eleve_id' => $request->input('eleve_id'),
                        'parent_id' => $id,
                        'relation' => 'Parent',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $parent = DB::table('parent_users')
                ->leftJoin('eleve_parents', 'parent_users.id', '=', 'eleve_parents.parent_id')
                ->select(
                    'parent_users.*',
                    'eleve_parents.eleve_id',
                    DB::raw('(SELECT COUNT(*) FROM eleve_parents ep WHERE ep.parent_id = parent_users.id) as nb_enfants')
                )
                ->where('parent_users.id', $id)
                ->first();
            return response()->json($parent);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::table('eleve_parents')->where('parent_id', $id)->delete();
            DB::table('parent_users')->where('id', $id)->delete();
            return response()->json(['message' => 'Deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getEvents($id)
    {
        // Rendez-vous (en_attente ou acceptÃ©s)
        $appointments = DB::table('appointments')
            ->leftJoin('enseignants', 'appointments.enseignant_id', '=', 'enseignants.id')
            ->leftJoin('eleves', 'appointments.eleve_id', '=', 'eleves.id')
            ->where('appointments.parent_id', $id)
            ->whereIn('appointments.statut', ['en_attente', 'accepte'])
            ->select('appointments.*', 'enseignants.nom as enseignant_nom', 'enseignants.prenom as enseignant_prenom', 'eleves.nom as eleve_nom', 'eleves.prenom as eleve_prenom')
            ->get();

        // Conversations en attente (demandes de messagerie de l'enseignant vers le parent)
        $conversations = DB::table('conversations')
            ->leftJoin('enseignants', 'conversations.enseignant_id', '=', 'enseignants.id')
            ->where('conversations.parent_id', $id)
            ->where('conversations.status', 'pending')
            ->select('conversations.*', 'enseignants.nom as enseignant_nom', 'enseignants.prenom as enseignant_prenom')
            ->get();

        return response()->json([
            'success' => true,
            'appointments' => $appointments,
            'conversations' => $conversations,
        ]);
    }
}
