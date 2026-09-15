<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Historique des appels de présence par classe (admin) + export CSV.
 */
class AdminDashboardAttendanceController extends Controller
{
    /**
     * Historique des sessions d'appel pour une classe.
     * GET /api/admin/classes/{classe_id}/attendances
     */
    public function byClasse(Request $request, $classeId)
    {
        $ecole = $request->attributes->get('school');

        $classe = DB::table('classes')
            ->where('id', $classeId)
            ->where('ecole_id', $ecole->id)
            ->first();

        if (!$classe) {
            return response()->json(['success' => false, 'error' => 'Classe introuvable'], 404);
        }

        $hasEnseignant = Schema::hasColumn('attendances', 'enseignant_id');

        $query = DB::table('attendances')
            ->where('attendances.classe_id', $classeId);

        if ($hasEnseignant) {
            $sessions = $query
                ->leftJoin('enseignants', 'attendances.enseignant_id', '=', 'enseignants.id')
                ->select(
                    DB::raw('MIN(attendances.id) as id'),
                    'attendances.date',
                    'attendances.matiere',
                    'attendances.enseignant_id',
                    DB::raw('MIN(attendances.created_at) as taken_at'),
                    DB::raw('COUNT(*) as eleves_count'),
                    DB::raw("SUM(CASE WHEN attendances.status = 'present' THEN 1 ELSE 0 END) as present_count"),
                    DB::raw("SUM(CASE WHEN attendances.status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
                    DB::raw("SUM(CASE WHEN attendances.status = 'late' THEN 1 ELSE 0 END) as late_count"),
                    'enseignants.nom as enseignant_nom',
                    'enseignants.prenom as enseignant_prenom'
                )
                ->groupBy(
                    'attendances.date',
                    'attendances.matiere',
                    'attendances.enseignant_id',
                    'enseignants.nom',
                    'enseignants.prenom'
                )
                ->orderByDesc('taken_at')
                ->get();
        } else {
            $sessions = $query
                ->select(
                    DB::raw('MIN(attendances.id) as id'),
                    'attendances.date',
                    'attendances.matiere',
                    DB::raw('MIN(attendances.created_at) as taken_at'),
                    DB::raw('COUNT(*) as eleves_count'),
                    DB::raw("SUM(CASE WHEN attendances.status = 'present' THEN 1 ELSE 0 END) as present_count"),
                    DB::raw("SUM(CASE WHEN attendances.status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
                    DB::raw("SUM(CASE WHEN attendances.status = 'late' THEN 1 ELSE 0 END) as late_count")
                )
                ->groupBy('attendances.date', 'attendances.matiere')
                ->orderByDesc('taken_at')
                ->get();
        }

        $mapped = $sessions->map(function ($s) use ($hasEnseignant, $classe) {
            $prof = $hasEnseignant
                ? (trim(($s->enseignant_prenom ?? '') . ' ' . ($s->enseignant_nom ?? '')) ?: null)
                : null;

            return [
                'id'              => (int) $s->id,
                'classe_id'       => (int) $classe->id,
                'classe_nom'      => $classe->nom,
                'date'            => $s->date,
                'matiere'         => $s->matiere,
                'enseignant_id'   => $hasEnseignant ? $s->enseignant_id : null,
                'enseignant_nom'  => $prof,
                'taken_at'        => $s->taken_at,
                'eleves_count'    => (int) $s->eleves_count,
                'present_count'   => (int) $s->present_count,
                'absent_count'    => (int) $s->absent_count,
                'late_count'      => (int) $s->late_count,
                'message'         => $this->sessionMessage($prof, $s->matiere, $s->taken_at, $s->date),
            ];
        });

        return response()->json([
            'success'    => true,
            'classe_id'  => (int) $classeId,
            'attendances'=> $mapped->values(),
        ]);
    }

    /**
     * Export CSV d'une session d'appel (via un id de présence représentatif).
     * GET /api/admin/attendances/{attendance_id}/export
     */
    public function exportSession(Request $request, $attendanceId): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $ecole = $request->attributes->get('school');
        $hasEnseignant = Schema::hasColumn('attendances', 'enseignant_id');

        $pivot = DB::table('attendances')
            ->join('classes', 'attendances.classe_id', '=', 'classes.id')
            ->where('attendances.id', $attendanceId)
            ->where('classes.ecole_id', $ecole->id)
            ->select('attendances.*', 'classes.nom as classe_nom')
            ->first();

        if (!$pivot) {
            return response()->json(['success' => false, 'error' => 'Appel introuvable'], 404);
        }

        $q = DB::table('attendances')
            ->join('eleves', 'attendances.eleve_id', '=', 'eleves.id')
            ->where('attendances.classe_id', $pivot->classe_id)
            ->where('attendances.date', $pivot->date);

        if ($pivot->matiere === null) {
            $q->whereNull('attendances.matiere');
        } else {
            $q->where('attendances.matiere', $pivot->matiere);
        }

        if ($hasEnseignant) {
            if ($pivot->enseignant_id === null) {
                $q->whereNull('attendances.enseignant_id');
            } else {
                $q->where('attendances.enseignant_id', $pivot->enseignant_id);
            }
            $q->leftJoin('enseignants', 'attendances.enseignant_id', '=', 'enseignants.id');
        }

        $rows = $q->orderBy('eleves.nom')
            ->orderBy('eleves.prenom')
            ->select(
                'attendances.status',
                'attendances.date',
                'attendances.matiere',
                'eleves.nom as eleve_nom',
                'eleves.prenom as eleve_prenom',
                $hasEnseignant
                    ? DB::raw("TRIM(CONCAT(COALESCE(enseignants.prenom,''),' ',COALESCE(enseignants.nom,''))) as enseignant_nom")
                    : DB::raw("NULL as enseignant_nom")
            )
            ->get();

        $safeDate = preg_replace('/[^0-9\-]/', '', (string) $pivot->date) ?: 'export';
        $filename = "presences_classe_{$pivot->classe_id}_{$safeDate}.csv";

        return response()->streamDownload(function () use ($rows, $pivot) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Nom élève',
                'Prénom élève',
                'Classe',
                'Statut',
                'Professeur',
                'Cours',
                'Date',
            ], ';');

            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->eleve_nom,
                    $r->eleve_prenom,
                    $pivot->classe_nom,
                    $this->statusLabel($r->status),
                    trim((string) ($r->enseignant_nom ?? '')) ?: '—',
                    $r->matiere ?: '—',
                    $r->date,
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function sessionMessage(?string $prof, ?string $matiere, $takenAt, $date): string
    {
        $profLabel = $prof ?: 'un professeur';
        $matiereLabel = $matiere ?: 'Non précisée';

        $heure = '—';
        $dateLabel = $date;
        if ($takenAt) {
            try {
                $dt = \Carbon\Carbon::parse($takenAt);
                $heure = $dt->format('H:i');
                $dateLabel = $dt->locale('fr')->isoFormat('D MMMM YYYY');
            } catch (\Throwable $e) {
                // garde les valeurs brutes
            }
        }

        return "Nouvelle fiche de présence faite par le professeur {$profLabel}, matière {$matiereLabel}, à {$heure} le {$dateLabel}.";
    }

    protected function statusLabel(?string $status): string
    {
        return match ($status) {
            'present' => 'Présent',
            'absent'  => 'Absent',
            'late'    => 'En retard',
            default   => $status ?? '—',
        };
    }
}
