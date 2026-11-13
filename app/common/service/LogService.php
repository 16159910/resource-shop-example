<?php
namespace app\common\service;

use app\common\model\Log;

class LogService
{
    public static function record(string $module, string $action, string $description, int $adminId = 0): void
    {
        $log = new Log();
        $log->save([
            'admin_id'    => $adminId ?: (session('admin_id') ?? 0),
            'module'      => $module,
            'action'      => $action,
            'description' => $description,
            'ip'          => request()->ip(),
        ]);
    }
}
