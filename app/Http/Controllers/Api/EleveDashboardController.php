<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use Illuminate\Support\Facades\DB;

class EleveDashboardController extends Controller
{
    public function getDashboard($id)
    {
        $eleve = Eleve::with([
            'classe.ecole',
            'classe.profPrincipal',
            'classe.enseignants',
        ])->find($id);

        if (!$eleve) {
            return response()->json(['message' => 'Élève non trouvé'], 404);
        }

        $classe        = $eleve->classe;
        $profPrincipal = $classe?->profPrincipal;

        $today = date('Y-m-d');
        $attendanceRow = DB::table('attendances')
            ->where('eleve_id', $id)
            ->where('date', $today)
            ->first();

        // 1. Présences du jour
        $attendance = null;
        if ($attendanceRow) {
            $statutFr = match($attendanceRow->status) {
                'absent' => 'Absent',
                'late'   => 'En retard',
                default  => 'Présent',
            };
            $ts = $attendanceRow->updated_at ?? $attendanceRow->created_at ?? null;
            $attendance = [
                'statut'        => $statutFr,
                'date'          => $attendanceRow->date,
                'heure_arrivee' => $ts ? date('H:i', strtotime($ts)) : null,
                'matiere'       => $profPrincipal?->matiere,
                'enseignant_nom'=> $profPrincipal
                    ? trim($profPrincipal->prenom . ' ' . $profPrincipal->nom)
                    : null,
            ];
        }

        // 2. Professeurs de l'élève — déjà chargés via eager loading (0 requête supplémentaire)
        $principalId = $classe?->prof_principal_id;
        $teachers = collect();

        \Log::info('[EleveDashboard] eleve_id=' . $id . ' classe_id=' . ($classe?->id) . ' profPrincipalId=' . $principalId);
        \Log::info('[EleveDashboard] enseignants count=' . ($classe?->enseignants?->count() ?? 0));
        \Log::info('[EleveDashboard] enseignants ids=' . ($classe?->enseignants?->pluck('id')->implode(',') ?? 'none'));

        if ($profPrincipal) {
            $teachers->push([
                'id'          => $profPrincipal->id,
                'prenom'      => $profPrincipal->prenom,
                'nom'         => $profPrincipal->nom,
                'matiere'     => $profPrincipal->matiere,
                'is_principal'=> true,
            ]);
        }

        foreach ($classe?->enseignants ?? [] as $t) {
            if ($t->id != $principalId) {
                $teachers->push([
                    'id'          => $t->id,
                    'prenom'      => $t->prenom,
                    'nom'         => $t->nom,
                    'matiere'     => $t->matiere,
                    'is_principal'=> false,
                ]);
            }
        }

        // 3. Notes (Notes de la semaine et Historique)
        $gradesRaw = DB::table('notes')
            ->join('devoirs', 'notes.devoir_id', '=', 'devoirs.id')
            ->leftJoin('enseignants', 'devoirs.enseignant_id', '=', 'enseignants.id')
            ->where('notes.eleve_id', $id)
            ->select(
                'notes.valeur as note',
                'notes.trimestre',
                'devoirs.matiere',
                'devoirs.titre',
                'devoirs.date_remise as date',
                'enseignants.nom as prof_nom',
                'enseignants.prenom as prof_prenom',
                'enseignants.id as enseignant_id'
            )
            ->orderBy('devoirs.date_remise', 'desc')
            ->get();

        $grades = $gradesRaw->map(function($g) {
            $isBad = false;
            // logic for < 5/20 or < 3/10
            $val = floatval(str_replace(',', '.', $g->note));
            if (str_contains($g->note, '/10')) {
                if ($val < 3) $isBad = true;
            } else {
                if ($val < 5) $isBad = true; // /20 by default
            }

            return [
                'matiere' => $g->matiere,
                'note' => $g->note,
                'titre' => $g->titre,
                'date' => $g->date,
                'teacher' => trim(($g->prof_prenom ?? '') . ' ' . ($g->prof_nom ?? '')),
                'enseignant_id' => $g->enseignant_id,
                'isBad' => $isBad,
            ];
        })->toArray();

        $grades_history = $grades; // Historique complet
        
        // 4. Devoirs à venir (filtrer selon ciblage spécifique ou classe entière)
        $homeworksRaw = DB::table('devoirs')
            ->leftJoin('devoir_eleve', 'devoirs.id', '=', 'devoir_eleve.devoir_id')
            ->leftJoin('enseignants', 'devoirs.enseignant_id', '=', 'enseignants.id')
            ->where('devoirs.classe_id', $eleve->classe_id)
            ->where('devoirs.date_remise', '>=', date('Y-m-d', strtotime('-30 days')))
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
                'devoir_eleve.eleve_id as ciblage_eleve_id',
                'enseignants.nom as prof_nom',
                'enseignants.prenom as prof_prenom'
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
                'enseignant_nom' => trim(($hw->prof_prenom ?? '') . ' ' . ($hw->prof_nom ?? '')),
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
        $dbAdminInfos = \App\Models\AdminInformation::where('eleve_id', $id)
            ->orderByDesc('created_at')
            ->get();

        $adminInfos = $dbAdminInfos->map(fn ($info) => [
            'id'      => $info->id,
            'titre'   => $info->titre ?? 'Information',
            'contenu' => $info->contenu,
            'date'    => $info->created_at,
            'type'    => $info->type,
            'montant' => $info->montant,
            'is_read' => $info->is_read,
        ])->values()->all();

        $finances = null;

        // 7. Rendez-vous
        $appointments = DB::table('appointments')
            ->leftJoin('enseignants', 'appointments.enseignant_id', '=', 'enseignants.id')
            ->where('appointments.eleve_id', $id)
            ->select('appointments.*', 'enseignants.prenom as enseignant_prenom', 'enseignants.nom as enseignant_nom')
            ->orderBy('date_heure', 'asc')
            ->get();

        // 8. Notifications non lues
        $unread_notifications_count = $dbAdminInfos->where('is_read', false)->count();

        return response()->json([
            'eleve'                      => array_merge($eleve->toArray(), [
                'classe_nom' => $classe?->nom,
                'ecole_nom'  => $classe?->ecole?->nom,
                'ecole_id'   => $classe?->ecole_id,
            ]),
            'attendance'                 => $attendance,
            'teachers'                   => $teachers->unique('id')->values()->all(),
            'grades'                     => [],
            'grades_history'             => [],
            'homeworks'                  => $homeworks,
            'actualites'                 => $actualites,
            'finances'                   => $finances,
            'adminInfos'                 => $adminInfos,
            'appointments'               => $appointments,
            'unread_notifications_count' => $unread_notifications_count,
        ]);
    }
}
