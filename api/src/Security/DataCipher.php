<?php

declare(strict_types=1);

namespace App\Security;

use InvalidArgumentException;
use RuntimeException;

final class DataCipher
{
    private const VERSION = 1;

    private string $key;

    public function __construct(string $encodedKey)
    {
        $key = base64_decode($encodedKey, true);

        if (
            $key === false
            || strlen($key)
                !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES
        ) {
            throw new InvalidArgumentException(
                'Chave de criptografia invalida.'
            );
        }

        $this->key = $key;
    }

    public function encrypt(string $plaintext, string $context): string
    {
        if ($context === '') {
            throw new InvalidArgumentException(
                'Contexto de criptografia obrigatorio.'
            );
        }

        $nonce = random_bytes(
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
        );

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $context,
            $nonce,
            $this->key
        );

        $payload = chr(self::VERSION) . $nonce . $ciphertext;

        return base64_encode($payload);
    }

    public function decrypt(string $encodedPayload, string $context): string
    {
        if ($context === '') {
            throw new InvalidArgumentException(
                'Contexto de criptografia obrigatorio.'
            );
        }

        $payload = base64_decode($encodedPayload, true);

        $minimumLength = 1
            + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
            + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;

        if (
            $payload === false
            || strlen($payload) < $minimumLength
        ) {
            throw new RuntimeException(
                'Conteudo criptografado invalido.'
            );
        }

        $version = ord($payload[0]);

        if ($version !== self::VERSION) {
            throw new RuntimeException(
                'Versao de criptografia nao suportada.'
            );
        }

        $nonceLength =
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

        $nonce = substr($payload, 1, $nonceLength);
        $ciphertext = substr($payload, 1 + $nonceLength);

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            $context,
            $nonce,
            $this->key
        );

        if ($plaintext === false) {
            throw new RuntimeException(
                'Nao foi possivel descriptografar o conteudo.'
            );
        }

        return $plaintext;
    }
}
