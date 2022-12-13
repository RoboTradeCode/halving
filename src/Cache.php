<?php

namespace Src;

class Cache
{
    public static function set(mixed $data, string $key): void
    {
        file_put_contents(self::getFile($key), serialize($data));
    }

    public static function get(string $key, ): mixed
    {
        if ($content = @file_get_contents(self::getFile($key)))
            return unserialize($content);
        return null;
    }

    public static function delete(string $key, ): void
    {
        @unlink(self::getFile($key));
    }

    private static function getFile(string $key): string
    {
        return dirname(__DIR__) . '/storage/' . md5($key);
    }
}