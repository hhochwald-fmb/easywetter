<?php
declare(strict_types=1);

/**
 * Einfacher Mailversand über PHP mail() – funktioniert bei den meisten Webhostern (z. B. Mittwald).
 * Absender und Empfänger werden im Admin unter "System & Updates" eingestellt
 * (oder fest in config/config.php). Ohne Absender wird nichts verschickt –
 * die Seite funktioniert trotzdem vollständig, E-Mails sind nur ein Komfort.
 */
final class Mailer
{
    /** Absenderadresse: Einstellung im Admin hat Vorrang vor config.php. */
    public static function from(): string
    {
        $f = trim((string) setting('mail_from', '')) ?: trim((string) config('mail_from', ''));
        return filter_var($f, FILTER_VALIDATE_EMAIL) ? $f : '';
    }

    /** Empfänger für Benachrichtigungen an den Oberadmin. */
    public static function adminRecipient(): string
    {
        $to = trim((string) setting('admin_email', '')) ?: trim((string) config('admin_email', ''));
        return filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : '';
    }

    public static function enabled(): bool
    {
        return self::from() !== '' && function_exists('mail');
    }

    /** Warum werden keine E-Mails verschickt? (für die Statusanzeige) */
    public static function problem(): ?string
    {
        if (!function_exists('mail')) {
            return 'Der Server unterstützt keinen E-Mail-Versand (PHP mail() ist deaktiviert).';
        }
        if (self::from() === '') {
            return 'Es ist keine Absenderadresse eingetragen.';
        }
        if (self::adminRecipient() === '') {
            return 'Es ist keine Empfängeradresse für Benachrichtigungen eingetragen.';
        }
        return null;
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        $from = self::from();
        if ($from === '' || !function_exists('mail') || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $headers = [
            'From: EasyWetter <' . $from . '>',
            'Reply-To: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: EasyWetter/' . EW_VERSION,
        ];
        $subject = '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $subject)) . '?=';
        $body = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body));
        try {
            // Envelope-Absender setzen (bessere Zustellbarkeit); falls der Hoster das nicht erlaubt, ohne
            $ok = @mail($to, $subject, $body, implode("\r\n", $headers), '-f' . $from);
            if (!$ok) {
                $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
            }
            if (!$ok) {
                error_log('[EasyWetter] mail() hat den Versand an ' . $to . ' abgelehnt.');
            }
            return $ok;
        } catch (Throwable $e) {
            error_log('[EasyWetter] Mailversand fehlgeschlagen: ' . $e->getMessage());
            return false;
        }
    }

    public static function notifyAdmin(string $subject, string $body): bool
    {
        $to = self::adminRecipient();
        return $to !== '' && self::send($to, $subject, $body);
    }
}
