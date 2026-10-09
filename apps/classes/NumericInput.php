<?php

/**
 * Parser angka untuk batas HTTP/form aplikasi.
 *
 * Nilai uang dan quantity sengaja dipisahkan karena teks "1.000" ambigu:
 * - uang: Rp 1.000 (seribu rupiah)
 * - quantity: 1.000 (satu, dengan tiga angka desimal pada input HTML/JSON)
 *
 * Di dalam aplikasi/database gunakan angka kanonik (tanpa pemisah ribuan).
 */
final class NumericInput
{
    public static function money($value, int $scale = 2): float
    {
        return self::parse($value, true, $scale, 'nilai uang');
    }

    public static function wholeMoney($value): float
    {
        return self::parse($value, true, 0, 'nilai uang');
    }

    public static function quantity($value, int $scale = 6): float
    {
        return self::parse($value, false, $scale, 'quantity');
    }

    private static function parse($value, bool $money, int $scale, string $label): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            $number = (float)$value;
            if (!is_finite($number)) {
                throw new InvalidArgumentException(ucfirst($label) . ' tidak valid');
            }
            return round($number, $scale);
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(ucfirst($label) . ' tidak valid');
        }

        $raw = trim(str_replace(["\xc2\xa0", "\u{00a0}"], ' ', $value));
        if ($raw === '') {
            return 0.0;
        }

        // Form uang dapat mengirim "Rp 1.950"; spasi bukan bagian dari angka.
        $raw = preg_replace('/\s+/u', '', $raw);
        $raw = preg_replace('/^rp\.?/i', '', $raw);

        if ($raw === '' || !preg_match('/^[+-]?[0-9][0-9.,]*$/', $raw)) {
            throw new InvalidArgumentException(ucfirst($label) . ' tidak valid');
        }

        $sign = '';
        if ($raw[0] === '-' || $raw[0] === '+') {
            $sign = $raw[0] === '-' ? '-' : '';
            $raw = substr($raw, 1);
        }

        if ($raw === '' || !preg_match('/^[0-9][0-9.,]*$/', $raw)) {
            throw new InvalidArgumentException(ucfirst($label) . ' tidak valid');
        }

        $hasComma = strpos($raw, ',') !== false;
        $hasDot = strpos($raw, '.') !== false;

        if ($hasComma && $hasDot) {
            // Mendukung 1.234,56 dan nilai kanonik/en-US 1,234.56.
            $lastComma = strrpos($raw, ',');
            $lastDot = strrpos($raw, '.');
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $validMixedPattern = $decimalSeparator === ','
                ? '/^\d{1,3}(?:\.\d{3})+,\d+$/'
                : '/^\d{1,3}(?:,\d{3})+\.\d+$/';
            if (!preg_match($validMixedPattern, $raw)) {
                throw new InvalidArgumentException(ucfirst($label) . ' tidak valid');
            }
            $decimalPosition = max($lastComma, $lastDot);
            $integerPart = substr($raw, 0, $decimalPosition);
            $fractionPart = substr($raw, $decimalPosition + 1);

            $integerPart = str_replace([',', '.'], '', $integerPart);
            if ($integerPart === '' || $fractionPart === '' || !ctype_digit($integerPart) || !ctype_digit($fractionPart)) {
                throw new InvalidArgumentException(ucfirst($label) . ' tidak valid');
            }
            // Pemisah desimal tidak boleh muncul lagi di bagian pecahan.
            if (strpos($fractionPart, $decimalSeparator) !== false || strpos($fractionPart, $decimalSeparator === ',' ? '.' : ',') !== false) {
                throw new InvalidArgumentException(ucfirst($label) . ' tidak valid');
            }
            $normalized = $integerPart . '.' . $fractionPart;
        } elseif ($hasComma) {
            $commaCount = substr_count($raw, ',');
            if ($commaCount === 1) {
                // Koma adalah desimal pada format Indonesia.
                $normalized = str_replace(',', '.', $raw);
            } elseif (preg_match('/^\d{1,3}(?:,\d{3})+$/', $raw)) {
                // Tetap menerima input uang gaya 1,234,567 dari integrasi lama.
                $normalized = str_replace(',', '', $raw);
            } else {
                throw new InvalidArgumentException(ucfirst($label) . ' tidak valid');
            }
        } elseif ($hasDot) {
            $dotCount = substr_count($raw, '.');
            if ($money && preg_match('/^\d{1,3}(?:\.\d{3})+$/', $raw)) {
                // Untuk uang, titik berkelompok adalah pemisah ribuan Indonesia.
                $normalized = str_replace('.', '', $raw);
            } elseif ($dotCount === 1) {
                // Quantity/form HTML dan JSON memakai titik sebagai desimal kanonik.
                $normalized = $raw;
            } elseif (!$money && preg_match('/^\d{1,3}(?:\.\d{3}){2,}$/', $raw)) {
                // Quantity terformat dengan minimal dua pemisah ribuan masih jelas.
                $normalized = str_replace('.', '', $raw);
            } else {
                throw new InvalidArgumentException(ucfirst($label) . ' tidak valid');
            }
        } else {
            $normalized = $raw;
        }

        $normalized = $sign . $normalized;
        if (!is_numeric($normalized)) {
            throw new InvalidArgumentException(ucfirst($label) . ' tidak valid');
        }

        $number = (float)$normalized;
        if (!is_finite($number)) {
            throw new InvalidArgumentException(ucfirst($label) . ' tidak valid');
        }

        return round($number, $scale);
    }
}
