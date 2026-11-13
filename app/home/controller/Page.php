<?php
namespace app\home\controller;

class Page extends Base
{
    public function privacy(): \think\Response
    {
        return $this->view('page/privacy', ['seo_title' => '隐私政策']);
    }

    public function terms(): \think\Response
    {
        return $this->view('page/terms', ['seo_title' => '用户协议']);
    }
}
