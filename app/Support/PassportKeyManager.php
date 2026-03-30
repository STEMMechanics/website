<?php

namespace App\Support;

use RuntimeException;
use Laravel\Passport\Passport;

class PassportKeyManager
{
    public function ensureKeysExist(): void
    {
        Passport::loadKeysFrom(storage_path());

        $privateKeyPath = storage_path('oauth-private.key');
        $publicKeyPath = storage_path('oauth-public.key');

        if ($this->passportKeysAreValid($privateKeyPath, $publicKeyPath)) {
            @chmod($privateKeyPath, 0600);
            @chmod($publicKeyPath, 0600);

            return;
        }

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $keyResource = openssl_pkey_new($config);
        if ($keyResource === false) {
            throw new RuntimeException('Unable to generate Passport keys.');
        }

        if (! openssl_pkey_export($keyResource, $privateKey)) {
            throw new RuntimeException('Unable to export Passport private key.');
        }

        $details = openssl_pkey_get_details($keyResource);
        $publicKey = $details['key'] ?? null;
        if (! is_string($publicKey) || $publicKey === '') {
            throw new RuntimeException('Unable to export Passport public key.');
        }

        file_put_contents($privateKeyPath, $privateKey);
        file_put_contents($publicKeyPath, $publicKey);

        @chmod($privateKeyPath, 0600);
        @chmod($publicKeyPath, 0600);
    }

    private function passportKeysAreValid(string $privateKeyPath, string $publicKeyPath): bool
    {
        if (! is_file($privateKeyPath) || ! is_file($publicKeyPath)) {
            return false;
        }

        $privateKey = file_get_contents($privateKeyPath);
        $publicKey = file_get_contents($publicKeyPath);

        if (! is_string($privateKey) || ! is_string($publicKey)) {
            return false;
        }

        return openssl_pkey_get_private($privateKey) !== false
            && openssl_pkey_get_public($publicKey) !== false;
    }
}
