<?php
namespace app\admin\controller;

use app\admin\service\UserService;
use app\common\service\LogService;

class User extends Base
{
    private UserService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->checkLogin();
        $this->service = new UserService();
    }

    public function index(): \think\Response
    {
        return $this->view('user/index', ['currentMenu' => 'user', 'pageTitle' => '用户管理']);
    }

    public function list(): \think\Response
    {
        $params   = input('get.');
        $page     = (int) input('get.page', 1);
        $pageSize = (int) input('get.page_size', 20);
        $result   = $this->service->list($params, $page, $pageSize);
        return $this->success($result);
    }

    public function save(): \think\Response
    {
        $data = input('post.');
        $id   = (int) ($data['id'] ?? 0);
        try {
            if ($id) {
                $this->service->updateUser($id, $data);
                LogService::record('用户管理', '编辑', '编辑用户 ID：' . $id);
            } else {
                $user = $this->service->createUser($data);
                LogService::record('用户管理', '新增', '新增用户：' . ($data['account'] ?? ''));
            }
            return $this->success();
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function uploadAvatar(): \think\Response
    {
        $id   = (int) input('post.user_id');
        $file = request()->file('avatar');
        if (!$file) return $this->error('请选择图片');

        $ext = strtolower($file->getOriginalExtension());
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            return $this->error('只支持 JPG/PNG/GIF/WebP');
        }
        $dir  = public_path() . 'uploads/avatar/' . date('Ym') . '/';
        $name = md5($id . uniqid()) . '.' . $ext;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file->move($dir, $name);

        $url = '/uploads/avatar/' . date('Ym') . '/' . $name;
        if ($id) {
            \app\common\model\User::where('id', $id)->update(['avatar' => $url]);
            LogService::record('用户管理', '修改头像', '用户 ID：' . $id);
        }
        return $this->success(['url' => $url]);
    }

    public function resetPassword(): \think\Response
    {
        $id       = (int) input('post.id');
        $password = input('post.password', '');
        try {
            $this->service->resetPassword($id, $password);
            LogService::record('用户管理', '重置密码', '重置用户 ID：' . $id . ' 的密码');
            return $this->success();
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function toggleStatus(): \think\Response
    {
        $id     = (int) input('post.id');
        $status = (int) input('post.status');
        $this->service->update($id, ['status' => $status]);
        LogService::record('用户管理', $status ? '启用' : '禁用', '操作用户 ID：' . $id);
        return $this->success();
    }
}
