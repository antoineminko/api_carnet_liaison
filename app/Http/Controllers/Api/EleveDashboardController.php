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
        $attendanceRow = DB::table('attendances')
            ->where('eleve_id', $id)
            ->where('date', $today)
            ->first();

        // Enrichissement pour l'aperçu parent
        $attendance = null;
        if ($attendanceRow) {
            $statutFr = 'Présent';
            if ($attendanceRow->status === 'absent') $statutFr = 'Absent';
            if ($attendanceRow->status === 'late')  $statutFr = 'En retard';

            $teacherName = null;
            $matiere = null;
            if (!empty($eleve->prof_principal_id)) {
                $t = DB::table('enseignants')
                    ->where('id', $eleve->prof_principal_id)
                    ->select('prenom', 'nom', 'matiere')
                    ->first();
                if ($t) {
                    $teacherName = trim(($t->prenom ?? '') . ' ' . ($t->nom ?? ''));
                    $matiere = $t->matiere ?? null;
                }
            }

            $ts = $attendanceRow->updated_at ?? $attendanceRow->created_at ?? null;
            $heureArrivee = $ts ? date('H:i', strtotime($ts)) : null;

            $attendance = [
                'statut' => $statutFr,
                'date' => $attendanceRow->date,
                'heure_arrivee' => $heureArrivee,
                'matiere' => $matiere,
                'enseignant_nom' => $teacherName,
            ];
        }

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
        
        // 4. Devoirs à venir (filtrer selon ciblage spécifique ou classe entière)
        $homeworksRaw = DB::table('devoirs')
            ->leftJoin('devoir_eleve', 'devoirs.id', '=', 'devoir_eleve.devoir_id')
            ->where('devoirs.classe_id', $eleve->classe_id)
            ->where('devoirs.date_remise', '>=', $today)
            ->where(function ($query) use ($id) {
                $query->whereNull('devoir_eleve.eleve_id')
                      ->orWhere('devoir_eleve.eleve_id', $id);
            })
            ->orderBy('devoirs.date_remise', 'asc')
            ->select(
                'devoirs.id',
                'devoirs.titre',
                'devoirs.description',
                'devoirs.matiere',
                'devoirs.type',
                'devoirs.date_remise',
                'devoirs.created_at',
                'devoirs.enseignant_id',
                'devoir_eleve.eleve_id as ciblage_eleve_id'
            )
            ->get();

        $homeworks = $homeworksRaw->map(function ($hw) use ($id) {
            return [
                'id' => $hw->id,
                'titre' => $hw->titre,
                'description' => $hw->description,
                'matiere' => $hw->matiere,
                'type' => $hw->type ?? 'maison',
                'date_remise' => $hw->date_remise,
                'created_at' => $hw->created_at,
                'enseignant_id' => $hw->enseignant_id,
                'is_targeted' => $hw->ciblage_eleve_id !== null,
                'is_for_me' => $hw->ciblage_eleve_id === null || $hw->ciblage_eleve_id == $id,
            ];
        });

        // 5. Actualités (News de l'école)
        $actualites = [
            [
                'id' => 1,
                'type' => 'MESSE',
                'titre' => 'Messe d\'ouverture semaine résurrection du Christ',
                'contenu' => 'Rejoignez-nous pour la célébration de la messe d\'ouverture de la semaine de la résurrection du Christ. Une moment de recueillement et de prière pour toute la communauté scolaire.',
                'image_url' => 'https://i.pinimg.com/736x/51/b1/a7/51b1a798455b0af03492963412bf1689.jpg',
                'date' => '2026-06-15',
                'heure' => '09:00',
                'celebrant' => 'Père Jean-Pierre Moussavou'
            ],
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

        // Finances dynamiques - SECTION DÉSACTIVÉE pour l'instant
        // Pour réactiver, remplacer par la logique dynamique avec admin_informations
        $finances = null; // Toujours null pour cacher la section "Reste à payer"

        // 7. Rendez-vous
        $appointments = DB::table('appointments')
            ->leftJoin('enseignants', 'appointments.enseignant_id', '=', 'enseignants.id')
            ->where('appointments.eleve_id', $id)
            ->select('appointments.*', 'enseignants.prenom as enseignant_prenom', 'enseignants.nom as enseignant_nom')
            ->orderBy('date_heure', 'asc')
            ->get();

        // 8. Notifications non lues
        $unread_notifications_count = DB::table('admin_informations')
            ->where('eleve_id', $id)
            ->where('is_read', false)
            ->count();

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
            'unread_notifications_count' => $unread_notifications_count
        ]);
    }
}
