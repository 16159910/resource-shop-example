<?php
namespace app\home\controller;
use app\home\service\MaterialService;
use app\common\service\SystemConfigService;
class Index extends Base
{
    public function index(): \think\Response
    {
        $service = new MaterialService();
        $products = $service->getNew(12);
        return $this->view('index/index', [
            'products' => $products,
            'featured' => $products[0] ?? null,
            'resourceCount' => $service->categoryCounts()[0] ?? 0,
            'seo_title' => SystemConfigService::get('site_name', '资料小铺') . ' - 发现实用的数字资料',
        ]);
    }
}
