<?php

namespace App\Http\Middleware;

use App\Support\SiteActivity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Registra que alguém usou o site (ver App\Support\SiteActivity). Só no grupo "web": /up não conta. */
class RecordSiteActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        SiteActivity::touch();

        return $response;
    }
}
