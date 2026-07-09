<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClasseController extends Controller
{
    private function formatClasse(Classe $classe): array
    {
        return [
            'id'                => $classe->id,
            'nom'               => $classe->nom,
            'code'              => $classe->code,
            'ecole_id'          => $classe->ecole_id,
            'prof_principal_id' => $classe->prof_principal_id,
            'ecole_nom'         => $classe->ecole?->nom,
            'ecole_code'        => $classe->ecole?->code,
            'prof_principal_nom'=> $classe->profPrincipal
                ? trim($classe->profPrincipal->prenom . ' ' . $classe->profPrincipal->nom)
                : null,
            'enseignants'       => $classe->enseignants->map(fn ($e) => [
                'id'      => $e->id,
                'prenom'  => $e->prenom,
                'nom'     => $e->nom,
                'matiere' => $e->matiere,
            ])->values(),
            'created_at' => $classe->created_at,
            'updated_at' => $classe->updated_at,
        ];
    }

    public function index()
    {
        try {
            $classes = Classe::with(['ecole', 'profPrincipal', 'enseignants'])->get();
            return response()->json($classes->map(fn ($c) => $this->formatClasse($c))->values());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $nom = $request->input('nom', '');
            $code = $request->input('code') ?: strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nom));
            $code = substr($code, 0, 5);
            $suffix = 1;
            $baseCode = $code;
            while (Classe::where('code', $code)->exists()) {
                $code = $baseCode . $suffix++;
            }

            $ecoleId = $request->input('ecole_id')
                ?? Classe::latest()->value('ecole_id')
                ?? DB::table('ecoles')->value('id');

            $classe = Classe::create([
                'nom'               => $nom,
                'code'              => $code,
                'ecole_id'          => $ecoleId,
                'prof_principal_id' => $request->input('prof_principal_id'),
            ]);

            if ($request->has('enseignant_ids')) {
                $classe->enseignants()->sync($request->input('enseignant_ids', []));
            }

            $classe->load(['ecole', 'profPrincipal', 'enseignants']);

            return response()->json($this->formatClasse($classe), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $classe = Classe::findOrFail($id);

            $classe->update(array_filter([
                'nom'               => $request->input('nom'),
                'prof_principal_id' => $request->input('prof_principal_id'),
            ], fn ($v) => $v !== null));

            if ($request->has('enseignant_ids')) {
                $classe->enseignants()->sync($request->input('enseignant_ids', []));
            }

            $classe->load(['ecole', 'profPrincipal', 'enseignants']);

            return response()->json($this->formatClasse($classe));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            Classe::findOrFail($id)->delete();
            return response()->json(['message' => 'Deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getAnnouncements(int $id)
    {
        try {
            $classe = Classe::findOrFail($id);

            $niveau = explode(' ', $classe->nom)[0] ?? null;

            $elevesIds = $classe->eleves()->pluck('id')->toArray();
            $parentsIds = DB::table('eleve_parents')
                ->whereIn('eleve_id', $elevesIds)
                ->pluck('parent_id')
                ->unique()
                ->toArray();

            $broadcasts = \App\Models\AdminBroadcast::where('ecole_id', $classe->ecole_id)
                ->orderByDesc('created_at')
                ->get()
                ->filter(function ($broadcast) use ($id, $classe, $niveau, $elevesIds, $parentsIds) {
                    $cibles = is_string($broadcast->cibles)
                        ? json_decode($broadcast->cibles, true)
                        : $broadcast->cibles;
                    if (!$cibles) return false;

                    if (!empty($cibles['tous_etablissement'])) return true;

                    if (isset($cibles['classe_id'])) {
                        $ids = (array) $cibles['classe_id'];
                        if (in_array($id, $ids) || in_array((string) $id, $ids)) return true;
                    }

                    if (isset($cibles['niveaux']) && $niveau) {
                        foreach ((array) $cibles['niveaux'] as $n) {
                            if (str_starts_with($classe->nom, $n)) return true;
                        }
                    }

                    if (isset($cibles['eleve_id']) && in_array($cibles['eleve_id'], $elevesIds)) return true;
                    if (isset($cibles['parent_id']) && in_array($cibles['parent_id'], $parentsIds)) return true;

                    return false;
                })
                ->values()
                ->map(fn ($b) => [
                    'id'          => $b->id,
                    'type'        => $b->type,
                    'titre'       => $b->titre,
                    'contenu'     => $b->contenu,
                    'fichier_url' => $b->fichier_url,
                    'created_at'  => $b->created_at,
                    'cibles'      => $b->cibles,
                ]);

            return response()->json($broadcasts);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
