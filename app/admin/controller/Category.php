<?php
namespace app\admin\controller;

use app\admin\service\CategoryService;
use app\common\service\LogService;

class Category extends Base
{
    private CategoryService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->checkLogin();
        $this->service = new CategoryService();
    }

    public function index(): \think\Response
    {
        return $this->view('category/index', ['currentMenu' => 'category', 'pageTitle' => '分类管理']);
    }

    public function list(): \think\Response
    {
        $list = $this->service->all();
        return $this->success($list);
    }

    public function save(): \think\Response
    {
        $data = input('post.');
        $id   = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->error('请填写分类名称');
        }
        if ($id) {
            $this->service->update($id, $data);
            LogService::record('分类管理', '编辑', '编辑分类：' . $name);
        } else {
            $this->service->create($data);
            LogService::record('分类管理', '新增', '新增分类：' . $name);
        }
        return $this->success();
    }

    public function delete(): \think\Response
    {
        $id = (int) input('post.id');
        try {
            $this->service->delete($id);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }
        LogService::record('分类管理', '删除', '删除分类ID：' . $id);
        return $this->success();
    }
}
