<?php
// Temporary, exact-path bridge while the legacy homepage/callbacks remain active.
// Installed in the legacy public directory; no request is forwarded to a remote host.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!in_array($path, ['/privacy', '/privacy-policy', '/pages/privacy-policy'], true)) {
    http_response_code(404);
    exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
$_SERVER['REQUEST_URI'] = '/pages/privacy-policy';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
require '/home/u762348670/apps/jw-corporate/public/index.php';
