<?php
namespace app\admin\service;

use app\common\model\User;
use app\common\service\BaseCrudService;
use think\db\BaseQuery;

class UserService extends BaseCrudService
{
    protected function modelClass(): string
    {
        return User::class;
    }

    protected function allowFields(): array
    {
        return ['nickname', 'phone', 'email', 'status'];
    }

    protected function buildListQuery(array $params): BaseQuery
    {
        $query = User::order('id', 'desc');
        if (!empty($params['keyword'])) {
            $kw = $params['keyword'];
            // 关键词条件必须整体括起来，否则会和后面的绑定筛选串成 or，把筛选架空
            $query->where(function ($q) use ($kw) {
                $q->whereOr([
                    ['nickname', 'like', '%' . $kw . '%'],
                    ['phone', 'like', '%' . $kw . '%'],
                    ['email', 'like', '%' . $kw . '%'],
                ]);
            });
        }
        return $query;
    }

    public function createUser(array $data): User
    {
        $account = trim($data['account'] ?? '');
        if (!$account) throw new \RuntimeException('账号不能为空');

        $password = trim($data['password'] ?? '');
        if (strlen($password) < 6) throw new \RuntimeException('密码不能少于6位');

        // 手机或邮箱
        $field = str_contains($account, '@') ? 'email' : 'phone';
        if (User::where($field, $account)->find()) {
            throw new \RuntimeException('该账号已被注册');
        }

        return User::create([
            $field        => $account,
            'nickname'    => trim($data['nickname'] ?? '') ?: $account,
            'password'    => password_hash($password, PASSWORD_BCRYPT),
            'status'      => 1,
            'register_ip' => request()->ip(),
        ]);
    }

    public function updateUser(int $id, array $data): void
    {
        $user = User::find($id);
        if (!$user) throw new \RuntimeException('用户不存在');

        $save = [];
        foreach (['nickname', 'phone', 'email', 'status'] as $f) {
            if (array_key_exists($f, $data)) $save[$f] = $data[$f];
        }
        foreach (['phone' => '手机号', 'email' => '邮箱'] as $f => $label) {
            if (!empty($save[$f]) && $save[$f] != $user->$f
                && User::where($f, $save[$f])->where('id', '<>', $id)->find()) {
                throw new \RuntimeException($label . '已被其他账号使用');
            }
        }
        if ($save) $user->save($save);
    }

    public function resetPassword(int $id, string $password): void
    {
        if (strlen($password) < 6) throw new \RuntimeException('密码不能少于6位');
        $user = User::find($id);
        if (!$user) throw new \RuntimeException('用户不存在');
        $user->save(['password' => password_hash($password, PASSWORD_BCRYPT)]);
    }
}
