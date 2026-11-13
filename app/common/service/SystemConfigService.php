<?php
namespace app\common\service;

use app\common\model\SystemConfig;

class SystemConfigService
{
    private static array $cache = [];
    private static bool $loaded = false;

    public static function getAll(): array
    {
        if (self::$loaded) {
            return self::$cache;
        }
        $rows = SystemConfig::select()->toArray();
        foreach ($rows as $row) {
            self::$cache[$row['key']] = self::castValue($row['value'], $row['type']);
        }
        self::$loaded = true;
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::getAll();
        return $all[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $row = SystemConfig::where('key', $key)->find();
        if ($row) {
            $row->save(['value' => $value]);
            $type = $row->type;
        } else {
            // key 不存在时补插入，避免 update 影响 0 行的静默失败
            SystemConfig::create(['key' => $key, 'value' => $value, 'type' => 'string']);
            $type = 'string';
        }
        self::$cache[$key] = self::castValue((string) $value, $type);
    }

    public static function batchSet(array $data): void
    {
        foreach ($data as $key => $value) {
            self::set($key, $value);
        }
    }

    private static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'number' => (int) $value,
            'bool'   => (bool) $value,
            default  => $value,
        };
    }
}
