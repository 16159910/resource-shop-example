<?php
namespace app\home\controller;

use app\home\service\QuestionService;

class Question extends Base
{
    /** GET /user/questions  我的问题 */
    public function index(): \think\Response
    {
        $this->requireLogin();
        $page   = max(1, (int) input('get.page', 1));
        $result = (new QuestionService())->listByUser($this->userId, $page, 10);

        return $this->view('user/questions', [
            'result'    => $result,
            'seo_title' => '我有问题 - ' . ($this->loginUser['nickname'] ?? ''),
        ]);
    }

    /** POST /user/questions/submit  提交问题 */
    public function submit(): \think\Response
    {
        $this->requireLogin();
        try {
            (new QuestionService())->create(
                $this->userId,
                (string) input('post.content', ''),
                (string) input('post.contact', '')
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }
        return $this->success(null, '已提交，我们会尽快回复');
    }
}
