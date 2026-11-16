<?php
/**
 * Plugin Name: File Manager
 * Plugin URI: https://github.com/slims/slims9_bulian
 * Description: Manajemen terpusat untuk file lampiran bibliografi (tabel files & biblio_attachment).
 * Version: 1.0.2
 * Author: Waris Agung Widodo
 * Author URI: https://github.com/idoalit
 */

// Loaded by the plugin loader from every SLiMS entry point (OPAC, admin, API),
// so only require the core bootstrap here; the page itself checks INDEX_AUTH.
defined('SB') or exit('Direct access is not allowed!');

// Menu labels: Indonesian for id_* admin locales, otherwise SLiMS translation.
$fmIndonesian = str_starts_with((string)($GLOBALS['sysconf']['default_lang'] ?? ''), 'id');

// Register the menu in the Master File module, inside its own group so the
// core "TOOLS" items are left untouched.
\SLiMS\Plugins::getInstance()
    ->registerMenu(
        'master_file',
        __('File Manager'),
        __DIR__ . '/index.php',
        $fmIndonesian ? 'Kelola file lampiran bibliografi' : __('Manage bibliography file attachments')
    )
    ->group($fmIndonesian ? 'LAMPIRAN FILE' : __('FILE ATTACHMENTS'));

unset($fmIndonesian);
