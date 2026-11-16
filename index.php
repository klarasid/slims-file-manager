<?php
/**
 * File Manager plugin — entry point.
 *
 * Loaded by admin/plugin_container.php?mod=master_file&id=<hash>.
 */

use SlimsFileManager\{Controller, FileRepository, View};
use function SlimsFileManager\t;

// Must be included through admin/plugin_container.php, never requested directly.
defined('INDEX_AUTH') or die('Direct access is not allowed!');
if (INDEX_AUTH != 1) die('Direct access is not allowed!');

const FM_VERSION = '1.0.0';

// IP based access limitation (same rules as the Master File module)
require_once LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-masterfile');

// Admin session + login/checksum/timeout validation
require_once SB . 'admin/default/session.inc.php';
require_once SB . 'admin/default/session_check.inc.php';

foreach (['Lang', 'ValidationException', 'Icons', 'View', 'Csrf', 'Disk', 'Uploader', 'FileRepository', 'Controller'] as $fmClass) {
    require_once __DIR__ . '/src/' . $fmClass . '.php';
}
unset($fmClass);

View::setViewDir(__DIR__ . '/views');

$fmWantsJson = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' || ($_GET['fm'] ?? '') === 'biblio_search';
$fmDeny = function (string $message) use ($fmWantsJson) {
    if ($fmWantsJson) Controller::json(['ok' => false, 'message' => $message], 403);
    http_response_code(403);
    die('<div class="errorBox">' . View::e($message) . '</div>');
};

// Module privilege
$fmCanRead = utility::havePrivilege('master_file', 'r');
$fmCanWrite = utility::havePrivilege('master_file', 'w');
if (!$fmCanRead) {
    $fmDeny(t('You don\'t have enough privileges to access this area!'));
}

// Plugin menu privilege: non-super-admin users must have been granted this
// menu (System > User Group), exactly like lib/module.inc.php hides it.
if ((int)($_SESSION['uid'] ?? 0) > 1) {
    $fmMenus = $_SESSION['priv']['master_file']['menus'] ?? [];
    if (!is_array($fmMenus) || !in_array((string)($_GET['id'] ?? ''), $fmMenus, true)) {
        $fmDeny(t('You don\'t have enough privileges to access this area!'));
    }
}

(new Controller(new FileRepository(\SLiMS\DB::getInstance()), $fmCanWrite))->handle();
