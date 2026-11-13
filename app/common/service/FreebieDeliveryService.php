<?php
namespace app\common\service;

use app\common\model\Freebie;
use app\common\model\Material;
use app\common\service\FreebieClaimService;
use app\common\service\SystemConfigService;

/**
 * 小程序免费资料：输出字段做白名单，网盘链接、文件路径只在领取后给
 */
class FreebieDeliveryService
{
    public function list(int $userId): array
    {
        $rows = Freebie::where('status', 1)->order('sort', 'asc')->order('id', 'desc')->select();
        $claimed = $userId ? \app\common\model\FreebieClaim::where('user_id', $userId)->column('freebie_id') : [];
        $list = [];
        foreach ($rows as $f) {
            $item = $this->card($f);
            $item['claimed'] = in_array($f->id, $claimed);
            $list[] = $item;
        }
        return ['list' => $list, 'service' => self::service()];
    }

    public function detail(int $id, int $userId): ?array
    {
        $f = Freebie::where('id', $id)->where('status', 1)->find();
        if (!$f) return null;

        $data = $this->card($f);
        $data['claim_tip'] = (string) $f->claim_tip;
        $data['claimed']   = FreebieClaimService::hasClaimed($userId, $id);
        $data['logged']    = $userId > 0;
        $type = (int) $f->claim_type;

        if ($type === Freebie::TYPE_MP) {
            $data['mp'] = ['name' => (string) $f->mp_name, 'qr' => (string) $f->mp_qr, 'keyword' => (string) $f->mp_keyword];
        }
        if ($type === Freebie::TYPE_BUY) {
            $ids    = FreebieClaimService::needMaterialIds($f);
            $bought = FreebieClaimService::purchasedAmong($userId, $ids);
            $data['need_materials'] = array_map(fn ($m) => [
                'id'     => (int) $m['id'],
                'title'  => $m['title'],
                'cover'  => thumb($m['cover'] ?: ''),
                'price'  => (float) $m['price'],
                'bought' => in_array((int) $m['id'], $bought, true),
            ], Material::whereIn('id', $ids)->where('status', 1)->field('id, title, cover, price')->select()->toArray());
        }
        if ($type === Freebie::TYPE_ORDERS) {
            $data['order_progress'] = [
                'have' => $userId ? FreebieClaimService::paidOrderCount($userId) : 0,
                'need' => (int) $f->need_order_count,
            ];
        }

        $check = FreebieClaimService::check($f, $userId);
        $data['can_claim'] = $check['ok'];
        $data['reason']    = $check['reason'];
        $data['delivery']  = $data['claimed'] ? $this->delivery($f) : null;
        $data['service']   = self::service();
        return $data;
    }

    /**
     * 领取，返回发放内容
     * @throws \RuntimeException
     */
    public function claim(int $id, int $userId): array
    {
        $f = Freebie::find($id);
        if (!$f) {
            throw new \RuntimeException('资料不存在');
        }
        (new FreebieClaimService())->claim($f, $userId);
        return $this->delivery($f);
    }

    /**
     * 领过的才能下载
     * @return array{0: string, 1: string} [绝对路径, 下载文件名]
     * @throws \RuntimeException
     */
    public function file(int $id, int $userId): array
    {
        $f = Freebie::find($id);
        if (!$f || !FreebieClaimService::hasClaimed($userId, $id)) {
            throw new \RuntimeException('请先领取');
        }
        return [\app\common\service\DeliveryService::resolvePath((string) $f->file_path), (string) $f->file_name];
    }

    /** 领到后的发放内容 */
    public function delivery(Freebie $f): array
    {
        $ways = FreebieClaimService::deliveryWays($f);
        $out  = ['ways' => $ways];
        if (in_array('pan', $ways, true)) {
            $out['url']  = (string) $f->pan_url;
            $out['code'] = (string) $f->pan_code;
        }
        if (in_array('file', $ways, true)) {
            $out['file_name'] = $f->file_name ?: basename((string) $f->file_path);
            $out['file_url']  = '/free/file?id=' . $f->id;
        }
        return $out;
    }

    private function card(Freebie $f): array
    {
        return [
            'id'             => (int) $f->id,
            'title'          => $f->title,
            'cover'          => (string) $f->cover,
            'summary'        => (string) $f->summary,
            'claim_type'     => (int) $f->claim_type,
            'condition_text' => FreebieClaimService::conditionText($f),
            'claim_count'    => (int) $f->claim_count,
        ];
    }

    /** 客服信息：后台「网站配置」里的客服微信和二维码 */
    public static function service(): array
    {
        return [
            'wechat' => (string) SystemConfigService::get('default_wechat', ''),
            'qr'     => (string) SystemConfigService::get('default_wechat_qr', ''),
        ];
    }
}
