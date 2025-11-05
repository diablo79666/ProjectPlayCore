<?php
// PPC quick debug wrapper for the profiles controller
declare(strict_types=1);

// Show all errors on screen for this request
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
@ini_set('html_errors', '1');
error_reporting(E_ALL);

// Safety headers
header('Cache-Control: no-store, max-age=0');
header('X-Debug-Page: profiles_debug');

// Load minimal runtime (same stack as controller)
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../../config.php';

// Now include the real controller so any fatal/syntax error prints here
require_once __DIR__ . '/../modules/profiles/controller.php';
