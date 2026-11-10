<?php

declare(strict_types=1);

namespace app\support\Security;

/**
 * Google Authenticator (TOTP, RFC 6238)
 */
class GoogleAuthService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567=';

    public function createSecret(int $bits = 160): string
    {
        $bytes = (int) ceil($bits / 5);
        $secret = '';
        $random = random_bytes($bytes);

        for ($i = 0; $i < $bytes; $i++) {
            $secret .= self::BASE32_CHARS[ord($random[$i]) & 31];
        }

        return $secret;
    }

    public function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp = time();
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $time = $timestamp + ($i * 30);
            if (hash_equals($this->getCode($secret, $time), $code)) {
                return true;
            }
        }

        return false;
    }

    public function getOtpAuthUrl(string $account, string $secret, ?string $issuer = null): string
    {
        $issuerName = $issuer ?? (string) env('APP_NAME', 'Webman Admin');
        $label = $issuerName . ':' . $account;

        return 'otpauth://totp/' . rawurlencode($label)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuerName)
            . '&period=30'
            . '&algorithm=SHA1'
            . '&digits=6';
    }

    private function getCode(string $secret, int $time): string
    {
        $secretKey = $this->base32Decode($secret);
        $timeSlice = (int) floor($time / 30);
        $timestamp = "\0\0\0\0" . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $timestamp, $secretKey, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $hashPart = substr($hash, $offset, 4);
        $value = unpack('N', $hashPart);
        $value = ($value[1] & 0x7FFFFFFF) % 1000000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $lookup = array_flip(str_split(rtrim(self::BASE32_CHARS, '=')));
        $buffer = '';

        foreach (str_split(strtoupper($value)) as $char) {
            if ($char === '=') {
                continue;
            }
            if (!isset($lookup[$char])) {
                continue;
            }
            $buffer .= str_pad(decbin($lookup[$char]), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($buffer, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $output .= chr(bindec($chunk));
            }
        }

        return $output;
    }
}
