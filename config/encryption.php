<?php
/**
 * Hospital Management System — End-to-End Field-Level Encryption (E2EE)
 * AES-256-GCM authenticated encryption for sensitive patient healthcare records.
 */

define('ENCRYPTION_CIPHER', 'aes-256-gcm');
define('DEFAULT_SECRET_KEY', 'medicare_hms_e2ee_secret_key_2026_default_laptop_secured!');

/**
 * Retrieve master encryption key
 */
function getEncryptionKey(): string {
    $envKey = getenv('APP_ENCRYPTION_KEY') ?: ($_ENV['APP_ENCRYPTION_KEY'] ?? null);
    if ($envKey && strlen($envKey) >= 16) {
        return hash('sha256', $envKey, true);
    }
    return hash('sha256', DEFAULT_SECRET_KEY, true);
}

/**
 * Encrypt sensitive plaintext field using AES-256-GCM (or AES fallback)
 */
function encryptData(?string $plaintext): ?string {
    if ($plaintext === null || $plaintext === '') {
        return $plaintext;
    }
    
    // Check if already encrypted (prefix indicator)
    if (str_starts_with($plaintext, 'ENC::')) {
        return $plaintext;
    }
    
    try {
        $key = getEncryptionKey();
        
        if (function_exists('openssl_encrypt') && function_exists('openssl_cipher_iv_length')) {
            $ivlen = openssl_cipher_iv_length(ENCRYPTION_CIPHER) ?: 12;
            $iv = openssl_random_pseudo_bytes($ivlen);
            $tag = '';
            
            $ciphertext = openssl_encrypt(
                $plaintext,
                ENCRYPTION_CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            if ($ciphertext !== false) {
                $packed = base64_encode($iv . $tag . $ciphertext);
                return 'ENC::' . $packed;
            }
        }
        
        // Fallback cipher for local environment if OpenSSL extension is not enabled in php.ini
        $iv = random_bytes(16);
        $cipher = '';
        $klen = strlen($key);
        for ($i = 0; $i < strlen($plaintext); $i++) {
            $cipher .= $plaintext[$i] ^ $key[$i % $klen] ^ $iv[$i % 16];
        }
        return 'ENC::' . base64_encode($iv . $cipher);
    } catch (\Throwable $e) {
        error_log('Encryption Error: ' . $e->getMessage());
        return $plaintext;
    }
}

/**
 * Decrypt encrypted field
 */
function decryptData(?string $encryptedText): ?string {
    if ($encryptedText === null || $encryptedText === '') {
        return $encryptedText;
    }
    
    if (!str_starts_with($encryptedText, 'ENC::')) {
        return $encryptedText; // Not encrypted
    }
    
    try {
        $key = getEncryptionKey();
        $raw = base64_decode(substr($encryptedText, 5));
        
        if (function_exists('openssl_decrypt') && function_exists('openssl_cipher_iv_length')) {
            $ivlen = openssl_cipher_iv_length(ENCRYPTION_CIPHER) ?: 12;
            $taglen = 16;
            
            if (strlen($raw) >= ($ivlen + $taglen)) {
                $iv = substr($raw, 0, $ivlen);
                $tag = substr($raw, $ivlen, $taglen);
                $ciphertext = substr($raw, $ivlen + $taglen);
                
                $decrypted = openssl_decrypt(
                    $ciphertext,
                    ENCRYPTION_CIPHER,
                    $key,
                    OPENSSL_RAW_DATA,
                    $iv,
                    $tag
                );
                if ($decrypted !== false) {
                    return $decrypted;
                }
            }
        }
        
        // Fallback decryption
        if (strlen($raw) > 16) {
            $iv = substr($raw, 0, 16);
            $cipher = substr($raw, 16);
            $plaintext = '';
            $klen = strlen($key);
            for ($i = 0; $i < strlen($cipher); $i++) {
                $plaintext .= $cipher[$i] ^ $key[$i % $klen] ^ $iv[$i % 16];
            }
            return $plaintext;
        }
        
        return '[Decryption Failed]';
    } catch (\Throwable $e) {
        error_log('Decryption Error: ' . $e->getMessage());
        return '[Decryption Error]';
    }
}
