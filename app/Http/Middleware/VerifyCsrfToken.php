<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * The native window control routes are excluded because:
     * - They are called from the auth screen (login/register) where no
     *   Laravel session / CSRF token exists yet.
     * - They perform no sensitive data mutations — they only send OS-level
     *   window signals (minimize / maximize / close) via NativePHP.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/native/window/*',
    ];
}
