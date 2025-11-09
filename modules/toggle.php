<?php
// ============================================================================
// ProjectPlayCore – Modul-Toggle (Enable/Disable)
// Pfad: /backend/modules/toggle.php
// ============================================================================

declare(strict_types=1);
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../../config.php';

ppc_security_headers();
ppc_require_login();

$module  = $_POST['module']  ?? '';
$enabled = ($_POST['enabled'] ?? '') === '1';

$path = __DIR__ . '/' . basename($module) . '/module.json';

if (!is_file($path)) {
    http_response_code(404);
    exit("Modul nicht gefunden: " . e($module));
}

$json = @file_get_contents($path);
$data = json_decode($json ?: '{}', true);
if (!is_array($data)) $data = [];

$data['enabled'] = $enabled;

@file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

ppc_redirect('/backend/modules/index.php');
