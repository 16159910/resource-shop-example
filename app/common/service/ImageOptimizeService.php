<?php
namespace app\common\service;

class ImageOptimizeService
{
    /** 封面/试看图的最长边上限 */
    public const MAX_EDGE = 1200;

    /** JPEG 输出质量 */
    public const QUALITY = 85;

    /** 列表缩略图的最长边 */
    public const THUMB_EDGE = 600;

    /** 缩略图文件名后缀 */
    public const THUMB_SUFFIX = '_thumb';

    /**
     * 压缩单张图片并就地替换。
     * 不透明的图输出 JPEG，带透明通道的图保持 PNG。
     * 扩展名可能发生变化，返回新文件的绝对路径；无需处理或失败时返回 null。
     */
    public static function optimize(string $absPath, int $maxEdge = self::MAX_EDGE, int $quality = self::QUALITY): ?string
    {
        if (!is_file($absPath)) return null;

        $info = @getimagesize($absPath);
        if (!$info) return null;
        $type = $info[2];

        // 动图 GIF 用 GD 处理会只剩第一帧，直接跳过
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absPath),
            IMAGETYPE_PNG  => @imagecreatefrompng($absPath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($absPath),
            default        => null,
        };
        if (!$src) return null;

        $width    = imagesx($src);
        $height   = imagesy($src);
        $hasAlpha = $type !== IMAGETYPE_JPEG && self::hasAlpha($src, $width, $height);

        // 等比缩放到最长边以内，本来就够小就保持原尺寸（仍会重新编码压缩）
        $scale     = min(1, $maxEdge / max($width, $height));
        $newWidth  = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        if ($hasAlpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        } else {
            // JPEG 不支持透明，先铺白底再合成，避免透明区变黑
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagealphablending($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $newExt  = $hasAlpha ? 'png' : 'jpg';
        $tmpPath = $absPath . '.opt.' . $newExt;
        $ok = $hasAlpha
            ? imagepng($dst, $tmpPath, 9)
            : imagejpeg($dst, $tmpPath, $quality);

        if (!$ok || !is_file($tmpPath)) {
            @unlink($tmpPath);
            return null;
        }

        // 压不小就放弃，保留原图
        if (filesize($tmpPath) >= filesize($absPath)) {
            @unlink($tmpPath);
            return null;
        }

        $destPath = self::replaceExtension($absPath, $newExt);
        if (!@rename($tmpPath, $destPath)) {
            @unlink($tmpPath);
            return null;
        }
        // 扩展名变了，删掉原文件
        if ($destPath !== $absPath) {
            @unlink($absPath);
        }

        return $destPath;
    }

    /**
     * 为已压缩的图片生成列表用缩略图，存成同目录下的 xxx_thumb.jpg。
     * 原图本身就比缩略图还小时不生成。返回缩略图绝对路径，未生成返回 null。
     */
    public static function makeThumb(string $absPath, int $thumbEdge = self::THUMB_EDGE): ?string
    {
        if (!is_file($absPath)) return null;

        $info = @getimagesize($absPath);
        if (!$info) return null;
        if (max($info[0], $info[1]) <= $thumbEdge) return null;

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absPath),
            IMAGETYPE_PNG  => @imagecreatefrompng($absPath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($absPath),
            default        => null,
        };
        if (!$src) return null;

        $width     = imagesx($src);
        $height    = imagesy($src);
        $scale     = $thumbEdge / max($width, $height);
        $newWidth  = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagealphablending($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $thumbPath = self::thumbPath($absPath);
        if (!imagejpeg($dst, $thumbPath, self::QUALITY)) {
            @unlink($thumbPath);
            return null;
        }
        return $thumbPath;
    }

    /** 由原图路径推出缩略图路径，绝对路径和 URL 路径都适用 */
    public static function thumbPath(string $path): string
    {
        return preg_replace('/\.[^.\/]+$/', '', $path) . self::THUMB_SUFFIX . '.jpg';
    }

    /** 采样检测图片是否存在透明像素 */
    private static function hasAlpha(\GdImage $im, int $width, int $height): bool
    {
        $stepX = max(1, (int) ($width / 80));
        $stepY = max(1, (int) ($height / 80));
        for ($x = 0; $x < $width; $x += $stepX) {
            for ($y = 0; $y < $height; $y += $stepY) {
                if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) > 5) return true;
            }
        }
        return false;
    }

    /** 把路径的扩展名换成指定的，路径可以是绝对路径也可以是 URL 相对路径 */
    public static function replaceExtension(string $path, string $ext): string
    {
        return preg_replace('/\.[^.\/]+$/', '', $path) . '.' . $ext;
    }
}
