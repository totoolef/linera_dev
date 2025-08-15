<?php

namespace App\Http\Middleware;

use App\Services\EphemeralJwtVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class VerifyEphemeralJwt
{
    public function __construct(private EphemeralJwtVerifier $verifier) {}

    /** Répond en JSON même si des octets non‑UTF8 apparaissent dans les données */
    private function jsonSafe(array $data, int $status = 200): JsonResponse
    {
        return response()->json(
            $data,
            $status,
            [],
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    public function handle(Request $request, Closure $next)
    {
        try {
            // 0) Récup token de manière robuste
            $jwt = (string) ($request->bearerToken() ?? '');
            if ($jwt === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $hdr = $_SERVER['HTTP_AUTHORIZATION'];
                if (stripos($hdr, 'Bearer ') === 0) {
                    $jwt = trim(substr($hdr, 7));
                }
            }
            // Fallback facultatif: header alternatif
            if ($jwt === '') {
                $alt = $request->header('X-Ephemeral-Token', '');
                if ($alt !== '') { $jwt = $alt; }
            }
            if ($jwt === '') {
                return $this->jsonSafe(['error' => 'missing bearer'], 401);
            }

            // Sanity check: format JWS compact (3 segments)
            if (substr_count($jwt, '.') !== 2) {
                return $this->jsonSafe([
                    'error'  => 'bad token format',
                    'reason' => 'must be compact JWS with 3 segments',
                    'len'    => strlen($jwt),
                    'dots'   => substr_count($jwt, '.'),
                    'prefix' => substr($jwt, 0, 20),
                    'suffix' => substr($jwt, -20),
                ], 401);
            }

            // 1) Vérif Ed25519 → header + payload
            [$header, $payload] = $this->verifier->verifySignature($jwt);

            // 2) Checks fondamentaux
            $now = time();
            if (!isset($payload['exp']) || $payload['exp'] < $now) {
                return $this->jsonSafe(['error' => 'token expired'], 401);
            }
            if (!isset($payload['iat']) || $payload['iat'] > $now + 5) {
                return $this->jsonSafe(['error' => 'invalid iat'], 401);
            }
            foreach (['iss','sub','method','path','bodyHash','nonce'] as $k) {
                if (!isset($payload[$k])) {
                    return $this->jsonSafe(['error' => "payload missing {$k}"], 400);
                }
            }

            // 3) Method + path (tolère avec/sans /api)
            $reqMethod = strtoupper($request->getMethod());
            $jwtMethod = strtoupper((string)$payload['method']);
            if ($reqMethod !== $jwtMethod) {
                return $this->jsonSafe(['error' => 'method mismatch'], 401);
            }

            $actualPath = $request->getPathInfo();   // ex: /api/provider/openai/chat
            $jwtPath    = (string) $payload['path']; // ex: /api/... ou /...
            $match = ($actualPath === $jwtPath)
                  || ($actualPath === '/api'.$jwtPath)
                  || (str_starts_with($jwtPath, '/api') && substr($jwtPath, 4) === $actualPath);
            if (!$match) {
                return $this->jsonSafe([
                    'error'      => 'path mismatch',
                    'actualPath' => $actualPath,
                    'jwtPath'    => $jwtPath,
                ], 401);
            }

            // 4) bodyHash — NORMALISATION JSON AVANT HASH
            $raw = $request->getContent() ?? '';
            $normalized = $raw;

            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Re-encode en JSON canonique (compact, sans échappement de /)
                $normalized = json_encode($decoded, JSON_UNESCAPED_SLASHES);
            }

            $hash = EphemeralJwtVerifier::bodyHash($normalized);
            if ($hash !== $payload['bodyHash']) {
                return $this->jsonSafe([
                    'error'    => 'bodyHash mismatch',
                    'expected' => $payload['bodyHash'],
                    'computed' => $hash,
                    'len'      => strlen($raw),
                ], 401);
            }

            // 5) Anti‑rejeu (nonce unique)
            try {
                DB::table('jwt_nonces')->insert([
                    'nonce'      => (string) $payload['nonce'],
                    'iat'        => (int) $payload['iat'],
                    'exp'        => (int) $payload['exp'],
                    'iss'        => (string) $payload['iss'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Contrainte unique violée -> rejoué
                return $this->jsonSafe(['error' => 'replay detected'], 401);
            }

            // 6) Attache le payload vérifié à la requête
            $request->attributes->set('ephemeral_jwt', $payload);

            return $next($request);

        } catch (\Throwable $e) {
            return $this->jsonSafe([
                'error'   => 'middleware-exception',
                'message' => app()->hasDebugModeEnabled() ? (string)$e->getMessage() : 'internal',
                'type'    => get_class($e),
            ], 500);
        }
    }
}
