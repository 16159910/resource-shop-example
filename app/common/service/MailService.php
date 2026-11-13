<?php
namespace app\common\service;

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP 发信。配置在后台「网站配置 → 邮件设置」。
 *
 * QQ 邮箱 / 163 / 企业邮箱都支持：服务器、端口、账号照填，
 * 密码填邮箱后台生成的「授权码」，不是登录密码。
 */
class MailService
{
    public static function configured(): bool
    {
        return trim((string) SystemConfigService::get('smtp_host', '')) !== ''
            && trim((string) SystemConfigService::get('smtp_user', '')) !== ''
            && (string) SystemConfigService::get('smtp_pass', '') !== '';
    }

    /**
     * @param array<int, array{path: string, name: string}> $attachments
     * @throws \RuntimeException 发信失败，消息可直接给用户看
     */
    public static function send(string $to, string $subject, string $html, array $attachments = []): void
    {
        if (!self::configured()) {
            throw new \RuntimeException('邮件发送暂未开通，请联系客服');
        }

        $user = trim((string) SystemConfigService::get('smtp_user'));
        $port = (int) SystemConfigService::get('smtp_port', 465) ?: 465;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->CharSet    = PHPMailer::CHARSET_UTF8;
            $mail->Host       = trim((string) SystemConfigService::get('smtp_host'));
            $mail->Port       = $port;
            $mail->SMTPAuth   = true;
            $mail->Username   = $user;
            $mail->Password   = (string) SystemConfigService::get('smtp_pass');
            // 465 直接走 SSL；587/25 由 PHPMailer 自动协商 STARTTLS（服务器支持就升级加密）
            $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : '';
            $mail->Timeout    = 20;

            $fromName = trim((string) SystemConfigService::get('smtp_from_name', '')) ?: (string) SystemConfigService::get('site_name', '资料小铺');
            $mail->setFrom($user, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>', '</div>'], "\n", $html)));
            foreach ($attachments as $a) {
                $mail->addAttachment($a['path'], $a['name']);
            }
            $mail->send();
        } catch (MailerException $e) {
            // 原始报错里可能带服务器信息，只写日志，给用户一句人话
            trace('[mail] 发送到 ' . $to . ' 失败：' . $mail->ErrorInfo, 'error');
            throw new \RuntimeException('邮件发送失败，请检查邮箱地址或稍后再试');
        }
    }
}
