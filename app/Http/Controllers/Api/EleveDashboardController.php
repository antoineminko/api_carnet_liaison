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
            ->select('eleves.*', 'classes.nom as classe_nom', 'ecoles.nom as ecole_nom', 'ecoles.id as ecole_id', 'classes.prof_principal_id')
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

        // 2. Professeurs de l'élève
        // On récupère strictement les professeurs de la classe (prof_principal + ceux ayant assigné des devoirs)
        $teachers = collect([]);
        if ($eleve->prof_principal_id) {
            $profPrincipal = DB::table('enseignants')->where('id', $eleve->prof_principal_id)->select('id', 'prenom', 'nom', 'matiere')->first();
            if ($profPrincipal) {
                $profPrincipal->is_principal = true;
                $teachers->push($profPrincipal);
            }
        }
        
        $otherTeachersIds = DB::table('devoirs')
            ->where('classe_id', $eleve->classe_id)
            ->whereNotNull('enseignant_id')
            ->pluck('enseignant_id')
            ->unique();
            
        foreach($otherTeachersIds as $teacherId) {
            if ($teacherId != $eleve->prof_principal_id) {
                $t = DB::table('enseignants')->where('id', $teacherId)->select('id', 'prenom', 'nom', 'matiere')->first();
                if ($t) {
                    $t->is_principal = false;
                    $teachers->push($t);
                }
            }
        }

        // 3. Notes (Notes de la semaine et Historique)
        // MOCK : Simulation des notes car la table 'notes' n'est pas encore créée
        $grades = [
            // ['matiere' => 'Mathématiques', 'note' => '15/20', 'date' => date('Y-m-d', strtotime('-1 day'))],
            // Décommenter pour simuler des notes, laisser vide sinon ("Aucune note")
        ];
        $grades_history = []; // Historique complet
        
        // 4. Devoirs à venir
        $homeworks = DB::table('devoirs')
            ->where('classe_id', $eleve->classe_id)
            ->where('date_remise', '>=', $today)
            ->orderBy('date_remise', 'asc')
            ->get();

        // 5. Actualités (News de l'école)
        $actualites = [
            ['id' => 1, 'titre' => 'Réunion Parents-Professeurs', 'contenu' => 'La réunion trimestrielle aura lieu ce vendredi à 15h00.', 'date' => date('Y-m-d', strtotime('+2 days')), 'type' => 'info'],
        ];

        // 6. Informations administratives & Finances
        $dbAdminInfos = DB::table('admin_informations')
            ->where('eleve_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $adminInfos = [];
        $totalMontantAdmin = 0;

        foreach ($dbAdminInfos as $info) {
            $adminInfos[] = [
                'id'      => $info->id,
                'titre'   => $info->titre ?? 'Information',
                'contenu' => $info->contenu,
                'date'    => $info->created_at,
                'type'    => $info->type,
                'montant' => $info->montant,
                'is_read' => $info->is_read
            ];
            
            if ($info->montant) {
                $totalMontantAdmin += $info->montant;
            }
        }

        // On simule une dette de base + la somme des montants demandés par l'admin
        $baseDette = 125000;
        $solde_restant = $baseDette + $totalMontantAdmin;

        $finances = [
            'solde_restant' => $solde_restant,
            'frais_scolarite' => 450000,
            'prochain_paiement' => date('Y-m-d', strtotime('+15 days')),
            'devise' => 'FCFA'
        ];

        // 7. Rendez-vous
        $appointments = DB::table('appointments')
            ->leftJoin('enseignants', 'appointments.enseignant_id', '=', 'enseignants.id')
            ->where('appointments.eleve_id', $id)
            ->select('appointments.*', 'enseignants.prenom as enseignant_prenom', 'enseignants.nom as enseignant_nom')
            ->orderBy('date_heure', 'asc')
            ->get();

        // 8. Notifications non lues
        // On récupère l'ID du parent lié (si possible, ici simulé à 4 ou basé sur table messages)
        $unread_notifications_count = DB::table('messages')
            ->where('is_read', false)
            ->count(); // Mock simplifié

        return response()->json([
            'eleve' => $eleve,
            'attendance' => $attendance,
            'teachers' => $teachers->unique('id')->values()->all(),
            'grades' => $grades,
            'grades_history' => $grades_history,
            'homeworks' => $homeworks,
            'actualites' => $actualites,
            'finances' => $finances,
            'adminInfos' => $adminInfos,
            'appointments' => $appointments,
            'unread_notifications_count' => $unread_notifications_count > 0 ? $unread_notifications_count : rand(0, 5) // Mock dynamique pour demo
        ]);
    }
}
