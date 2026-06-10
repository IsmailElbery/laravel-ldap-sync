<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Support;

final class ArabicText
{
    public static function isArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_convert_encoding(trim($value), 'UTF-8', 'UTF-8');
    }
}
