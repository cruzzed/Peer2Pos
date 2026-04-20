<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWorkgroupToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('app.workgroup_token');

        if (empty($configured)) {
            return response()->json(['error' => 'Workgroup token not configured on this node.'], 500);
        }

        $bearer = $request->bearerToken();

        if (! $bearer || ! hash_equals($configured, $bearer)) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
