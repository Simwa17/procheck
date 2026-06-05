<?php
/**
 * ProCheck Mailer
 * Sends HTML emails via SMTP (with STARTTLS/SSL) or PHP mail() fallback.
 * Configure via Admin → Settings → Mail / SMTP.
 */

require_once __DIR__ . '/functions.php';

class Mailer
{
    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Send an HTML email.
     * Returns true on success, false on failure (check error_log for details).
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody
    ): bool {
        $fromEmail = setting('mail_from_email', '');
        $fromName  = setting('mail_from_name', setting('company_name', 'ProCheck'));

        if (!$fromEmail) {
            error_log('ProCheck Mailer: mail_from_email is not configured in Admin → Settings.');
            return false;
        }

        $html     = self::wrapTemplate($htmlBody, $fromName);
        $text     = self::htmlToText($html);
        $boundary = 'PROCHECK_' . bin2hex(random_bytes(8));

        $mimeBody = implode("\r\n", [
            "--{$boundary}",
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 7bit',
            '',
            $text,
            '',
            "--{$boundary}",
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 7bit',
            '',
            $html,
            '',
            "--{$boundary}--",
        ]);

        $headers = [
            'From'         => self::rfc($fromName) . " <{$fromEmail}>",
            'To'           => self::rfc($toName) . " <{$toEmail}>",
            'Reply-To'     => $fromEmail,
            'Date'         => date('r'),
            'MIME-Version' => '1.0',
            'Content-Type' => "multipart/alternative; boundary=\"{$boundary}\"",
            'X-Mailer'     => 'ProCheck/1.0',
        ];

        $smtpHost = setting('smtp_host', '');
        if ($smtpHost) {
            return self::sendSmtp($fromEmail, $fromName, $toEmail, $toName, $subject, $mimeBody, $headers);
        }

        // ── PHP mail() fallback ──────────────────────────────────────────────
        $extraHeaders = '';
        foreach ($headers as $k => $v) {
            if ($k === 'To') continue; // mail() handles To separately
            $extraHeaders .= "{$k}: {$v}\r\n";
        }
        $ok = @mail($toEmail, $subject, $mimeBody, rtrim($extraHeaders));
        if (!$ok) error_log("ProCheck Mailer: mail() returned false for {$toEmail}");
        return $ok;
    }

    // ── SMTP Implementation ──────────────────────────────────────────────────

    private static function sendSmtp(
        string $fromEmail, string $fromName,
        string $toEmail,   string $toName,
        string $subject,
        string $mimeBody,
        array  $headers
    ): bool {
        $host       = setting('smtp_host');
        $port       = (int)setting('smtp_port', '587');
        $user       = setting('smtp_user', '');
        $pass       = setting('smtp_pass', '');
        $encryption = strtolower(setting('smtp_encryption', 'tls')); // tls | ssl | none

        try {
            // ── Connect ──────────────────────────────────────────────────────
            $addr = ($encryption === 'ssl') ? "ssl://{$host}" : "tcp://{$host}";
            $sock = @stream_socket_client("{$addr}:{$port}", $errno, $errstr, 15);
            if (!$sock) {
                throw new RuntimeException("Cannot connect to {$host}:{$port} — {$errstr} ({$errno})");
            }
            stream_set_timeout($sock, 15);

            self::expect($sock, 220, 'greeting');

            // ── EHLO ─────────────────────────────────────────────────────────
            self::cmd($sock, 'EHLO ' . (gethostname() ?: 'localhost'));
            self::expect($sock, 250, 'EHLO');

            // ── STARTTLS ─────────────────────────────────────────────────────
            if ($encryption === 'tls') {
                self::cmd($sock, 'STARTTLS');
                self::expect($sock, 220, 'STARTTLS');
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS negotiation failed.');
                }
                self::cmd($sock, 'EHLO ' . (gethostname() ?: 'localhost'));
                self::expect($sock, 250, 'EHLO after TLS');
            }

            // ── AUTH LOGIN ───────────────────────────────────────────────────
            if ($user !== '') {
                self::cmd($sock, 'AUTH LOGIN');
                self::expect($sock, 334, 'AUTH LOGIN');
                self::cmd($sock, base64_encode($user));
                self::expect($sock, 334, 'AUTH username');
                self::cmd($sock, base64_encode($pass));
                self::expect($sock, 235, 'AUTH password');
            }

            // ── Envelope ─────────────────────────────────────────────────────
            self::cmd($sock, "MAIL FROM:<{$fromEmail}>");
            self::expect($sock, 250, 'MAIL FROM');
            self::cmd($sock, "RCPT TO:<{$toEmail}>");
            self::expect($sock, [250, 251], 'RCPT TO');

            // ── Message data ─────────────────────────────────────────────────
            self::cmd($sock, 'DATA');
            self::expect($sock, 354, 'DATA');

            $msg = '';
            foreach ($headers as $k => $v) $msg .= "{$k}: {$v}\r\n";
            $msg .= "Subject: {$subject}\r\n\r\n";
            // Dot-stuff lines starting with '.'
            $msg .= preg_replace('/^\.$/m', '..', $mimeBody);
            fwrite($sock, $msg . "\r\n.\r\n");
            self::expect($sock, 250, 'end of DATA');

            self::cmd($sock, 'QUIT');
            fclose($sock);
            return true;

        } catch (RuntimeException $e) {
            error_log('ProCheck SMTP Error: ' . $e->getMessage());
            if (!empty($sock) && is_resource($sock)) fclose($sock);
            return false;
        }
    }

    private static function cmd($sock, string $command): void {
        fwrite($sock, $command . "\r\n");
    }

    private static function expect($sock, int|array $codes, string $label = ''): string {
        $response = '';
        while (!feof($sock)) {
            $line = fgets($sock, 512);
            if ($line === false) break;
            $response .= $line;
            // Multi-line response ends when 4th char is space
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code     = (int)substr($response, 0, 3);
        $expected = is_array($codes) ? $codes : [$codes];
        if (!in_array($code, $expected)) {
            throw new RuntimeException(
                "SMTP [{$label}] expected " . implode('|', $expected) .
                ", got {$code}: " . trim($response)
            );
        }
        return $response;
    }

    // ── Email Templates ───────────────────────────────────────────────────────

    /**
     * Wrap content in a clean, branded HTML email template.
     */
    public static function wrapTemplate(string $content, string $senderName = 'ProCheck'): string {
        $year    = date('Y');
        $company = h(setting('company_name', $senderName));
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ProCheck</title></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Segoe UI',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px">
  <!-- Header -->
  <tr><td style="background:linear-gradient(135deg,#1e3a5f,#2563eb);border-radius:10px 10px 0 0;padding:24px 32px;text-align:center">
    <span style="color:#fff;font-size:22px;font-weight:700;letter-spacing:-0.5px">&#10003; ProCheck</span>
  </td></tr>
  <!-- Body -->
  <tr><td style="background:#ffffff;padding:32px;border-radius:0 0 10px 10px;line-height:1.6;color:#1e293b;font-size:15px">
    {$content}
    <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">
    <p style="font-size:12px;color:#94a3b8;margin:0">
      &copy; {$year} {$company}. This email was sent by ProCheck, the project pricing system for Malawian developers.
    </p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
    }

    // ── Canned Email Bodies ───────────────────────────────────────────────────

    public static function verificationBody(string $name, string $link): string {
        return self::wrapTemplate(
            '<p style="margin:0 0 16px">Hi <strong>' . h($name) . '</strong>,</p>
            <p style="margin:0 0 16px">Welcome to ProCheck! Please verify your email address by clicking the button below.</p>
            <p style="text-align:center;margin:32px 0">
              <a href="' . $link . '" style="background:#2563eb;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;display:inline-block;font-size:15px">
                &#10003; Verify My Email
              </a>
            </p>
            <p style="margin:0 0 8px;color:#64748b;font-size:13px">Or copy this link into your browser:</p>
            <p style="margin:0;font-size:12px;word-break:break-all;color:#2563eb">' . $link . '</p>
            <p style="margin:16px 0 0;font-size:13px;color:#94a3b8">This link expires in 48 hours. If you did not create an account, you can safely ignore this email.</p>'
        );
    }

    public static function quoteEmailBody(array $quote, string $message, string $viewLink): string {
        $company = h(setting('company_name', 'ProCheck'));
        $items   = '';
        foreach ($quote['items'] as $item) {
            $items .= '<tr>
              <td style="padding:8px 12px;border-bottom:1px solid #e2e8f0">' . h($item['module_name']) . '</td>
              <td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;text-align:center">' . ucfirst($item['complexity']) . '</td>
              <td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;text-align:right">' . number_format($item['hours'], 1) . ' hrs</td>
              <td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600">MWK ' . number_format($item['total_mwk'], 2, '.', ',') . '</td>
            </tr>';
        }
        $total    = 'MWK ' . number_format($quote['total_mwk'], 2, '.', ',');
        $totalUSD = 'USD ' . number_format($quote['total_usd'], 2, '.', ',');

        $messageHtml = $message ? '<p style="margin:0 0 24px">' . nl2br(h($message)) . '</p>' : '';

        return self::wrapTemplate(
            '<p style="margin:0 0 8px">Hi <strong>' . h($quote['client_name'] ?? 'there') . '</strong>,</p>
            <p style="margin:0 0 24px">Please find your project quotation from <strong>' . $company . '</strong> below.</p>
            ' . $messageHtml . '
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;border-collapse:collapse;margin-bottom:24px">
              <tr style="background:#eff6ff">
                <th style="padding:10px 12px;text-align:left;font-size:13px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0">Module</th>
                <th style="padding:10px 12px;text-align:center;font-size:13px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0">Complexity</th>
                <th style="padding:10px 12px;text-align:right;font-size:13px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0">Hours</th>
                <th style="padding:10px 12px;text-align:right;font-size:13px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0">Amount</th>
              </tr>
              ' . $items . '
              <tr style="background:#f8faff">
                <td colspan="3" style="padding:12px;text-align:right;font-weight:700;font-size:16px">Total</td>
                <td style="padding:12px;text-align:right;font-weight:700;font-size:16px;color:#2563eb">' . $total . '</td>
              </tr>
              <tr><td colspan="3" style="padding:4px 12px 12px;text-align:right;color:#94a3b8;font-size:12px">≈</td>
                  <td style="padding:4px 12px 12px;text-align:right;color:#94a3b8;font-size:12px">' . $totalUSD . '</td></tr>
            </table>
            <p style="margin:0 0 8px;font-size:13px;color:#64748b">Quote Reference: <strong>' . h($quote['quote_number']) . '</strong></p>
            <p style="margin:0 0 24px;font-size:13px;color:#64748b">Valid Until: <strong>' . ($quote['valid_until'] ? date('d F Y', strtotime($quote['valid_until'])) : 'N/A') . '</strong></p>
            ' . ($quote['notes'] ? '<p style="margin:0 0 24px;font-size:13px;color:#475569;font-style:italic">' . nl2br(h($quote['notes'])) . '</p>' : '') . '
            <p style="text-align:center;margin:0 0 8px">
              <a href="' . $viewLink . '" style="background:#2563eb;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:600;display:inline-block">
                View Full Quote Online
              </a>
            </p>
            <p style="margin:8px 0 0;font-size:12px;color:#94a3b8;text-align:center">' . h(setting('quote_footer', '')) . '</p>'
        );
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    private static function htmlToText(string $html): string {
        $text = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>', '</h1>', '</h2>', '</h3>'], "\n", $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        return preg_replace('/\n{3,}/', "\n\n", trim($text));
    }

    /** RFC 2047 encode a display name that may contain non-ASCII chars */
    private static function rfc(string $name): string {
        if (preg_match('/[^\x20-\x7E]/', $name)) {
            return '=?UTF-8?B?' . base64_encode($name) . '?=';
        }
        return '"' . addslashes($name) . '"';
    }
}
