<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EleveDashboardController extends Controller
{
    public function getDashboard($id)
    {
        // Récupérer les informations de l'élève
        $eleve = DB::table('eleves')
            ->leftJoin('classes', 'eleves.classe_id', '=', 'classes.id')
            ->leftJoin('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
            ->where('eleves.id', $id)
            ->select('eleves.*', 'classes.nom as classe_nom', 'ecoles.nom as ecole_nom', 'ecoles.id as ecole_id')
            ->first();

        if (!$eleve) {
            return response()->json(['message' => 'Elève non trouvé'], 404);
        }

        // 1. Présences du jour
        $today = date('Y-m-d');
        $attendance = DB::table('attendances')
            ->where('eleve_id', $id)
            ->where('date', $today)
            ->first();

        // 2. Professeurs de l'élève (via la classe ou l'école)
        // Pour l'instant, on prend les professeurs de l'école ou le prof principal
        $teachers = DB::table('enseignants')
            ->where('ecole_id', $eleve->ecole_id)
            ->select('id', 'prenom', 'nom', 'matiere')
            ->get();

        // 3. Dernières notes (si table existante, sinon tableau vide pour le moment)
        $grades = []; // TODO: intégrer la table notes quand elle sera créée

        // 4. Devoirs à venir
        $homeworks = DB::table('devoirs')
            ->where('classe_id', $eleve->classe_id)
            ->where('date_remise', '>=', $today)
            ->orderBy('date_remise', 'asc')
            ->get();

        // 5. Informations administratives (incidents / messages admin)
        // Pour l'instant on se base sur les conversations admin s'il y en a, sinon vide
        $adminInfos = [];

        // 6. Rendez-vous et appels vidéo
        $appointments = DB::table('appointments')
            ->leftJoin('enseignants', 'appointments.enseignant_id', '=', 'enseignants.id')
            ->where('appointments.eleve_id', $id)
            ->select('appointments.*', 'enseignants.prenom as enseignant_prenom', 'enseignants.nom as enseignant_nom')
            ->orderBy('date_heure', 'asc')
            ->get();

        return response()->json([
            'eleve' => $eleve,
            'attendance' => $attendance,
            'teachers' => $teachers,
            'grades' => $grades,
            'homeworks' => $homeworks,
            'adminInfos' => $adminInfos,
            'appointments' => $appointments
        ]);
    }
}
