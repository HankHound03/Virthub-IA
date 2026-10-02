<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Registra eventos de seguridad y auditoria en un canal dedicado.
 *
 * Objetivo de proyecto 8: registrar accesos y actividad para auditoria.
 */
class SecurityAudit
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(Request $request, string $event, ?array $user = null, array $context = []): void
    {
        Log::channel('security')->info($event, array_merge([
            'username' => $user['username'] ?? null,
            'role' => $user['role'] ?? null,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ], $context));
    }
}
