<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AlgorandService;
use App\Support\Base64Url;

class TokenController extends Controller
{
    public function __construct(private AlgorandService $algorand) {}

    // POST /api/token/ephemeral
    public function issue(Request $req)
    {
        $data = $req->validate([
            'method' => 'required|string|max:10',
            'path'   => 'required|string|max:255',
            'body'   => 'nullable', // string ou object
            'ttlSeconds' => 'nullable|integer|min:10|max:300',
        ]);

        $bodyString = '';
        if ($req->has('body')) {
            $bodyString = is_string($data['body'])
                ? $data['body']
                : json_encode($data['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $bodyHash = Base64Url::encode(hash('sha256', $bodyString, true));
        $userId = $req->user()?->id ?? 1;
        $payload = [
            'sub'        => 'user:'.$userId,
            'method'     => strtoupper($data['method']),
            'path'       => $data['path'],
            'bodyHash'   => $bodyHash,
            'ttlSeconds' => $data['ttlSeconds'] ?? 60,
        ];

        $jwt = $this->algorand->issueJwt($payload);

        return response()->json([
            'token'     => $jwt['token'],
            'kid'       => $jwt['kid'] ?? null,
            'iat'       => $jwt['iat'] ?? null,
            'exp'       => $jwt['exp'] ?? null,
            'bodyHash'  => $bodyHash,
            'method'    => $payload['method'],
            'path'      => $payload['path'],
        ]);
    }

    // POST /api/token/_verify  (debug local)
    public function verifyLocal(Request $req)
    {
        $data = $req->validate([
            'token' => 'required|string',
            'body'  => 'nullable',
        ]);

        // 1) pubkey via microservice
        $pub = $this->algorand->bankPubkey();
        $pk  = base64_decode($pub['publicKeyBase64']);

        // 2) découpe du JWS compact
        $parts = explode('.', $data['token']);
        if (count($parts) !== 3) {
            return response()->json(['ok' => false, 'error' => 'bad token format'], 400);
        }
        [$h64, $p64, $s64] = $parts;
        $signingInput = $h64.'.'.$p64;
        $sig = base64_decode(strtr($s64, '-_', '+/').str_repeat('=', (4 - strlen($s64) % 4) % 4));

        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            return response()->json(['ok' => false, 'error' => 'sodium not available'], 500);
        }
        $ok = sodium_crypto_sign_verify_detached($sig, $signingInput, $pk);

        $bodyString = $req->has('body')
            ? (is_string($data['body']) ? $data['body'] : json_encode($data['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            : '';
        $expectedBodyHash = Base64Url::encode(hash('sha256', $bodyString, true));

        $payload = json_decode(base64_decode(strtr($p64, '-_', '+/').str_repeat('=', (4 - strlen($p64) % 4) % 4)), true);

        return response()->json([
            'ok'        => $ok && isset($payload['bodyHash']) && $payload['bodyHash'] === $expectedBodyHash,
            'sigValid'  => $ok,
            'payload'   => $payload,
            'expectedBodyHash' => $expectedBodyHash,
        ]);
    }
}
