<?php
header('Content-Type: application/json');
header('X-Robots-Tag: noindex');
$root = $_SERVER['DOCUMENT_ROOT'];
$ht = @file_get_contents($root . '/.htaccess');
$up = @file_get_contents(dirname($root) . '/.htaccess');
$list = @scandir($root) ?: [];
$dl = @scandir($root . '/downloads') ?: [];
echo json_encode([
  'php' => PHP_VERSION,
  'https' => $_SERVER['HTTPS'] ?? null,
  'xfp' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
  'htaccess' => $ht === false ? null : $ht,
  'parent_htaccess' => $up === false ? null : $up,
  'root' => $list,
  'downloads' => $dl,
  'parent_writable' => is_writable(dirname($root)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
