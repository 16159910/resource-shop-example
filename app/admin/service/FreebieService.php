<?php
namespace app\admin\service;

use app\common\model\Freebie;
use app\common\model\FreebieClaim;
use app\common\model\Material;
use app\common\service\BaseCrudService;
use app\common\service\FreebieClaimService;
use think\db\BaseQuery;

class FreebieService extends BaseCrudService
{
    protected function modelClass(): string
    {
        return Freebie::class;
    }

    protected function allowFields(): array
    {
        return [
            'title', 'cover', 'summary', 'claim_type', 'need_material_ids', 'need_order_count',
            'mp_name', 'mp_qr', 'mp_keyword', 'claim_tip',
            'pan_url', 'pan_code', 'file_path', 'file_name', 'file_size',
            'sort', 'status',
        ];
    }

    protected function buildListQuery(array $params): BaseQuery
    {
        return Freebie::order('sort', 'asc')->order('id', 'desc');
    }

    /** 列表：附上给人看的条件说明 */
    public function listWithCondition(): array
    {
        return array_map(function ($row) {
            $row['condition_text'] = FreebieClaimService::conditionText($row);
            $row['need_material_ids'] = FreebieClaimService::needMaterialIds($row);
            return $row;
        }, $this->all());
    }

    /** 方式3 可选的资料：上架的付费资料 */
    public function materialOptions(): array
    {
        return Material::where('status', 1)->where('price', '>', 0)->order('id', 'desc')->field('id, title')->select()->toArray();
    }

    /**
     * 按领取方式校验并整理入库数据
     * @throws \RuntimeException
     */
    public function normalize(array $data): array
    {
        if (trim((string) ($data['title'] ?? '')) === '') {
            throw new \RuntimeException('请填写标题');
        }
        $type = (int) ($data['claim_type'] ?? Freebie::TYPE_DIRECT);
        if (!in_array($type, [Freebie::TYPE_DIRECT, Freebie::TYPE_MP, Freebie::TYPE_BUY, Freebie::TYPE_ORDERS], true)) {
            throw new \RuntimeException('领取方式不正确');
        }
        $data['claim_type'] = $type;

        $ids = $data['need_material_ids'] ?? [];
        $ids = is_array($ids) ? $ids : explode(',', (string) $ids);
        $data['need_material_ids'] = implode(',', array_values(array_filter(array_map('intval', $ids))));
        $data['need_order_count']  = max(0, (int) ($data['need_order_count'] ?? 0));

        if ($type === Freebie::TYPE_MP) {
            if (trim((string) ($data['mp_qr'] ?? '')) === '' || trim((string) ($data['mp_keyword'] ?? '')) === '') {
                throw new \RuntimeException('公众号领取需要上传公众号二维码并填写关键词');
            }
            return $data;
        }
        if ($type === Freebie::TYPE_BUY && $data['need_material_ids'] === '') {
            throw new \RuntimeException('请选择需要购买的资料');
        }
        if ($type === Freebie::TYPE_ORDERS && $data['need_order_count'] < 1) {
            throw new \RuntimeException('订单数至少为 1');
        }
        if (trim((string) ($data['pan_url'] ?? '')) === '' && trim((string) ($data['file_path'] ?? '')) === '') {
            throw new \RuntimeException('请填写网盘链接或上传文件，用户领取后才拿得到资料');
        }
        return $data;
    }

    public function delete(int $id): bool
    {
        FreebieClaim::where('freebie_id', $id)->delete();
        return parent::delete($id);
    }
}
