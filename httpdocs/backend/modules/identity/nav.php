<?php
// ============================================================================
// Modul: identity – Navigationseintrag (für Admin-Dashboard)
// ============================================================================

if (!isset($GLOBALS['PPC_NAV'])) $GLOBALS['PPC_NAV'] = [];
$GLOBALS['PPC_NAV']['admin'][] = [
  'label' => 'Identity (KYC)',
  'href'  => '/backend/modules/identity/admin.php',
  'cap'   => 'view_admin'
];
