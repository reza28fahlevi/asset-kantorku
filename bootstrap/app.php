<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->appendToGroup('web', EnsureUserIsActive::class);
        $middleware->alias(['permission' => EnsurePermission::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport(BusinessRuleException::class);

        // Pelanggaran aturan bisnis → kembali ke form dengan pesan (ditampilkan SweetAlert)
        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });

        // Constraint / trigger database (unique, exclusion, append-only, dll)
        $exceptions->render(function (QueryException $e, Request $request) {
            $state = (string) ($e->errorInfo[0] ?? '');
            if (! str_starts_with($state, '23') && $state !== 'P0001') {
                return null;
            }
            preg_match('/ERROR:\s+(.+?)(\n|DETAIL|CONTEXT|$)/s', $e->getMessage(), $m);
            $message = 'Operasi ditolak oleh aturan integritas data'.(isset($m[1]) ? ': '.trim($m[1]) : '.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 409)
                : back()->withInput()->with('error', $message);
        });
    })->create();
