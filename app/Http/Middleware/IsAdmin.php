<?php

namespace App\Http\Middleware;

use App\Errors\ForbiddenError;
use App\Helpers\ResponseHandler;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user->role != 'admin') {
            return ResponseHandler::error(new ForbiddenError('No autorizado'));
        }

        return $next($request);
    }
}
