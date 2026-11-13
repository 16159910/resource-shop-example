<?php
namespace app\admin\controller;

use app\admin\service\MaterialService;
use app\admin\service\OrderService;

class Order extends Base
{
    private OrderService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->checkLogin();
        $this->service = new OrderService();
    }

    public function index(): \think\Response
    {
        return $this->view('order/index', [
            // 供订单列表按资料筛选用
            'materials'   => (new MaterialService())->options(),
            'currentMenu' => 'order',
            'pageTitle'   => '订单管理',
        ]);
    }

    public function list(): \think\Response
    {
        $params   = input('get.');
        $page     = (int) input('get.page', 1);
        $pageSize = (int) input('get.page_size', 20);
        $result   = $this->service->list($params, $page, $pageSize);
        return $this->success($result);
    }
}
