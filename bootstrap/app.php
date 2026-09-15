<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->alias([
            'school' => \App\Http\Middleware\SchoolContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => 'Les données fournies sont invalides.',
                'errors'  => $e->errors(),
                'error'   => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'code'    => 401,
                'message' => 'Non authentifié.',
                'error'   => 'Non authentifié.',
            ], 401);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'code'    => 404,
                'message' => 'Ressource introuvable.',
                'error'   => 'Ressource introuvable.',
            ], 404);
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof ModelNotFoundException
                || $e instanceof NotFoundHttpException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $message = $status >= 500 && !config('app.debug')
                ? 'Une erreur serveur est survenue.'
                : ($e->getMessage() ?: 'Une erreur est survenue.');

            $payload = [
                'success' => false,
                'code'    => $status,
                'message' => $message,
                'error'   => $message,
            ];

            if (config('app.debug')) {
                $payload['details'] = [
                    'exception' => class_basename($e),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ];
            }

            return response()->json($payload, $status);
        });
    })->create();
