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
        $hasTeacherColumn = \Illuminate\Support\Facades\Schema::hasColumn('attendances', 'enseignant_id');
        $attendanceQuery = DB::table('attendances')
            ->where('attendances.eleve_id', $id)
            ->where('attendances.date', $today)
            ->orderByDesc('attendances.created_at');

        if ($hasTeacherColumn) {
            $attendanceQuery
                ->leftJoin('enseignants', 'attendances.enseignant_id', '=', 'enseignants.id')
                ->select(
                    'attendances.status',
                    'attendances.date',
                    'attendances.matiere',
                    'attendances.created_at',
                    'attendances.updated_at',
                    'enseignants.prenom',
                    'enseignants.nom',
                    'enseignants.matiere as enseignant_matiere'
                );
        }

        $attendanceRows = $attendanceQuery->get();
        $attendances = $attendanceRows->map(function ($row) use ($profPrincipal, $classe) {
            $statutFr = match ($row->status) {
                'absent' => 'Absent',
                'late'   => 'En retard',
                default  => 'Présent',
            };
            $ts = $row->updated_at ?? $row->created_at ?? null;
            $prenom = property_exists($row, 'prenom') ? $row->prenom : '';
            $nom = property_exists($row, 'nom') ? $row->nom : '';
            $teacherSubject = property_exists($row, 'enseignant_matiere') ? $row->enseignant_matiere : null;
            $teacherName = trim(($prenom ?? '') . ' ' . ($nom ?? ''));
            if ($teacherName === '') {
                $teacherName = $profPrincipal
                    ? trim($profPrincipal->prenom . ' ' . $profPrincipal->nom)
                    : ($classe?->enseignants->first()
                        ? trim($classe->enseignants->first()->prenom . ' ' . $classe->enseignants->first()->nom)
                        : '');
            }
            $matiere = !empty($row->matiere)
                ? $row->matiere
                : ($teacherSubject ?: ($profPrincipal?->matiere ?: 'Cours'));

            return [
                'statut'         => $statutFr,
                'status'         => $row->status,
                'date'           => $row->date,
                'heure'          => $ts ? date('H:i', strtotime($ts)) : null,
                'heure_arrivee'  => $ts ? date('H:i', strtotime($ts)) : null,
                'matiere'        => $matiere,
                'enseignant_nom' => $teacherName,
            ];
        })->values();

        /* Le résumé reste le dernier appel de la journée. La liste contient chaque cours. */
        $attendance = $attendances->last();

        /* Extraction de l'équipe pédagogique (optimisée via eager loading pour éviter le N+1) */
        $principalId = $classe?->prof_principal_id;
        $teachers = collect();

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

        /* Agrégation des évaluations récentes et constitution de l'historique des notes */
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
            /* Identification des résultats insuffisants nécessitant une attention particulière */
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

        $grades_history = $grades;
        
        /* Filtrage des devoirs à venir : inclut les devoirs généraux de la classe et ceux ciblés spécifiquement sur cet élève */
        $homeworksRaw = DB::table('devoirs')
            ->leftJoin('devoir_eleve', 'devoirs.id', '=', 'devoir_eleve.devoir_id')
            ->leftJoin('enseignants', 'devoirs.enseignant_id', '=', 'enseignants.id')
            ->where('devoirs.classe_id', $eleve->classe_id)
            ->where(function($q) {
                $q->where('devoirs.date_remise', '>=', date('Y-m-d', strtotime('-30 days')))
                  ->orWhere('devoirs.date_realisation', '>=', date('Y-m-d', strtotime('-30 days')));
            })
            ->where(function ($query) use ($id) {
                $query->whereNull('devoir_eleve.eleve_id')
                      ->orWhere('devoir_eleve.eleve_id', $id);
            })
            ->orderBy(DB::raw('COALESCE(devoirs.date_remise, devoirs.date_realisation)'), 'asc')
            ->select(
                'devoirs.id',
                'devoirs.titre',
                'devoirs.description',
                'devoirs.matiere',
                'devoirs.type',
                'devoirs.date_remise',
                'devoirs.date_realisation',
                'devoirs.cahier_texte_id',
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
                'date_realisation' => $hw->date_realisation,
                'cahier_texte_id' => $hw->cahier_texte_id,
                'created_at' => $hw->created_at,
                'enseignant_id' => $hw->enseignant_id,
                'enseignant_nom' => trim(($hw->prof_prenom ?? '') . ' ' . ($hw->prof_nom ?? '')),
                'is_targeted' => $hw->ciblage_eleve_id !== null,
                'is_for_me' => $hw->ciblage_eleve_id === null || $hw->ciblage_eleve_id == $id,
            ];
        });

        /* Chargement du flux d'actualités de l'établissement */
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

        /* Récupération des circulaires administratives et situations financières */
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

        /* Planning des rendez-vous validés avec le corps enseignant */
        $appointments = DB::table('appointments')
            ->leftJoin('enseignants', 'appointments.enseignant_id', '=', 'enseignants.id')
            ->where('appointments.eleve_id', $id)
            ->select('appointments.*', 'enseignants.prenom as enseignant_prenom', 'enseignants.nom as enseignant_nom')
            ->orderBy('date_heure', 'asc')
            ->get();

        /* Calcul du compteur des éléments non lus pour l'affichage du badge d'alerte sur mobile */
        $unread_notifications_count = $dbAdminInfos->where('is_read', false)->count();

        if (request()->has('parent_id')) {
            $apiNotificationsCount = \App\Models\Notification::where('user_type', 'parent')
                ->where('user_id', request()->parent_id)
                ->where('is_read', false)
                ->count();
            $unread_notifications_count += $apiNotificationsCount;
        }

        return response()->json([
            'eleve'                      => array_merge($eleve->toArray(), [
                'classe_nom' => $classe?->nom,
                'ecole_nom'  => $classe?->ecole?->nom,
                'ecole_id'   => $classe?->ecole_id,
            ]),
            'attendance'                 => $attendance,
            'attendances'                => $attendances,
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
