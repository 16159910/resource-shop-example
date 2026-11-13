<?php
namespace app\common\service;

/**
 * 对外请求的地址校验，防 SSRF。
 *
 * 凡是「地址由用户/后台填写、再由服务器去请求」的场景都要先过这里，
 * 否则填个 http://127.0.0.1/... 或内网段地址，就能借服务器的身份探测内网。
 */
class UrlSecurityService
{
    /** 允许请求的公网 http(s) 地址 */
    public static function isPublicHttpUrl(string $url): bool
    {
        $url   = trim($url);
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return false;
        }
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }
        // 带用户名密码的地址（http://user:pass@host）常用于绕过校验，一律拒绝
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = $parts['host'];

        // 主机名直接写 IP 的，校验这个 IP 本身
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }

        // 域名要把所有解析结果都查一遍，任何一条落在内网就拒绝
        $ips = self::resolve($host);
        if (!$ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }
        return true;
    }

    /** 私有段、保留段、回环、链路本地一律不算公网 */
    private static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /** @return string[] A / AAAA 解析结果 */
    private static function resolve(string $host): array
    {
        $ips = [];
        $v4  = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }
        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $rec) {
                if (!empty($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }
        return array_unique($ips);
    }
}
