<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EleveController extends Controller
{
    public function index()
    {
        try {
            $eleves = DB::table('eleves')
                ->leftJoin('classes', 'eleves.classe_id', '=', 'classes.id')
                ->select(
                    'eleves.*',
                    'classes.nom as classe_nom'
                )
                ->get();

            $eleves = $eleves->map(function($eleve) {
                $eleve->photo_url = $eleve->photo ? (env('APP_URL') == 'http://localhost' ? 'https://sirh.alwaysdata.net/api_carnet_liaison' : env('APP_URL', 'https://sirh.alwaysdata.net/api_carnet_liaison')) . '/storage/' . $eleve->photo : null;
                return $eleve;
            });

            return response()->json($eleves);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Résoudre classe_id depuis classe_code ou classe_id direct
            $classeId = $request->input('classe_id');
            if (!$classeId && $request->input('classe_code')) {
                $classe = DB::table('classes')->where('code', $request->input('classe_code'))->first();
                $classeId = $classe ? $classe->id : null;
            }

            if (!$classeId) {
                // If still no classe_id, check if 'Non assigné' exists, else create it
                $defaultClass = DB::table('classes')->where('nom', 'Non assigné')->first();
                if ($defaultClass) {
                    $classeId = $defaultClass->id;
                } else {
                    $classeId = DB::table('classes')->insertGetId([
                        'nom' => 'Non assigné',
                        'ecole_id' => 1,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }


            // Générer matricule automatique
            $matricule = $request->input('matricule', 'MAT-' . strtoupper(uniqid()));

            // Gérer l'upload de la photo
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('photos/eleves', 'public');
            }

            $id = DB::table('eleves')->insertGetId([
                'nom'            => $request->input('nom'),
                'prenom'         => $request->input('prenom'),
                'matricule'      => $matricule,
                'classe_id'      => $classeId,
                'code_secret'    => $request->input('code_secret'),
                'date_naissance' => $request->input('date_naissance'),
                'lieu_naissance' => $request->input('lieu_naissance'),
                'photo'          => $photoPath,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $eleve = DB::table('eleves')
                ->leftJoin('classes', 'eleves.classe_id', '=', 'classes.id')
                ->select('eleves.*', 'classes.nom as classe_nom')
                ->where('eleves.id', $id)
                ->first();

            if ($eleve) {
                $eleve->photo_url = $eleve->photo ? (env('APP_URL') == 'http://localhost' ? 'https://sirh.alwaysdata.net/api_carnet_liaison' : env('APP_URL', 'https://sirh.alwaysdata.net/api_carnet_liaison')) . '/storage/' . $eleve->photo : null;
            }

            return response()->json($eleve, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function getByClasse($classeId)
    {
        try {
            $eleves = DB::table('eleves')
                ->leftJoin('attendances', function ($join) {
                    $join->on('eleves.id', '=', 'attendances.eleve_id')
                         ->where('attendances.date', '=', date('Y-m-d'));
                })
                ->where('eleves.classe_id', $classeId)
                ->select('eleves.*', 'attendances.status as statut_presence')
                ->get();

            $eleves = $eleves->map(function($eleve) {
                $eleve->photo_url = $eleve->photo ? (env('APP_URL') == 'http://localhost' ? 'https://sirh.alwaysdata.net/api_carnet_liaison' : env('APP_URL', 'https://sirh.alwaysdata.net/api_carnet_liaison')) . '/storage/' . $eleve->photo : null;
                return $eleve;
            });

            return response()->json($eleves);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $data = [
                'nom' => $request->input('nom'),
                'prenom' => $request->input('prenom'),
                'date_naissance' => $request->input('date_naissance') ?: null,
                'lieu_naissance' => $request->input('lieu_naissance'),
                'classe_id' => $request->input('classe_id'),
                'updated_at' => now(),
            ];
            
            if ($request->has('code_secret')) {
                $data['code_secret'] = $request->input('code_secret');
            }

            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('photos', 'public');
                $data['photo'] = $path;
            }

            DB::table('eleves')->where('id', $id)->update($data);

            $eleve = DB::table('eleves')
                ->leftJoin('classes', 'eleves.classe_id', '=', 'classes.id')
                ->select(
                    'eleves.*',
                    'classes.nom as classe_nom'
                )
                ->where('eleves.id', $id)
                ->first();
            
            if ($eleve) {
                $eleve->photo_url = $eleve->photo ? (env('APP_URL') == 'http://localhost' ? 'https://sirh.alwaysdata.net/api_carnet_liaison' : env('APP_URL', 'https://sirh.alwaysdata.net/api_carnet_liaison')) . '/storage/' . $eleve->photo : null;
            }

            return response()->json($eleve);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::table('eleves')->where('id', $id)->delete();
            return response()->json(['message' => 'Deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
