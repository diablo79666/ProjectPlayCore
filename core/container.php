<?php
// ============================================================================
// ProjectPlayCore – Zentraler Service Container
// Pfad: /backend/core/container.php
// ============================================================================
declare(strict_types=1);

namespace Core;

final class Container
{
    private static array $items = [];

    public static function set(string $key, mixed $value): void
    {
        self::$items[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$items[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$items);
    }

    public static function remove(string $key): void
    {
        unset(self::$items[$key]);
    }

    public static function all(): array
    {
        return self::$items;
    }
}
