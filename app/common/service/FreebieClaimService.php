<?php
namespace app\common\service;

use app\common\model\Freebie;
use app\common\model\FreebieClaim;
use app\common\model\Material;
use app\common\model\Order;
use app\common\model\OrderItem;
use think\facade\Db;

/**
 * 免费资料的领取规则：条件说明、资格判断、领取、发放内容。
 * 后台列表和小程序接口共用，规则只写这一份。
 */
class FreebieClaimService
{
    /** @return int[] 方式3 需要购买的资料 ID */
    public static function needMaterialIds(Freebie|array $f): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $f['need_material_ids']))));
    }

    /** 上架的免费资料（网站首页侧栏、/free 页用），带条件说明 */
    public static function publicList(int $limit = 0): array
    {
        $query = Freebie::where('status', 1)->order('sort', 'asc')->order('id', 'desc');
        if ($limit > 0) $query->limit($limit);
        return array_map(static fn ($f) => [
            'id'             => (int) $f['id'],
            'title'          => $f['title'],
            'cover'          => (string) $f['cover'],
            'summary'        => (string) $f['summary'],
            'claim_type'     => (int) $f['claim_type'],
            'claim_count'    => (int) $f['claim_count'],
            'condition_text' => self::conditionText($f),
            // 公众号方式要在页面上展示二维码和关键词，这些本来就是公开信息
            'mp_name'        => (string) $f['mp_name'],
            'mp_qr'          => (string) $f['mp_qr'],
            'mp_keyword'     => (string) $f['mp_keyword'],
        ], $query->select()->toArray());
    }

    /** @return int[] 用户领过的免费资料 ID */
    public static function claimedIds(int $userId): array
    {
        return $userId > 0 ? array_map('intval', FreebieClaim::where('user_id', $userId)->column('freebie_id')) : [];
    }

    /** 给人看的条件说明 */
    public static function conditionText(Freebie|array $f): string
    {
        switch ((int) $f['claim_type']) {
            case Freebie::TYPE_MP:
                $name = trim((string) $f['mp_name']) ?: '我们的公众号';
                $kw   = trim((string) $f['mp_keyword']);
                return '关注公众号「' . $name . '」' . ($kw !== '' ? '，发送「' . $kw . '」自动领取' : '后领取');
            case Freebie::TYPE_BUY:
                $titles = Material::whereIn('id', self::needMaterialIds($f))->column('title');
                if (!$titles) return '购买指定资料后可领取';
                return '购买' . (count($titles) > 1 ? '以下任意一份资料' : '「' . $titles[0] . '」') . '后可领取';
            case Freebie::TYPE_ORDERS:
                return '累计 ' . (int) $f['need_order_count'] . ' 笔已支付订单后可领取';
            default:
                return '登录后直接领取';
        }
    }

    /** 已支付且有金额的订单数（0 元领取不计入） */
    public static function paidOrderCount(int $userId): int
    {
        return Order::where('user_id', $userId)->where('pay_status', 1)->where('amount', '>', 0)->count();
    }

    /** @return int[] 指定资料里用户已买的那些 */
    public static function purchasedAmong(int $userId, array $materialIds): array
    {
        if (!$userId || !$materialIds) return [];
        return array_map('intval', OrderItem::alias('i')
            ->join('order o', 'o.id = i.order_id')
            ->where('o.user_id', $userId)
            ->where('o.pay_status', 1)
            ->whereIn('i.material_id', $materialIds)
            ->distinct(true)
            ->column('i.material_id'));
    }

    public static function hasClaimed(int $userId, int $freebieId): bool
    {
        return $userId > 0 && FreebieClaim::where('user_id', $userId)->where('freebie_id', $freebieId)->count() > 0;
    }

    /**
     * 资格判断。
     * 公众号方式的资料由公众号自动回复发放，小程序拿不到用户有没有去发关键词，
     * 这里只允许用户自己「标记已领取」，用来显示状态和统计，不发放任何内容。
     * @return array{ok: bool, reason: string}
     */
    public static function check(Freebie $f, int $userId): array
    {
        $type = (int) $f->claim_type;
        if (!$userId) {
            return ['ok' => false, 'reason' => '请先登录'];
        }
        if ($type === Freebie::TYPE_BUY && !self::purchasedAmong($userId, self::needMaterialIds($f))) {
            return ['ok' => false, 'reason' => '购买指定资料后才能领取'];
        }
        if ($type === Freebie::TYPE_ORDERS) {
            $need = (int) $f->need_order_count;
            $have = self::paidOrderCount($userId);
            if ($have < $need) {
                return ['ok' => false, 'reason' => '还差 ' . ($need - $have) . ' 笔已支付订单'];
            }
        }
        return ['ok' => true, 'reason' => ''];
    }

    /** 领取：校验资格，记一条领取记录（公众号方式是用户自己标记已领取）。重复领取直接放行 */
    public function claim(Freebie $f, int $userId): void
    {
        if ((int) $f->status !== 1) {
            throw new \RuntimeException('这份资料已下架');
        }
        if (self::hasClaimed($userId, (int) $f->id)) {
            return;
        }
        $check = self::check($f, $userId);
        if (!$check['ok']) {
            throw new \RuntimeException($check['reason']);
        }
        // 公众号方式只是标记，资料在公众号那边发
        if ((int) $f->claim_type !== Freebie::TYPE_MP && !self::deliveryWays($f)) {
            throw new \RuntimeException('资料暂未上传，请联系客服');
        }
        try {
            Db::transaction(function () use ($f, $userId) {
                FreebieClaim::create(['freebie_id' => $f->id, 'user_id' => $userId]);
                Freebie::where('id', $f->id)->inc('claim_count')->update();
            });
        } catch (\think\db\exception\PDOException $e) {
            // 连点两次并发进来，唯一索引挡住了第二条，算已领取
            if (!self::hasClaimed($userId, (int) $f->id)) throw $e;
        }
    }

    /** @return string[] 配了哪些发放方式：pan / file */
    public static function deliveryWays(Freebie $f): array
    {
        $ways = [];
        if (trim((string) $f->pan_url) !== '') $ways[] = 'pan';
        if (trim((string) $f->file_path) !== '') $ways[] = 'file';
        return $ways;
    }
}
