<?php
// 应用公共函数

/**
 * 生成分页 HTML（前台用）
 */
function include_pager(int $total, int $page, int $pageSize, array $params = []): string
{
    $totalPages = (int) ceil($total / $pageSize);
    if ($totalPages <= 1) return '';

    $buildUrl = function (int $p) use ($params) {
        return '?' . http_build_query(array_merge($params, ['page' => $p]));
    };

    $html = '<div class="home-pager">';
    $html .= $page > 1
        ? '<a href="' . $buildUrl($page - 1) . '"><i class="fa-solid fa-chevron-left"></i></a>'
        : '<span class="disabled"><i class="fa-solid fa-chevron-left"></i></span>';

    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);
    if ($start > 1) $html .= '<a href="' . $buildUrl(1) . '">1</a>' . ($start > 2 ? '<span>…</span>' : '');
    for ($i = $start; $i <= $end; $i++) {
        $html .= $i === $page
            ? '<span class="active">' . $i . '</span>'
            : '<a href="' . $buildUrl($i) . '">' . $i . '</a>';
    }
    if ($end < $totalPages) $html .= ($end < $totalPages - 1 ? '<span>…</span>' : '') . '<a href="' . $buildUrl($totalPages) . '">' . $totalPages . '</a>';

    $html .= $page < $totalPages
        ? '<a href="' . $buildUrl($page + 1) . '"><i class="fa-solid fa-chevron-right"></i></a>'
        : '<span class="disabled"><i class="fa-solid fa-chevron-right"></i></span>';

    $html .= '</div>';
    return $html;
}

/**
 * 时间列显示。开了 autoWriteTimestamp 的模型读出来是 'Y-m-d H:i:s' 字符串，
 * 没开的仍是时间戳，两种都得能显示。
 */
function time_label(mixed $ts, string $format = 'Y-m-d H:i'): string
{
    return date($format, is_numeric($ts) ? (int) $ts : (int) strtotime((string) $ts));
}

/**
 * 取图片的列表缩略图路径，缩略图不存在时回退到原图。
 * 模板中用法：{$item.cover|thumb}
 */
function thumb(string $path): string
{
    if ($path === '' || !str_starts_with($path, '/uploads/')) {
        return $path;
    }
    $thumbUrl = \app\common\service\ImageOptimizeService::thumbPath($path);
    return is_file(public_path() . ltrim($thumbUrl, '/')) ? $thumbUrl : $path;
}
