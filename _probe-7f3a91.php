<?php
header('Content-Type: application/json');
header('X-Robots-Tag: noindex');
echo json_encode([
  'php' => PHP_VERSION,
  'https' => $_SERVER['HTTPS'] ?? null,
  'port' => $_SERVER['SERVER_PORT'] ?? null,
  'xfp' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
  'xfs' => $_SERVER['HTTP_X_FORWARDED_SSL'] ?? null,
  'scheme' => $_SERVER['REQUEST_SCHEME'] ?? null,
  'host' => $_SERVER['HTTP_HOST'] ?? null,
  'curl' => function_exists('curl_init'),
  'mail' => function_exists('mail'),
  'docroot' => basename($_SERVER['DOCUMENT_ROOT'] ?? ''),
]);
