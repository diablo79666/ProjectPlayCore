<?php
// ============================================================================
// Pfad: /user/index.php
// Zweck: Router für den User-Bereich (Login ↔ Dashboard)
// ============================================================================
declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/core/session.php';

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$loggedIn = isset($_SESSION['user']) && is_string($_SESSION['user']) && $_SESSION['user'] !== '';

header('Location: ' . ($loggedIn ? '/user/dashboard.php' : '/user/login.php'));
exit;
