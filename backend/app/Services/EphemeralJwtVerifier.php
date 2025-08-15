<?php

namespace App\Services;

class EphemeralJwtVerifier
{
    public function __construct(private AlgorandService $algorand) {}

    /** SHA-256 → base64url (sans =) du corps brut, pour bodyHash */
    public static function bodyHash(string $raw): string
    {
        $digest = hash('sha256', $raw, true);
        return rtrim(strtr(base64_encode($digest), '+/', '-_'), '=');
    }

    /** base64url decode robuste */
    private static function b64url_decode(string $s): string|false
    {
        $b64 = strtr($s, '-_', '+/');
        $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
        return base64_decode($b64, true);
    }

    /**
     * Vérifie la signature Ed25519 d’un JWT compact (3 segments).
     * Retourne [header_assoc_array, payload_assoc_array] si OK, sinon exception.
     */
    public function verifySignature(string $jwt): array
    {
        // 1) Format compact
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \RuntimeException('bad jwt compact format');
        }
        [$h64, $p64, $s64] = $parts;
        $signingInput = $h64 . '.' . $p64;

        // 2) Decode et parse JSON
        $hbin = self::b64url_decode($h64);
        $pbin = self::b64url_decode($p64);
        $sbin = self::b64url_decode($s64);

        if ($hbin === false || $pbin === false || $sbin === false) {
            throw new \RuntimeException('base64url decode failed');
        }
        if (strlen($sbin) !== 64) {
            throw new \RuntimeException('invalid signature length');
        }

        try {
            $header  = json_decode($hbin, true, flags: JSON_THROW_ON_ERROR);
            $payload = json_decode($pbin, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new \RuntimeException('invalid jwt json: '.$e->getMessage());
        }

        // 3) Header checks
        if (($header['alg'] ?? null) !== 'EdDSA') {
            throw new \RuntimeException('alg must be EdDSA');
        }
        // typ/kid optionnels — on ne les rend pas bloquants.

        // 4) Récup clé publique de la banque (texte only via AlgorandService)
        $pub = $this->algorand->bankPubkey();

        $pkBin = null;
        // JWK (x en base64url) prioritaire
        if (isset($pub['jwk']['x']) && is_string($pub['jwk']['x'])) {
            $pkBin = self::b64url_decode($pub['jwk']['x']);
        }
        // Base64 standard
        if (!$pkBin && isset($pub['publicKeyBase64']) && is_string($pub['publicKeyBase64'])) {
            $pkBin = base64_decode($pub['publicKeyBase64'], true);
        }
        // Hex fallback
        if (!$pkBin && isset($pub['publicKeyHex']) && is_string($pub['publicKeyHex'])) {
            $pkBin = hex2bin($pub['publicKeyHex']);
        }
        if (!$pkBin || strlen($pkBin) !== 32) {
            throw new \RuntimeException('invalid public key');
        }

        // 5) Vérif Ed25519 (libsodium)
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new \RuntimeException('sodium extension missing');
        }
        $ok = sodium_crypto_sign_verify_detached($sbin, $signingInput, $pkBin);
        if (!$ok) {
            throw new \RuntimeException('bad signature');
        }

        // (Optionnel) cohérence iss/kid/adresse — on ne bloque pas ici.
        return [$header, $payload];
    }
}
