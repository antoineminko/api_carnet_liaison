<?php

namespace App\Http\Middleware;

use App\Models\Ecole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SchoolContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->header('X-School-Code');

        if (!$code) {
            return response()->json([
                'error' => 'En-tête X-School-Code manquant.',
            ], 400);
        }

        $ecole = Ecole::where('code', strtoupper($code))->first();

        if (!$ecole) {
            return response()->json([
                'error' => "École introuvable pour le code : {$code}",
            ], 404);
        }

        $request->merge(['school' => $ecole]);
        $request->attributes->set('school', $ecole);

        return $next($request);
    }
}
