<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User; // ← important

class DevToken
{
    public function handle(Request $request, Closure $next)
    {
        $auth = $request->header('Authorization', '');
        $token = trim(str_ireplace('Bearer', '', $auth));

        if ($token === 'TEST_TOKEN') {
            // on attache un vrai User Eloquent (id=1)
            if ($user = User::find(1)) {
                auth()->setUser($user);
            }
            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
