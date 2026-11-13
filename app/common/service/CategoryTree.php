<?php
namespace app\common\service;

use app\common\model\Category;

/**
 * 分类只有两级：顶级分类（parent_id=0）和它下面的子分类。
 * 选顶级分类时要连同子分类的资料一起算，额度也按顶级分类归组。
 */
class CategoryTree
{
    private static ?array $all = null;

    /** 全部分类 [id => row]，请求内缓存 */
    private static function all(): array
    {
        return self::$all ??= array_column(Category::order('sort', 'asc')->order('id', 'asc')->select()->toArray(), null, 'id');
    }

    /** 这个分类加上它的子分类 ID（传子分类就只有它自己） */
    public static function ids(int $id): array
    {
        $ids = [$id];
        foreach (self::all() as $c) {
            if ((int) $c['parent_id'] === $id) $ids[] = (int) $c['id'];
        }
        return $ids;
    }

    /** 所属顶级分类 ID */
    public static function topId(int $id): int
    {
        $c = self::all()[$id] ?? null;
        return $c && (int) $c['parent_id'] > 0 ? (int) $c['parent_id'] : $id;
    }

    /** 上级分类名，顶级分类返回空串 */
    public static function parentName(int $id): string
    {
        $c = self::all()[$id] ?? null;
        return $c && (int) $c['parent_id'] > 0 ? (string) (self::all()[(int) $c['parent_id']]['name'] ?? '') : '';
    }
}
