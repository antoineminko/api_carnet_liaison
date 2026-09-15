<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDashboardAttendanceController extends Controller
{
    /**
     * Liste des appels du jour (ou d'une date) pour l'établissement.
     */
    public function today(Request $request)
    {
        $ecole = $request->attributes->get('school');
        $date = $request->input('date', now()->toDateString());

        $rows = $this->buildQuery($ecole->id, $date)->get();

        $mapped = $rows->map(fn ($r) => $this->mapRow($r));

        $summary = [
            'present' => $mapped->where('status', 'present')->count(),
            'absent'  => $mapped->where('status', 'absent')->count(),
            'late'    => $mapped->where('status', 'late')->count(),
            'total'   => $mapped->count(),
        ];

        return response()->json([
            'success'    => true,
            'date'       => $date,
            'summary'    => $summary,
            'attendances'=> $mapped->values(),
        ]);
    }

    /**
     * Export CSV compatible Excel (UTF-8 BOM).
     */
    public function export(Request $request): StreamedResponse
    {
        $ecole = $request->attributes->get('school');
        $date = $request->input('date', now()->toDateString());

        $rows = $this->buildQuery($ecole->id, $date)->get();
        $filename = "presences_{$date}.csv";

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
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
                    $r->classe_nom,
                    $this->statusLabel($r->status),
                    trim(($r->enseignant_prenom ?? '') . ' ' . ($r->enseignant_nom ?? '')) ?: '—',
                    $r->matiere ?: '—',
                    $r->date,
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function buildQuery(int $ecoleId, string $date)
    {
        return DB::table('attendances')
            ->join('eleves', 'attendances.eleve_id', '=', 'eleves.id')
            ->join('classes', 'attendances.classe_id', '=', 'classes.id')
            ->leftJoin('enseignants', 'attendances.enseignant_id', '=', 'enseignants.id')
            ->where('classes.ecole_id', $ecoleId)
            ->where('attendances.date', $date)
            ->orderBy('classes.nom')
            ->orderBy('eleves.nom')
            ->select(
                'attendances.id',
                'attendances.status',
                'attendances.date',
                'attendances.matiere',
                'attendances.enseignant_id',
                'attendances.created_at',
                'eleves.id as eleve_id',
                'eleves.nom as eleve_nom',
                'eleves.prenom as eleve_prenom',
                'classes.id as classe_id',
                'classes.nom as classe_nom',
                'enseignants.nom as enseignant_nom',
                'enseignants.prenom as enseignant_prenom'
            );
    }

    protected function mapRow($r): array
    {
        return [
            'id'              => $r->id,
            'eleve_id'        => $r->eleve_id,
            'eleve_nom'       => $r->eleve_nom,
            'eleve_prenom'    => $r->eleve_prenom,
            'eleve_full_name' => trim($r->eleve_prenom . ' ' . $r->eleve_nom),
            'classe_id'       => $r->classe_id,
            'classe_nom'      => $r->classe_nom,
            'status'          => $r->status,
            'status_label'    => $this->statusLabel($r->status),
            'matiere'         => $r->matiere,
            'enseignant_id'   => $r->enseignant_id,
            'enseignant_nom'  => trim(($r->enseignant_prenom ?? '') . ' ' . ($r->enseignant_nom ?? '')) ?: null,
            'date'            => $r->date,
            'created_at'      => $r->created_at,
        ];
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
