<?php
namespace app\common\service;

use think\db\BaseQuery;

abstract class BaseCrudService
{
    abstract protected function modelClass(): string;

    abstract protected function allowFields(): array;

    protected function buildListQuery(array $params): BaseQuery
    {
        $class = $this->modelClass();
        return $class::order('id', 'desc');
    }

    public function list(array $params = [], int $page = 1, int $pageSize = 20): array
    {
        $query = $this->buildListQuery($params);
        $total = (clone $query)->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();
        return compact('list', 'total', 'page', 'pageSize');
    }

    public function all(array $params = []): array
    {
        return $this->buildListQuery($params)->select()->toArray();
    }

    public function find(int $id): array
    {
        $class = $this->modelClass();
        $record = $class::findOrFail($id);
        return $record->toArray();
    }

    public function create(array $data): array
    {
        $class  = $this->modelClass();
        $record = new $class();
        $record->allowField($this->allowFields())->save($data);
        return $record->toArray();
    }

    public function update(int $id, array $data): array
    {
        $class  = $this->modelClass();
        $record = $class::findOrFail($id);
        $record->allowField($this->allowFields())->save($data);
        return $record->toArray();
    }

    public function delete(int $id): bool
    {
        $class  = $this->modelClass();
        $record = $class::findOrFail($id);
        return $record->delete();
    }
}
