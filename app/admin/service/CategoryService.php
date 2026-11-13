<?php
namespace app\admin\service;

use app\common\model\Category;
use app\common\model\Material;
use app\common\service\BaseCrudService;
use think\db\BaseQuery;

class CategoryService extends BaseCrudService
{
    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function allowFields(): array
    {
        return ['parent_id', 'name', 'icon', 'sort', 'status'];
    }

    protected function buildListQuery(array $params): BaseQuery
    {
        return Category::order('sort', 'asc')->order('id', 'asc');
    }

    // 覆盖 delete：有子分类或分类下有资料时不允许删除，防止产生孤儿数据
    public function delete(int $id): bool
    {
        if (Category::where('parent_id', $id)->find()) {
            throw new \RuntimeException('该分类下还有子分类，请先删除子分类');
        }
        if (Material::where('category_id', $id)->find()) {
            throw new \RuntimeException('该分类下还有资料，请先移走或删除资料');
        }
        return parent::delete($id);
    }
}
