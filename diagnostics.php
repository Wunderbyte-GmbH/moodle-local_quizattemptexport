<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_quizattemptexport
 * @copyright   2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

defined('MOODLE_INTERNAL') || die();

use local_quizattemptexport\local\helper\wkhtmltopdf_locator;

$context = \context_system::instance();

require_login();
require_capability('moodle/site:config', $context); // Admins only.

$PAGE->set_context($context);
$PAGE->set_url(new \moodle_url('/local/quizattemptexport/diagnostics.php'));
$PAGE->set_pagelayout('admin');

$title = 'Quiz Attempt Export — wkhtmltopdf diagnostics';
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2);

// Gather data.
$conf = get_config('local_quizattemptexport');
$platform = wkhtmltopdf_locator::detect_platform();

$configuredpath = trim((string)($conf->wkhtmltopdf_binary ?? ''));
$configuredexists = $configuredpath !== '' && is_file($configuredpath);
$configuredexec = $configuredexists && (PHP_OS_FAMILY === 'Windows' ? true : is_executable($configuredpath));

$bundledpath = null;
$bundledexists = false;
$bundledexec = false;
try {
    // Just compute candidate path; don't modify permissions here.
    $plat = $platform;
    $bundledpath = $CFG->dirroot . '/local/quizattemptexport/bin/' . $plat['platform'] . '/wkhtmltopdf' . $plat['ext'];
    $bundledexists = is_file($bundledpath);
    $bundledexec = $bundledexists && (PHP_OS_FAMILY === 'Windows' ? true : is_executable($bundledpath));
} catch (\Throwable $e) {
    echo 'Exception error: ' . $e->getMessage();
}

// PATH candidate (best-effort).
$pathcandidate = null;
if (PHP_OS_FAMILY === 'Windows') {
    @exec('where wkhtmltopdf', $out, $code);
    if (isset($out[0]) && $code === 0) {
        $pathcandidate = trim($out[0]);
    }
} else {
    @exec('command -v wkhtmltopdf 2>/dev/null', $out, $code);
    if (isset($out[0]) && $code === 0) {
        $pathcandidate = trim($out[0]);
    }
}
$pathexists = $pathcandidate && is_file($pathcandidate);
$pathexec = $pathexists && (PHP_OS_FAMILY === 'Windows' ? true : is_executable($pathcandidate));

// Effective binary (what locator would actually return).
$effective = null;
$effectiveerror = null;
try {
    $effective = wkhtmltopdf_locator::get_binary(null);
} catch (\moodle_exception $ex) {
    $effectiveerror = $ex->getMessage();
} catch (\Throwable $ex) {
    $effectiveerror = $ex->getMessage();
}

// Helper: format bool ✓/✗.
$yesno = function (bool $v): string {
    return $v ? '✓' : '✗';
};

// Helper: get --version (admin page only).
$versionof = function (?string $path): ?string {
    if (!$path || !is_file($path)) {
        return null;
    }
    $cmd = escapeshellarg($path) . ' --version 2>&1';
    $out = [];
    $code = 0;
    @exec($cmd, $out, $code);
    if ($code !== 0) {
        return null;
    }
    return trim(implode("\n", $out));
};

// Compute versions for the rows we’ll show.
$configuredver = $configuredexec ? $versionof($configuredpath) : null;
$bundledver    = $bundledexec ? $versionof($bundledpath) : null;
$pathver       = $pathexec ? $versionof($pathcandidate) : null;
$effectivever  = $effective ? $versionof($effective) : null;

// Render platform info.
$ptable = new \html_table();
$ptable->attributes['class'] = 'generaltable';
$ptable->head = ['Key', 'Value'];
$ptable->data = [
    ['OS family', s(PHP_OS_FAMILY)],
    ['Machine (uname -m)', s(php_uname('m'))],
    ['Detected OS', s($platform['os'])],
    ['Detected Arch', s($platform['arch'])],
    ['Platform key', s($platform['platform'])],
];

echo \html_writer::tag('h3', 'Platform');
echo \html_writer::table($ptable);

// Render configuration/detection info.
$ctable = new \html_table();
$ctable->attributes['class'] = 'generaltable';
$ctable->head = ['Item', 'Path / Status', 'Executable', 'Version'];

$ctable->data[] = [
    'Configured path (settings)',
    $configuredpath !== '' ? \html_writer::tag('code', s($configuredpath)) : \html_writer::tag('em', 'not set'),
    $configuredpath !== '' ? $yesno($configuredexec) : '',
    $configuredver ? \html_writer::tag('code', s($configuredver)) : '',
];

$ctable->data[] = [
    'Bundled candidate (by platform)',
    $bundledpath ? \html_writer::tag('code', s($bundledpath))
        . ($bundledexists ? '' : ' ' . \html_writer::tag('em', '(not found)')) : \html_writer::tag('em', 'n/a'),
    $bundledexists ? $yesno($bundledexec) : '',
    $bundledver ? \html_writer::tag('code', s($bundledver)) : '',
];

$ctable->data[] = [
    'Found in PATH',
    $pathcandidate ? \html_writer::tag('code', s($pathcandidate)) : \html_writer::tag('em', 'not found'),
    $pathcandidate ? $yesno($pathexec) : '',
    $pathver ? \html_writer::tag('code', s($pathver)) : '',
];

$ctable->data[] = [
    'Effective binary (locator)',
    $effective ? \html_writer::tag('strong', \html_writer::tag('code', s($effective))) :
        \html_writer::div(\html_writer::tag('em', 'not resolved')),
    $effective ? $yesno(PHP_OS_FAMILY === 'Windows' ? true : is_executable($effective)) : '',
    $effectivever ? \html_writer::tag('code', s($effectivever)) : '',
];

echo \html_writer::tag('h3', 'wkhtmltopdf');
echo \html_writer::table($ctable);

// If there was an error resolving the effective binary, show it.
if ($effectiveerror) {
    echo $OUTPUT->notification($effectiveerror, \core\output\notification::NOTIFY_ERROR);
}

echo $OUTPUT->footer();
