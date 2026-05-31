<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnseignantDashboardController extends Controller
{
    public function getDashboard($id)
    {
        $enseignant = DB::table('enseignants')->where('id', $id)->first();
        if (!$enseignant) {
            return response()->json(['success' => false, 'message' => 'Enseignant non trouvé'], 404);
        }

        // Classes où il est professeur principal
        $classes = DB::table('classes')
            ->leftJoin('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
            ->where('classes.prof_principal_id', $id)
            ->select('classes.id', 'classes.nom as classe_nom', 'ecoles.nom as ecole_nom')
            ->get();

        // Récupérer aussi les classes via les devoirs (les classes où il enseigne)
        $classesViaDevoirs = DB::table('devoirs')
            ->join('classes', 'devoirs.classe_id', '=', 'classes.id')
            ->leftJoin('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
            ->where('devoirs.enseignant_id', $id)
            ->select('classes.id', 'classes.nom as classe_nom', 'ecoles.nom as ecole_nom')
            ->distinct()
            ->get();

        // Fusionner et dédupliquer par ID de classe
        $allClasses = $classes->concat($classesViaDevoirs)->unique('id')->values();

        // Ajouter le nombre d'élèves pour chaque classe
        foreach ($allClasses as $c) {
            $c->students_count = DB::table('eleves')->where('classe_id', $c->id)->count();
        }

        return response()->json([
            'success' => true,
            'teacher' => $enseignant,
            'classes' => $allClasses
        ]);
    }

    public function getClassDetails($teacherId, $classId)
    {
        $classe = DB::table('classes')
            ->leftJoin('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
            ->where('classes.id', $classId)
            ->select('classes.id', 'classes.nom as classe_nom', 'ecoles.nom as ecole_nom')
            ->first();

        if (!$classe) {
            return response()->json(['success' => false, 'message' => 'Classe non trouvée'], 404);
        }

        $eleves = DB::table('eleves')
            ->where('classe_id', $classId)
            ->select('id', 'nom', 'prenom', 'photo', 'statut')
            ->get();

        return response()->json([
            'success' => true,
            'classe' => $classe,
            'eleves' => $eleves
        ]);
    }

    public function getStudentInfo($studentId)
    {
        $eleve = DB::table('eleves')->where('id', $studentId)->first();
        if (!$eleve) {
            return response()->json(['success' => false, 'message' => 'Elève non trouvé'], 404);
        }

        $parents = [];
        $relations = DB::table('eleve_parents')->where('eleve_id', $studentId)->get();
        foreach ($relations as $relation) {
            $parentInfo = DB::table('parent_users')->where('id', $relation->parent_id)->first();
            if ($parentInfo) {
                $parents[] = [
                    'id' => $parentInfo->id,
                    'nom' => $parentInfo->nom,
                    'prenom' => $parentInfo->prenom,
                    'telephone' => $parentInfo->telephone,
                    'email' => $parentInfo->email,
                    'relation' => $relation->relation ?? 'Parent',
                ];
            }
        }

        // Calculer l'âge si date_naissance existe
        $age = null;
        if ($eleve->date_naissance) {
            $age = \Carbon\Carbon::parse($eleve->date_naissance)->age;
        }

        return response()->json([
            'success' => true,
            'eleve' => [
                'id' => $eleve->id,
                'nom' => $eleve->nom,
                'prenom' => $eleve->prenom,
                'photo' => $eleve->photo,
                'age' => $age,
                'date_naissance' => $eleve->date_naissance,
            ],
            'parents' => $parents
        ]);
    }

    public function getEvents($id)
    {
        // Rendez-vous (en_attente ou acceptés)
        $appointments = DB::table('appointments')
            ->leftJoin('parent_users', 'appointments.parent_id', '=', 'parent_users.id')
            ->leftJoin('eleves', 'appointments.eleve_id', '=', 'eleves.id')
            ->where('appointments.enseignant_id', $id)
            ->whereIn('appointments.statut', ['en_attente', 'accepte'])
            ->select('appointments.*', 'parent_users.nom as parent_nom', 'parent_users.prenom as parent_prenom', 'eleves.nom as eleve_nom', 'eleves.prenom as eleve_prenom')
            ->get();

        // Conversations en attente (demandes de messagerie du parent vers l'enseignant)
        $conversations = DB::table('conversations')
            ->leftJoin('parent_users', 'conversations.parent_id', '=', 'parent_users.id')
            ->where('conversations.enseignant_id', $id)
            ->where('conversations.status', 'pending')
            ->select('conversations.*', 'parent_users.nom as parent_nom', 'parent_users.prenom as parent_prenom')
            ->get();

        return response()->json([
            'success' => true,
            'appointments' => $appointments,
            'conversations' => $conversations,
        ]);
    }
}
