<?php
namespace app\admin\controller;

use app\admin\service\QuestionService;

class Question extends Base
{
    private QuestionService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->checkLogin();
        $this->service = new QuestionService();
    }

    public function index(): \think\Response
    {
        return $this->view('question/index', [
            'currentMenu' => 'question',
            'pageTitle'   => '用户提问',
        ]);
    }

    public function list(): \think\Response
    {
        $params   = input('get.');
        $page     = (int) input('get.page', 1);
        $pageSize = (int) input('get.page_size', 15);
        return $this->success($this->service->list($params, $page, $pageSize));
    }

    public function reply(): \think\Response
    {
        try {
            $this->service->reply(
                (int) input('post.id'),
                (string) input('post.reply', ''),
                $this->adminId
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }
        return $this->success();
    }

    public function delete(): \think\Response
    {
        $this->service->delete((int) input('post.id'));
        return $this->success();
    }
}
