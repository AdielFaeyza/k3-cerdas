<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyEdgeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('k3.edge_token');
        $given = (string) $request->header('X-Edge-Token', '');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Edge token tidak valid'], 401);
        }

        return $next($request);
    }
}
