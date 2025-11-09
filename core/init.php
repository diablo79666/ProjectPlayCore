<?php
// ============================================================================
// ProjectPlayCore – Core Initialisierung
// Pfad: /backend/core/init.php
// Beschreibung:
//  Lädt zentrale Komponenten (Config, Container, DB, Security, Utils)
//  und stellt sie global bereit. Mehrfache Includes sind sicher.
// ============================================================================

declare(strict_types=1);

// ----------------------------------------------------
// 1. Grundpfade definieren (mit Doppelt-Schutz)
// ----------------------------------------------------
if (!defined('PPC_BASE')) {
    define('PPC_BASE', dirname(__DIR__));          // /backend
}
if (!defined('PPC_ROOT')) {
    define('PPC_ROOT', dirname(PPC_BASE));         // /httpdocs
}
if (!defined('PPC_CORE')) {
    define('PPC_CORE', PPC_BASE . '/core');
}
if (!defined('PPC_MODULES')) {
    define('PPC_MODULES', PPC_BASE . '/modules');
}
if (!defined('PPC_TOOLS')) {
    define('PPC_TOOLS', PPC_BASE . '/tools');
}
if (!defined('PPC_DATABASE')) {
    define('PPC_DATABASE', PPC_BASE . '/database');
}
if (!defined('PPC_STORAGE')) {
    define('PPC_STORAGE', dirname(PPC_BASE) . '/storage');
}
if (!defined('PPC_LOGS')) {
    define('PPC_LOGS', PPC_STORAGE . '/logs');
}

// ----------------------------------------------------
// 2. Zentrale Core-Komponenten laden (einmalig)
// ----------------------------------------------------
require_once PPC_CORE . '/container.php';
require_once PPC_ROOT . '/config.php';
require_once PPC_DATABASE . '/db.php';
require_once PPC_CORE . '/security.php';
require_once PPC_CORE . '/utils.php';
require_once PPC_CORE . '/session.php';

// ----------------------------------------------------
// 3. Globale Container-Initialisierung
// ----------------------------------------------------
use Core\Container;

if (!Container::has('config'))   Container::set('config', $CONFIG ?? []);
if (!Container::has('db'))       Container::set('db', ppc_db());
if (!Container::has('user'))     Container::set('user', $_SESSION['ppc_user'] ?? null);
if (!Container::has('basepath')) Container::set('basepath', PPC_BASE);

// ----------------------------------------------------
// 4. Sicherheitsheader anwenden
// ----------------------------------------------------
if (function_exists('ppc_security_headers')) {
    ppc_security_headers();
}

// ----------------------------------------------------
// 5. Basisverzeichnisse sicherstellen
// ----------------------------------------------------
@mkdir(PPC_STORAGE, 0775, true);
@mkdir(PPC_LOGS, 0775, true);

// ----------------------------------------------------
// 6. Timezone setzen (Fallback auf Berlin)
// ----------------------------------------------------
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Europe/Berlin');
}
