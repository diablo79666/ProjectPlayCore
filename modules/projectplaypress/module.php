// ============================================================================
// ProjectPlayCore – ProjectPlayPress (CMS für Seitenverwaltung)
// Pfad: /backend/modules/projectplaypress/Module.php
// ============================================================================

// ---------------------------------------------------------------------------
// Modul-Informationen
// Diese Daten werden vom Core-Loader automatisch erkannt und verarbeitet.
// ---------------------------------------------------------------------------

$ModuleInfo = [
    'name'        => 'ProjectPlayPress',
    'description' => 'CMS-System zur Verwaltung von Seiten, Templates und Inhalten.',
    'author'      => 'ProjectPlayCore Team',
    'version'     => '1.0.0',
    'autoload'    => true,
    'menu' => [
        'title'      => 'Seitenverwaltung',
        'icon'       => 'fa-file-alt',
        'route'      => '/admin/projectplaypress',
        'permission' => 'cms.manage'
    ]
];

// ---------------------------------------------------------------------------
// Funktion: ProjectPlayPress_Init
// Wird beim Laden des Moduls automatisch aufgerufen.
// Hier werden interne Controller, Models und Wartungsstatus initialisiert.
// ---------------------------------------------------------------------------

function ProjectPlayPress_Init() {

    // Controller & Models laden
    require_once __DIR__ . '/Loader.php';
    require_once __DIR__ . '/Controller.php';
    require_once __DIR__ . '/Page.php';
    require_once __DIR__ . '/Router.php';

    // Wartungsmodus prüfen
    ProjectPlayPress_CheckMaintenance();
}

// ---------------------------------------------------------------------------
// Funktion: ProjectPlayPress_CheckMaintenance
// Prüft, ob der Wartungsmodus aktiviert ist.
// Wenn aktiv, wird die Wartungsseite automatisch angezeigt.
// ---------------------------------------------------------------------------

function ProjectPlayPress_CheckMaintenance() {
    $maintenanceFile = __DIR__ . '/maintenance.flag';

    if (file_exists($maintenanceFile)) {
        // Falls aktiv, einfache Maintenance-Seite ausgeben
        if (!defined('PROJECTPLAYPRESS_MAINTENANCE')) {
            define('PROJECTPLAYPRESS_MAINTENANCE', true);
        }

        if (!isset($_GET['admin'])) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>Wartungsmodus</title>';
            echo '<style>body{font-family:sans-serif;text-align:center;margin-top:10%;color:#444;}h1{font-size:2em;color:#222;}</style>';
            echo '</head><body><h1>Wartungsmodus aktiv</h1>';
            echo '<p>Die Website befindet sich derzeit im Wartungsmodus.<br>Bitte versuchen Sie es später erneut.</p></body></html>';
            exit;
        }
    }
}

// ---------------------------------------------------------------------------
// Funktion: ProjectPlayPress_ToggleMaintenance
// Aktiviert oder deaktiviert den Wartungsmodus über das Admin-Panel.
// ---------------------------------------------------------------------------

function ProjectPlayPress_ToggleMaintenance($enable = false) {
    $maintenanceFile = __DIR__ . '/maintenance.flag';

    if ($enable) {
        file_put_contents($maintenanceFile, date('Y-m-d H:i:s'));
    } else {
        if (file_exists($maintenanceFile)) {
            unlink($maintenanceFile);
        }
    }

    return $enable;
}
