<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Models\Classe;
use App\Models\Eleve;
use Illuminate\Support\Facades\DB;

class EnseignantDashboardController extends Controller
{
    public function getDashboard(int $id)
    {
        $enseignant = Enseignant::with('ecole')->find($id);

        if (!$enseignant) {
            return response()->json(['success' => false, 'message' => 'Enseignant non trouvé'], 404);
        }

        // Classes via prof_principal_id + via la table pivot classe_enseignant
        $classesPrincipal = Classe::with('ecole')
            ->where('prof_principal_id', $id)
            ->get();

        $classesAssignees = $enseignant->classes()->with('ecole')
            ->get();

        // Compter les élèves par classe en une seule requête
        $allClasseIds = $classesPrincipal->pluck('id')
            ->concat($classesAssignees->pluck('id'))
            ->unique();

        $studentCounts = Eleve::whereIn('classe_id', $allClasseIds)
            ->select('classe_id', DB::raw('COUNT(*) as students_count'))
            ->groupBy('classe_id')
            ->pluck('students_count', 'classe_id');

        $allClasses = $classesPrincipal->concat($classesAssignees)
            ->unique('id')
            ->map(fn ($c) => [
                'id'             => $c->id,
                'classe_nom'     => $c->nom,
                'ecole_nom'      => $c->ecole?->nom,
                'students_count' => $studentCounts[$c->id] ?? 0,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'teacher' => $enseignant,
            'classes' => $allClasses,
        ]);
    }

    public function getClassDetails(int $teacherId, int $classId)
    {
        $classe = Classe::with('ecole')->find($classId);

        if (!$classe) {
            return response()->json(['success' => false, 'message' => 'Classe non trouvée'], 404);
        }

        $baseUrl = rtrim(env('APP_URL', 'https://sirh.alwaysdata.net/api_carnet_liaison'), '/');

        $eleves = Eleve::where('classe_id', $classId)
            ->select('id', 'nom', 'prenom', 'photo', 'statut')
            ->get()
            ->map(fn ($e) => array_merge($e->toArray(), [
                'photo_url' => $e->photo ? "{$baseUrl}/storage/{$e->photo}" : null,
            ]));

        return response()->json([
            'success' => true,
            'classe'  => [
                'id'        => $classe->id,
                'classe_nom'=> $classe->nom,
                'ecole_nom' => $classe->ecole?->nom,
            ],
            'eleves'  => $eleves,
        ]);
    }

    public function getStudentInfo(int $studentId)
    {
        $eleve = Eleve::with(['parents', 'classe.ecole'])->find($studentId);

        if (!$eleve) {
            return response()->json(['success' => false, 'message' => 'Élève non trouvé'], 404);
        }

        $parents = $eleve->parents->map(fn ($p) => [
            'id'        => $p->id,
            'nom'       => $p->nom,
            'prenom'    => $p->prenom,
            'telephone' => $p->telephone,
            'email'     => $p->email,
            'relation'  => $p->pivot->relation ?? 'Parent',
        ])->values();

        return response()->json([
            'success' => true,
            'eleve'   => $eleve,
            'parents' => $parents,
        ]);
    }

    public function getEvents(int $id)
    {
        $appointments = DB::table('appointments')
            ->leftJoin('parents', 'appointments.parent_id', '=', 'parents.id')
            ->leftJoin('eleves', 'appointments.eleve_id', '=', 'eleves.id')
            ->where('appointments.enseignant_id', $id)
            ->whereIn('appointments.statut', ['en_attente', 'accepte'])
            ->select(
                'appointments.*',
                'parents.nom as parent_nom',
                'parents.prenom as parent_prenom',
                'eleves.nom as eleve_nom',
                'eleves.prenom as eleve_prenom'
            )
            ->get();

        $conversations = DB::table('conversations')
            ->leftJoin('parents', 'conversations.parent_id', '=', 'parents.id')
            ->leftJoin('ecoles', 'conversations.ecole_id', '=', 'ecoles.id')
            ->where('conversations.enseignant_id', $id)
            ->whereIn('conversations.status', ['pending', 'accepted', 'rejected'])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('messages')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->where('messages.sender_type', '!=', 'teacher')
            )
            ->select(
                'conversations.*',
                'parents.nom as parent_nom',
                'parents.prenom as parent_prenom',
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
