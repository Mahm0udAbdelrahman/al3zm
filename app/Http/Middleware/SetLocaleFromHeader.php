<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;


class SetLocaleFromHeader
{
    // /**
    //  * Handle an incoming request.
    //  *
    //  * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
    //  */
    public function handle(Request $request, Closure $next) : Response
    {
        $locale = $request->header('Accept-Language');

        if (in_array($locale, ['ar', 'en'])) { 
            App::setLocale($locale);
        } else {
            App::setLocale(config('app.locale'));
        }

        return $next($request);
    }
}
