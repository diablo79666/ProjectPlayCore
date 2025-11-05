<?php
// ============================================================================
// ProjectPlayCore – Admin Interface (Container) – Backup-Modul
// Pfad: /backend/modules/backup/admin.php
// ============================================================================

declare(strict_types=1);
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/utils.php';
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/controller.php'; // nutzt die bestehende Logik

ppc_security_headers();
ppc_require_login();

// Weiterleitung auf Controller, damit keine doppelte Logik entsteht
$action = $_GET['action'] ?? 'about';
header("Location: /backend/modules/backup/controller.php?action=" . urlencode($action));
exit;
