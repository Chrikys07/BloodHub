<?php
declare(strict_types=1);
namespace BloodHub\Core;

final class UserAvatar
{
    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['U'];
        $last = count($parts) > 1 ? $parts[count($parts) - 1] : '';
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . ($last !== '' ? mb_substr($last, 0, 1) : ''));
    }

    public static function data(array $user): array
    {
        $name = trim((string)($user['name'] ?? 'Usuario')) ?: 'Usuario';
        return ['name' => $name, 'photo' => trim((string)($user['photo_path'] ?? '')), 'initials' => self::initials($name)];
    }
}
