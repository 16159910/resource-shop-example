<?php
namespace app\common\service;

class WatermarkService
{
    // 尝试找可用的中文字体
    private static function findFont(): ?string
    {
        $candidates = [
            '/Library/Fonts/Arial Unicode.ttf',          // macOS
            '/System/Library/Fonts/PingFang.ttc',
            '/usr/share/fonts/truetype/arphic/uming.ttc', // Ubuntu
            '/usr/share/fonts/truetype/arphic/ukai.ttc',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc',
            '/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc',
        ];
        foreach ($candidates as $f) {
            if (file_exists($f)) return $f;
        }
        return null;
    }

    public static function addWatermark(string $sourcePath, string $destPath): bool
    {
        try {
            $text    = SystemConfigService::get('watermark_text', '资料小铺');
            $opacity = (int) SystemConfigService::get('watermark_opacity', 50);
            $alpha   = (int) round((100 - $opacity) * 127 / 100); // GD alpha: 0=opaque,127=transparent

            $info = @getimagesize($sourcePath);
            $type = $info[2] ?? IMAGETYPE_JPEG;

            $src = match($type) {
                IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
                IMAGETYPE_GIF  => @imagecreatefromgif($sourcePath),
                IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
                default        => @imagecreatefromjpeg($sourcePath),
            };
            if (!$src) throw new \RuntimeException('Cannot read image');

            imagealphablending($src, true);

            $width  = imagesx($src);
            $height = imagesy($src);
            $font   = self::findFont();
            $color  = imagecolorallocatealpha($src, 128, 128, 128, $alpha);

            $step    = 200;
            $size    = 16;
            $angle   = -30;

            if ($font) {
                for ($x = -$width; $x < $width * 2; $x += $step) {
                    for ($y = 0; $y < $height * 2; $y += $step) {
                        imagettftext($src, $size, $angle, $x, $y, $color, $font, $text);
                    }
                }
            } else {
                // 没有中文字体时降级为内置字体（ASCII）
                $asciiText = preg_replace('/[^\x00-\x7F]/', '', $text) ?: 'watermark';
                for ($x = 0; $x < $width; $x += $step) {
                    for ($y = 0; $y < $height; $y += $step) {
                        imagestring($src, 4, $x, $y, $asciiText, $color);
                    }
                }
            }

            $dir = dirname($destPath);
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $result = match($type) {
                IMAGETYPE_PNG  => imagepng($src, $destPath),
                IMAGETYPE_GIF  => imagegif($src, $destPath),
                IMAGETYPE_WEBP => imagewebp($src, $destPath, 90),
                default        => imagejpeg($src, $destPath, 90),
            };
            return (bool) $result;
        } catch (\Throwable $e) {
            \think\facade\Log::error('WatermarkService failed: ' . $e->getMessage() . ' src=' . $sourcePath);
            @copy($sourcePath, $destPath);
            return false;
        }
    }
}
