<?php
/**
 * BEAUFY — backend poptávkového formuláře (homepage #kontakt).
 * Nahrazuje Netlify Forms, které na Wedosu nefungují.
 *
 * Doručení:
 *   1) Resend API (odesílatel web@mail.tdt.cz, Reply-To = zákazník), pokud existuje
 *      klíč v _secrets/resend.php (zapisuje ho deploy workflow z GitHub secretu RESEND_API_KEY).
 *   2) Záloha: PHP mail() z hostingu Wedos.
 * Odpověď je vždy JSON {ok:bool, error?:string}. Frontend hlásí úspěch jen při ok:true.
 * Kompatibilní s PHP 7.4 i 8.x.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

const RECIPIENT  = 'info@beaufy.eu';
const FROM_EMAIL = 'BEAUFY web <web@mail.tdt.cz>';
const MAX_LEN    = 5000;

function out($ok, $error = null, $code = 200) {
    http_response_code($code);
    echo json_encode($error ? ['ok' => $ok, 'error' => $error] : ['ok' => $ok], JSON_UNESCAPED_UNICODE);
    exit;
}

function resend_key() {
    $f = __DIR__ . '/_secrets/resend.php';
    if (!is_file($f)) return null;
    $k = include $f;
    return (is_string($k) && strpos($k, 're_') === 0) ? $k : null;
}

// Diagnostika bez odeslání: /poptavka.php?selftest=1
if (isset($_GET['selftest'])) {
    echo json_encode([
        'ok'      => true,
        'php'     => PHP_VERSION,
        'curl'    => function_exists('curl_init'),
        'mail'    => function_exists('mail'),
        'resend'  => resend_key() !== null,
        'channel' => resend_key() !== null ? 'resend' : 'mail()',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'method', 405);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !preg_match('~^https://(www\.)?beaufy\.eu$~', $origin)) out(false, 'origin', 403);

if (!empty($_POST['bot-field'])) out(true);

$ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'x');
$ip  = trim(explode(',', $ip)[0]);
$rl  = sys_get_temp_dir() . '/bf_form_' . md5($ip);
$now = time();
$hits = array_filter(@json_decode((string)@file_get_contents($rl), true) ?: [], function ($t) use ($now) { return $t > $now - 3600; });
if (count($hits) >= 5) out(false, 'rate', 429);
$hits[] = $now;
@file_put_contents($rl, json_encode(array_values($hits)));

function field($k) {
    $v = trim((string)($_POST[$k] ?? ''));
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    return mb_substr($v, 0, MAX_LEN);
}
$jmeno     = field('jmeno');
$instituce = field('instituce');
$email     = field('email');
$telefon   = field('telefon');
$produkt   = field('produkt');
$zprava    = field('zprava');
$gdpr      = !empty($_POST['gdpr']);

if ($jmeno === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) out(false, 'validation', 422);
if (!$gdpr) out(false, 'gdpr', 422);
foreach ([$jmeno, $email] as $v) { if (preg_match('/[\r\n]/', $v)) out(false, 'validation', 422); }

$subject = 'Poptávka z beaufy.eu — ' . ($instituce !== '' ? $instituce : $jmeno);
$lines = [
    'Nová poptávka z formuláře na https://www.beaufy.eu/#kontakt',
    '',
    'Jméno:      ' . $jmeno,
    'Instituce:  ' . ($instituce ?: '—'),
    'E-mail:     ' . $email,
    'Telefon:    ' . ($telefon ?: '—'),
    'Produkt:    ' . ($produkt ?: '—'),
    '',
    'Zpráva:',
    $zprava ?: '—',
    '',
    '—',
    'Souhlas se zpracováním OÚ: ano',
    'Odesláno: ' . date('j. n. Y H:i') . ' · IP: ' . $ip,
];
$text = implode("\n", $lines);
$html = '<div style="font:14px/1.6 Arial,sans-serif;color:#0F172A">' . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</div>';

$sent = false;
$key = resend_key();
if ($key && function_exists('curl_init')) {
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'from'     => FROM_EMAIL,
            'to'       => [RECIPIENT],
            'reply_to' => $email,
            'subject'  => $subject,
            'text'     => $text,
            'html'     => $html,
        ], JSON_UNESCAPED_UNICODE),
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $sent = ($code >= 200 && $code < 300);
}

if (!$sent && function_exists('mail')) {
    $headers = implode("\r\n", [
        'From: BEAUFY web <info@beaufy.eu>',
        'Reply-To: ' . $email,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ]);
    $sent = @mail(RECIPIENT, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $headers);
}

$sent ? out(true) : out(false, 'send', 502);
