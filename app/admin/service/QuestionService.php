<?php
namespace app\admin\service;

use app\common\model\Question;
use app\common\service\BaseCrudService;
use app\common\service\LogService;
use think\db\BaseQuery;

class QuestionService extends BaseCrudService
{
    protected function modelClass(): string
    {
        return Question::class;
    }

    protected function allowFields(): array
    {
        return ['reply', 'status'];
    }

    protected function buildListQuery(array $params): BaseQuery
    {
        // 待回复的排前面，让处理顺序自然
        $query = Question::with(['user'])->order('status', 'asc')->order('id', 'desc');

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }
        if (!empty($params['keyword'])) {
            $query->whereLike('content', '%' . $params['keyword'] . '%');
        }
        return $query;
    }

    /** 回复提问 */
    public function reply(int $id, string $reply, int $adminId): void
    {
        $reply = trim($reply);
        if ($reply === '') {
            throw new \RuntimeException('回复内容不能为空');
        }

        $q = Question::findOrFail($id);
        $q->allowField(['reply', 'status', 'admin_id', 'reply_time'])->save([
            'reply'      => $reply,
            'status'     => 1,
            'admin_id'   => $adminId,
            'reply_time' => time(),
        ]);

        LogService::record('用户提问', '回复', '回复了提问 #' . $id);
    }

    public function pendingCount(): int
    {
        return Question::where('status', 0)->count();
    }
}
