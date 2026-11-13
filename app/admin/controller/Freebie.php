<?php
namespace app\admin\controller;

use app\admin\service\FreebieService;
use app\common\service\LogService;

class Freebie extends Base
{
    private FreebieService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->checkLogin();
        $this->service = new FreebieService();
    }

    public function index(): \think\Response
    {
        return $this->view('freebie/index', [
            'materials'   => $this->service->materialOptions(),
            'currentMenu' => 'freebie',
            'pageTitle'   => '免费资料',
        ]);
    }

    public function list(): \think\Response
    {
        return $this->success($this->service->listWithCondition());
    }

    public function save(): \think\Response
    {
        try {
            $data = $this->service->normalize(input('post.'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }
        $id = (int) ($data['id'] ?? 0);
        if ($id) {
            $this->service->update($id, $data);
            LogService::record('免费资料', '编辑', '编辑免费资料：' . $data['title']);
        } else {
            $this->service->create($data);
            LogService::record('免费资料', '新增', '新增免费资料：' . $data['title']);
        }
        return $this->success();
    }

    public function status(): \think\Response
    {
        $id     = (int) input('post.id');
        $status = (int) input('post.status') === 1 ? 1 : 0;
        try {
            $row = $this->service->find($id);
        } catch (\think\db\exception\ModelNotFoundException $e) {
            return $this->error('资料不存在');
        }
        $this->service->update($id, ['status' => $status]);
        LogService::record('免费资料', $status ? '上架' : '下架', ($status ? '上架' : '下架') . '免费资料：' . $row['title']);
        return $this->success();
    }

    public function delete(): \think\Response
    {
        $id  = (int) input('post.id');
        try {
            $row = $this->service->find($id);
        } catch (\think\db\exception\ModelNotFoundException $e) {
            return $this->error('资料不存在');
        }
        $this->service->delete($id);
        LogService::record('免费资料', '删除', '删除免费资料：' . $row['title']);
        return $this->success();
    }
}
