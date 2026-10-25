<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
         $middleware->alias([
            /**** OTHER MIDDLEWARE ALIASES ****/
            'is_role'             => \App\Http\Middleware\AdminMiddleware::class,
            'notification'        => \App\Http\Middleware\CheckNotification::class,
            'lang' =>                 \App\Http\Middleware\SetLocaleFromHeader::class,
            'localize'                => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes::class,
            'localizationRedirect'    => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect'   => \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            'localeCookieRedirect'    => \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            'localeViewPath'          => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath::class,
        ]);
        $middleware->redirectGuestsTo(
            fn (Request $request) =>
                $request->is('api/*') ? null : '/login'
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $e) =>
                $request->is('api/*') || $request->expectsJson()
        );
})->create();
