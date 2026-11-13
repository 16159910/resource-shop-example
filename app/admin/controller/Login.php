<?php
namespace app\admin\controller;

use app\common\model\Admin;
use app\common\service\LogService;

class Login extends Base
{
    protected function initialize(): void
    {
        // 登录页不检查session
        $this->adminId   = (int) session('admin_id');
        $this->adminInfo = session('admin_info') ?? [];
    }

    public function index(): mixed
    {
        if ($this->adminId) {
            return redirect('/admin');
        }
        return $this->view('login/index');
    }

    public function doLogin(): \think\Response
    {
        $username = input('post.username', '');
        $password = input('post.password', '');

        if (empty($username) || empty($password)) {
            return $this->error('请输入账号和密码');
        }

        // 防爆破：同一 IP 连续失败 5 次锁定 15 分钟
        $lockKey  = 'admin_login_fail:' . request()->ip();
        $attempts = (int) \think\facade\Cache::get($lockKey, 0);
        if ($attempts >= 5) {
            return $this->error('登录失败次数过多，请 15 分钟后再试');
        }

        $admin = Admin::where('username', $username)->find();
        if (!$admin || !password_verify($password, $admin->password)) {
            \think\facade\Cache::set($lockKey, $attempts + 1, 900);
            return $this->error('账号或密码错误');
        }
        if ($admin->status != 1) {
            return $this->error('账号已被禁用');
        }

        \think\facade\Cache::delete($lockKey);
        $admin->last_login_time = time();
        $admin->last_login_ip   = request()->ip();
        $admin->save();

        \think\facade\Session::regenerate(); // 防会话固定
        session('admin_id', $admin->id);
        // 只存展示需要的字段，避免密码哈希落进 session 文件
        session('admin_info', [
            'id'       => (int) $admin->id,
            'username' => $admin->username,
            'nickname' => $admin->nickname,
            'avatar'   => $admin->avatar,
        ]);

        return $this->success(['redirect' => '/admin']);
    }

    public function logout(): mixed
    {
        \think\facade\Session::destroy();
        return redirect('/admin/login');
    }

    /**
     * POST /admin/password  修改自己的登录密码
     *
     * 改完让会话失效，强制用新密码重新登录——免得旧会话还留在别的浏览器里。
     */
    public function changePassword(): \think\Response
    {
        $adminId = (int) session('admin_id');
        if (!$adminId) {
            return $this->error('请先登录', 401);
        }

        $old     = (string) input('post.old_password', '');
        $new     = (string) input('post.password', '');
        $confirm = (string) input('post.confirm', '');

        if ($old === '' || $new === '') {
            return $this->error('请填写当前密码和新密码');
        }
        if (strlen($new) < 8) {
            return $this->error('新密码不能少于 8 位');
        }
        if ($new !== $confirm) {
            return $this->error('两次输入的新密码不一致');
        }
        if ($new === $old) {
            return $this->error('新密码不能和当前密码相同');
        }

        $admin = Admin::find($adminId);
        if (!$admin || !password_verify($old, $admin->password)) {
            LogService::record('管理员', '改密失败', '当前密码校验不通过');
            return $this->error('当前密码不正确');
        }

        $admin->save(['password' => password_hash($new, PASSWORD_BCRYPT)]);
        LogService::record('管理员', '修改密码', "管理员「{$admin->username}」修改了登录密码");

        \think\facade\Session::clear();
        return $this->success(null, '密码已修改，请重新登录');
    }
}