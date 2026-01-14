<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security;

class Encryptor
{
    private string $secretKeyPath;

    public function __construct(string $secretKeyPath)
    {
        $this->secretKeyPath = $secretKeyPath;
    }

    public function encrypt(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $key = $this->getSecretKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Encrypt the data.
        $encryptedData = sodium_crypto_secretbox($data, $nonce, $key);

        // Concatenate the nonce and the encrypted data: we'll need both to
        // decrypt the secret later.
        $secret = sodium_bin2base64($nonce . $encryptedData, SODIUM_BASE64_VARIANT_ORIGINAL);

        // Nullify these variables to avoid leakage of sensitive data.
        sodium_memzero($data);
        sodium_memzero($key);
        sodium_memzero($nonce);
        sodium_memzero($encryptedData);

        return $secret;
    }

    public function decrypt(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $secret = sodium_base642bin($data, SODIUM_BASE64_VARIANT_ORIGINAL);
        $key = $this->getSecretKey();

        // Extract the nonce and the encrypted data from the secret.
        $nonce = mb_substr($secret, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $encryptedData = mb_substr($secret, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        // Decrypt the secret
        $plaintext = sodium_crypto_secretbox_open($encryptedData, $nonce, $key);

        if ($plaintext === false) {
            throw new \Exception('Could not decrypt the encrypted data.');
        }

        // Nullify these variables to avoid leakage of sensitive data.
        sodium_memzero($data);
        sodium_memzero($secret);
        sodium_memzero($key);
        sodium_memzero($nonce);
        sodium_memzero($encryptedData);

        return $plaintext;
    }

    private function getSecretKey(): string
    {
        if (file_exists($this->secretKeyPath)) {
            // If the secretKeyPath exists, it means that we already generated a key
            // in the past.
            $keyHex = file_get_contents($this->secretKeyPath);
        } else {
            // Otherwise, we must generate the key and store it in the secretKeyPath
            // file.
            $key = sodium_crypto_secretbox_keygen();
            $keyHex = sodium_bin2hex($key);

            // Make sure that the parent directory exists.
            $keyDirectory = dirname($this->secretKeyPath);
            if (!is_dir($keyDirectory)) {
                $result = mkdir($keyDirectory, 0755, true);
                if (!$result) {
                    throw new \Exception('Could not create the directory of the Encryptor secret key.');
                }
            }

            $result = file_put_contents($this->secretKeyPath, $keyHex);
            if ($result === false) {
                throw new \Exception('Could not save the file of the Encryptor secret key.');
            }

            // Ensure strict file permissions on the file.
            $result = chmod($this->secretKeyPath, 0600);
            if (!$result) {
                // Remove the unsecure file so it'll not be used later.
                @unlink($this->secretKeyPath);

                throw new \Exception('Could not set permissions on the file of the Encryptor secret key.');
            }
        }

        if ($keyHex === false) {
            throw new \Exception('Could not generate or load the Encryptor secret key.');
        }

        return sodium_hex2bin($keyHex);
    }
}
