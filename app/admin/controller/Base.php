<?php
namespace app\admin\controller;

use app\common\service\SystemConfigService;
use think\App;
use think\exception\HttpResponseException;

class Base
{
    protected App $app;
    protected int $adminId = 0;
    protected array $adminInfo = [];

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->initialize();
    }

    protected function initialize(): void
    {
        $this->adminId   = (int) session('admin_id');
        $this->adminInfo = session('admin_info') ?? [];
        // 统一在基类拦截，新增控制器不必再手写 checkLogin；
        // 登录页控制器覆盖 initialize() 跳过这里
        $this->checkLogin();
    }

    protected function checkLogin(): void
    {
        if (!$this->adminId) {
            throw new HttpResponseException(redirect('/admin/login'));
        }
    }

    protected function success(mixed $data = null, string $msg = 'ok'): \think\Response
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    protected function error(string $msg = '操作失败', int $code = 1): \think\Response
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => null]);
    }

    protected function view(string $template, array $vars = []): \think\Response
    {
        $siteConfig = SystemConfigService::getAll();
        $vars['siteConfig']   = $siteConfig;
        $vars['adminInfo']    = $this->adminInfo;
        $vars['currentMenu']  = $vars['currentMenu'] ?? '';
        $vars['pageTitle']    = $vars['pageTitle'] ?? '';
        $vars['breadcrumb']   = $vars['breadcrumb'] ?? [];
        return view($template, $vars);
    }
}
