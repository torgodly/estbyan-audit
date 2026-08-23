<?php

use App\Http\Middleware\EnsureRegistrationFormIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Livewire\Features\SupportFileUploads\FileNotPreviewableException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'registration.active' => EnsureRegistrationFormIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Never 500 the registration form over a bad Livewire temp preview.
        $exceptions->renderable(function (
            FileNotPreviewableException $e,
            Request $request,
        ) {
            report($e);

            if ($request->hasHeader('X-Livewire')) {
                return response()->json([
                    'message' => 'تعذر عرض الصورة. أعد رفع ملف JPG أو PNG.',
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', 'تعذر عرض الصورة. أعد رفع ملف JPG أو PNG.');
        });
    })->create();
