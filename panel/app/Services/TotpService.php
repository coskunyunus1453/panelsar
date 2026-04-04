<?php

namespace App\Services;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * RFC4648 Base32 (A-Z2-7) - padding olmadan döner.
     */
    public function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $binary = '';
        $bytes = array_values(unpack('C*', $data));
        foreach ($bytes as $byte) {
            $binary .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $padding = (5 - (strlen($binary) % 5)) % 5;
        if ($padding > 0) {
            $binary .= str_repeat('0', $padding);
        }

        $chunks = str_split($binary, 5);
        $encoded = '';
        foreach ($chunks as $chunk) {
            $index = bindec($chunk);
            $encoded .= self::ALPHABET[$index];
        }

        return $encoded;
    }

    public function base32Decode(string $secret): string
    {
        $secret = strtoupper(trim($secret));
        $secret = preg_replace('/=+$/', '', $secret);

        if ($secret === '') {
            return '';
        }

        $bits = '';
        $chars = str_split($secret);
        foreach ($chars as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                throw new \InvalidArgumentException('Invalid base32 character.');
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $byte = bindec(substr($bits, $i, 8));
            $out .= chr($byte);
        }

        return $out;
    }

    public function generateSecret(int $lengthBytes = 20): string
    {
        $raw = random_bytes($lengthBytes);

        return $this->base32Encode($raw);
    }

    private function intToUint64Bytes(int $counter): string
    {
        // 64-bit big-endian
        $high = intdiv($counter, 4294967296); // 2^32
        $low = $counter % 4294967296;

        return pack('N2', $high, $low);
    }

    public function getCode(string $secret, ?int $time = null, int $period = 30, int $digits = 6): string
    {
        $time = $time ?? time();
        $counter = intdiv($time, $period);

        $key = $this->base32Decode($secret);
        if ($key === '') {
            throw new \RuntimeException('TOTP secret is empty.');
        }

        $msg = $this->intToUint64Bytes($counter);
        $hash = hash_hmac('sha1', $msg, $key, true);

        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = substr($hash, $offset, 4);
        $value = unpack('N', $truncated)[1] & 0x7FFFFFFF;

        $otp = $value % (10 ** $digits);

        return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * $window: zaman penceresi (± period kadar drift toleransı).
     */
    public function verifyCode(string $secret, string $code, int $window = 1, int $period = 30, int $digits = 6): bool
    {
        $code = trim($code);
        if (! preg_match('/^\d{'.$digits.'}$/', $code)) {
            return false;
        }

        try {
            $now = time();
            for ($i = -$window; $i <= $window; $i++) {
                $expected = $this->getCode($secret, $now + ($i * $period), $period, $digits);
                if (hash_equals($expected, $code)) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
