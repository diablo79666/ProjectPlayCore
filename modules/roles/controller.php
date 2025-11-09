<?php
// ============================================================================
// ProjectPlayCore – Roles Controller
// Pfad: /backend/modules/roles/classes/RoleController.php
// ============================================================================
namespace Modules\Roles;

use Core\Container;
use Throwable;

class RoleController
{
    // ============================================================================
    // Konstruktor
    // ============================================================================
    protected RoleService $service;
    protected string $csrfCookie = 'roles_csrf';

    public function __construct()
    {
        $this->service = Container::get(RoleService::class);
    }

    // ============================================================================
    // Hauptsteuerung: Aktionen ausführen und UI laden
    // ============================================================================
    public function handle(): void
    {
        $action = strtolower($_GET['action'] ?? $_POST['action'] ?? '');
        $msg = '';
        $err = '';

        try {
            switch ($action) {
                case 'grant':        $this->grantRole(); break;
                case 'revoke':       $this->revokeRole(); break;
                case 'create_role':  $this->createRole(); break;
                case 'delete_role':  $this->deleteRole(); break;
                case 'add_cap':      $this->addCap(); break;
                case 'remove_cap':   $this->removeCap(); break;
            }
        } catch (Throwable $t) {
            $err = $t->getMessage();
        }

        include __DIR__ . '/../templates/roles.list.php';
    }

    // ============================================================================
    // CSRF-Prüfung
    // ============================================================================
    private function csrfCheck(): void
    {
        $cookie = $_COOKIE[$this->csrfCookie] ?? '';
        $posted = $_POST[$this->csrfCookie] ?? $_GET[$this->csrfCookie] ?? '';
        if (!$cookie || !$posted || !hash_equals($cookie, $posted)) {
            http_response_code(403);
            exit('CSRF-Überprüfung fehlgeschlagen.');
        }
    }

    // ============================================================================
    // Rolle zuweisen
    // ============================================================================
    private function grantRole(): void
    {
        $this->csrfCheck();
        $u = trim($_REQUEST['u'] ?? '');
        $r = trim($_REQUEST['r'] ?? '');
        $this->service->grantRole($u, $r);
    }

    // ============================================================================
    // Rolle entziehen
    // ============================================================================
    private function revokeRole(): void
    {
        $this->csrfCheck();
        $u = trim($_REQUEST['u'] ?? '');
        $r = trim($_REQUEST['r'] ?? '');
        $this->service->revokeRole($u, $r);
    }

    // ============================================================================
    // Neue Rolle anlegen
    // ============================================================================
    private function createRole(): void
    {
        $this->csrfCheck();
        $r = trim($_POST['role_new'] ?? '');
        $this->service->createRole($r);
    }

    // ============================================================================
    // Rolle löschen
    // ============================================================================
    private function deleteRole(): void
    {
        $this->csrfCheck();
        $r = trim($_POST['role_del'] ?? '');
        $this->service->deleteRole($r);
    }

    // ============================================================================
    // Capability hinzufügen
    // ============================================================================
    private function addCap(): void
    {
        $this->csrfCheck();
        $r = trim($_POST['cap_role'] ?? '');
        $c = trim($_POST['cap_name'] ?? '');
        $this->service->addCapability($r, $c);
    }

    // ============================================================================
    // Capability entfernen
    // ============================================================================
    private function removeCap(): void
    {
        $this->csrfCheck();
        $r = trim($_POST['cap_role'] ?? '');
        $c = trim($_POST['cap_name'] ?? '');
        $this->service->removeCapability($r, $c);
    }
}
