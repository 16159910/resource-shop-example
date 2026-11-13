<?php
namespace app\common\service;
class VisitService
{
    public const PLATFORM_WEB = 1;
    public static function currentPlatform(): int { return self::PLATFORM_WEB; }
}
