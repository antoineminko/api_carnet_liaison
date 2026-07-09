<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ecole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EcoleController extends Controller
{
    public function index()
    {
        return response()->json(Ecole::select('id', 'nom', 'code', 'ville', 'annee_scolaire', 'logo', 'nb_classes', 'nb_profs', 'nb_eleves')->get());
    }

    public function search(Request $request)
    {
        $q = trim($request->query('q', ''));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $ecoles = Ecole::select('id', 'nom', 'code', 'ville', 'annee_scolaire', 'logo', 'nb_classes', 'nb_profs', 'nb_eleves')
            ->where('nom', 'LIKE', "%{$q}%")
            ->orWhere('code', 'LIKE', "%{$q}%")
            ->orWhere('ville', 'LIKE', "%{$q}%")
            ->limit(10)
            ->get();

        return response()->json($ecoles);
    }

    public function publicProfile(string $code)
    {
        $ecole = Ecole::select(
                'id', 'nom', 'code', 'ville', 'annee_scolaire',
                'logo', 'image_fond', 'description',
                'nb_classes', 'nb_profs', 'nb_eleves'
            )
            ->where('code', strtoupper($code))
            ->first();

        if (!$ecole) {
            return response()->json(['error' => 'École introuvable.'], 404);
        }

        // Return directly (no wrapping) — consistent with index()
        return response()->json($ecole);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom'  => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:ecoles,code',
        ]);

        $ecole = Ecole::create([
            'nom'            => $request->nom,
            'code'           => strtoupper($request->code),
            'annee_scolaire' => $request->input('annee_scolaire', date('Y') . '-' . (date('Y') + 1)),
            'ville'          => $request->input('ville'),
            'description'    => $request->input('description'),
            'logo'           => $request->input('logo'),
            'image_fond'     => $request->input('image_fond'),
            'email_admin'    => $request->input('email_admin'),
            'nb_classes'     => $request->input('nb_classes', 0),
            'nb_profs'       => $request->input('nb_profs', 0),
            'nb_eleves'      => $request->input('nb_eleves', 0),
            'password_admin' => $request->filled('password_admin')
                ? Hash::make($request->password_admin)
                : Hash::make('password1234'), // Secure default
        ]);

        return response()->json($ecole, 201);
    }

    public function update(Request $request, int $id)
    {
        $ecole = Ecole::findOrFail($id);

        $ecole->update(array_filter([
            'nom'            => $request->input('nom'),
            'annee_scolaire' => $request->input('annee_scolaire'),
            'ville'          => $request->input('ville'),
            'description'    => $request->input('description'),
            'logo'           => $request->input('logo'),
            'image_fond'     => $request->input('image_fond'),
            'email_admin'    => $request->input('email_admin'),
            'password_admin' => $request->filled('password_admin')
                ? Hash::make($request->password_admin)
                : null,
        ], fn ($v) => $v !== null));

        return response()->json($ecole);
    }

    public function destroy(int $id)
    {
        Ecole::findOrFail($id)->delete();
        return response()->json(['message' => 'École supprimée.']);
    }
}
