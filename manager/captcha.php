<?php
require_once __DIR__ . '/../includes/manager_auth.php';

$characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$code = '';

for ($i = 0; $i < 5; $i++) {
    $code .= $characters[random_int(0, strlen($characters) - 1)];
}

$_SESSION['manager_login_captcha'] = $code;

$width = 150;
$height = 48;
$lines = '';
$dots = '';

for ($i = 0; $i < 7; $i++) {
    $lines .= sprintf(
        '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#e4ddd2" stroke-width="1"/>',
        random_int(0, $width),
        random_int(0, $height),
        random_int(0, $width),
        random_int(0, $height)
    );
}

for ($i = 0; $i < 80; $i++) {
    $dots .= sprintf(
        '<circle cx="%d" cy="%d" r="1" fill="#b83232" opacity="0.45"/>',
        random_int(0, $width),
        random_int(0, $height)
    );
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo sprintf(
    '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">
        <rect width="100%%" height="100%%" rx="8" fill="#fffaf3"/>
        %s
        %s
        <text x="50%%" y="58%%" text-anchor="middle" font-family="Arial, sans-serif" font-size="22" font-weight="700" letter-spacing="4" fill="#20242a">%s</text>
    </svg>',
    $width,
    $height,
    $width,
    $height,
    $lines,
    $dots,
    h($code)
);
