<?php

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnmangleRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  Request     $request   Request.
     * @param  \Closure    $next      Next.
     * @param  string|null ...$guards Guards.
     * @return Response response.
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if (isset($_SERVER['QUERY_STRING']) === true) {
            $params = $request->all();

            $string = $_SERVER['QUERY_STRING'];
            $parts = explode('&', $string);
            foreach ($parts as $part) {
                $key = $part;
                $splitPos = strpos($key, '=');
                if ($splitPos !== false) {
                    $key = urldecode(substr($key, 0, $splitPos));
                }

                $replace_key = str_replace('.', '_', $key);
                if (strpos($key, '.') !== false && array_key_exists($replace_key, $params) === true) {
                    $params[$key] = $params[$replace_key];
                    unset($params[$replace_key]);
                }
            }

            $request->replace($params);
        }//end if

        return $next($request);
    }
}
