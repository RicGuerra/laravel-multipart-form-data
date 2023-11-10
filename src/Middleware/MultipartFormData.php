<?php

namespace RgLaravelLib\Laravel\Request\Middleware;

use Closure;
use Illuminate\Http\Request;
use RgLaravelLib\Laravel\Request\MultipartFormDataParser;
use Symfony\Component\HttpFoundation\Response;


/**
 * 
 */
class MultipartFormData
{

    /**
     * Handle an incoming MultipartFormData request .
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */

    public function handle(Request $request, Closure $next): Response
    {
        $method = $request->getRealMethod();
        if (
            in_array($method, [Request::METHOD_PUT, Request::METHOD_PATCH]) &&
            strpos($request->headers->get('content-type'), "multipart/form-data") != -1
        ) {
            new MultipartFormDataParser($request, $method);
        }

        return $next($request);
    }
}
