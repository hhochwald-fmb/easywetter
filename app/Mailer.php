<?php
declare(strict_types=1);

/**
 * Sehr einfacher Mailversand über PHP mail() – funktioniert bei den meisten Webhostern.
 * Ist kein Absender konfiguriert, wird nichts verschickt (die Funktionen der Seite
 * hängen nicht davon ab, E-Mails sind nur ein Komfort).
 */
final class Mailer
{
    public static function enabled(): bool
    {
        return (string) config('mail_from', '') !== '' && function_exists('mail');
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        if (!self::enabled() || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $from = (string) config('mail_from');
        $headers = [
            'From: EasyWetter <' . $from . '>',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: EasyWetter',
        ];
        $subject = '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $subject)) . '?=';
        try {
            return @mail($to, $subject, $body, implode("\r\n", $headers));
        } catch (Throwable $e) {
            error_log('[EasyWetter] Mailversand fehlgeschlagen: ' . $e->getMessage());
            return false;
        }
    }

    public static function notifyAdmin(string $subject, string $body): void
    {
        $to = (string) config('admin_email', '') ?: (string) setting('admin_email', '');
        if ($to !== '') {
            self::send($to, $subject, $body);
        }
    }
}
