<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AlgorandService
{
    private string $baseUrl;

    public function __construct()
    {
        // URL du microservice (config/services.php -> 'algorand' => ['url' => env('ALGORAND_SERVICE_URL', 'http://127.0.0.1:8081')])
        $this->baseUrl = rtrim(config('services.algorand.url', 'http://127.0.0.1:8081'), '/');
    }

    /** Petit helper HTTP avec timeout & accept JSON */
    private function http()
    {
        return Http::timeout(8)
            ->acceptJson()
            ->asJson();
    }

    /** Transfert ASA banque -> utilisateur (amount en micro, envoyé en string pour safety bigint) */
    public function transfer(string $to, int $asaId, int $amountMicro): array
    {
        $resp = $this->http()->post("{$this->baseUrl}/asa/transfer", [
            'to'     => $to,
            'asaId'  => $asaId,
            'amount' => (string) $amountMicro,
        ])->throw();

        return $resp->json();
    }

    /** Info transaction (pending/confirmed) */
    public function tx(string $txId): array
    {
        $resp = $this->http()->get("{$this->baseUrl}/tx/{$txId}")->throw();
        return $resp->json();
    }

    /** Délégation d’émission de JWT éphémère côté microservice (optionnel si tu l’utilises) */
    public function issueJwt(array $data): array
    {
        $resp = $this->http()->post("{$this->baseUrl}/jwt/issue", $data)->throw();
        return $resp->json();
    }

    /**
     * Clé publique banque: on met en cache **uniquement des champs texte** (UTF‑8),
     * pas de binaire → évite l’erreur PostgreSQL "invalid byte sequence for encoding UTF8".
     */
    public function bankPubkey(): array
    {
        return Cache::remember('algorand_bank_pubkey_v2', now()->addMinutes(10), function () {
            $resp = $this->http()->get("{$this->baseUrl}/bank/pubkey")->throw()->json();

            // Filtrage strict (texte only)
            $safe = [
                'address'         => isset($resp['address']) ? (string) $resp['address'] : null,
                'publicKeyBase64' => isset($resp['publicKeyBase64']) ? (string) $resp['publicKeyBase64'] : null,
                'publicKeyHex'    => isset($resp['publicKeyHex']) ? (string) $resp['publicKeyHex'] : null,
                'jwk'             => null,
            ];

            if (isset($resp['jwk']) && is_array($resp['jwk'])) {
                // On ne garde que des strings sûres
                $safe['jwk'] = [
                    'kty' => isset($resp['jwk']['kty']) ? (string) $resp['jwk']['kty'] : null,
                    'crv' => isset($resp['jwk']['crv']) ? (string) $resp['jwk']['crv'] : null,
                    'x'   => isset($resp['jwk']['x'])   ? (string) $resp['jwk']['x']   : null, // base64url
                    'kid' => isset($resp['jwk']['kid']) ? (string) $resp['jwk']['kid'] : null,
                ];
            }

            return $safe;
        });
    }
}
