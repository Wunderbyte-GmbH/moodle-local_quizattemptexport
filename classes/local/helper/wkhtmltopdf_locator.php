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

namespace local_quizattemptexport\local\helper;

/**
 * Helper for locating a suitable wkhtmltopdf binary for the current platform.
 *
 * It prefers a plugin-bundled binary under:
 *   local/quizattemptexport/bin/{platform}/wkhtmltopdf(.exe)
 * where {platform} is one of:
 *   linux-x64, linux-arm64, linux-armhf, linux-ia32, macos-x64, macos-arm64, windows-x64, windows-ia32
 *
 * You can override the binary path via plugin config 'wkhtmltopdf_binary'.
 *
 * @package    local_quizattemptexport
 * @copyright  2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author     2025 Mahdi Poustini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wkhtmltopdf_locator {
    /**
     * Detect the current platform (OS + architecture).
     *
     * @return array{os:string, arch:string, platform:string, ext:string}
     *   - os:      'linux'|'macos'|'windows'|'other'
     *   - arch:    'x64'|'arm64'|'armhf'|'ia32'|'other'
     *   - platform: '{os}-{arch}' (e.g. 'linux-x64')
     *   - ext:     '' or '.exe'
     */
    public static function detect_platform(): array {
        $family  = PHP_OS_FAMILY; // Note: e.g. 'Linux', 'Darwin', 'Windows'.
        $machine = strtolower(php_uname('m') ?: ''); // Note: e.g. 'x86_64', 'aarch64', 'armv7l', 'i686'.

        // Normalize OS.
        if ($family === 'Windows') {
            $os = 'windows';
        } else if ($family === 'Darwin') {
            $os = 'macos';
        } else if ($family === 'Linux') {
            $os = 'linux';
        } else {
            $os = 'other';
        }

        // Prefer distro arch on Linux if available (Debian/Ubuntu/RPM).
        $arch = null;
        if ($os === 'linux') {
            // Dpkg (Debian/Ubuntu).
            $out = [];
            $code = 1;
            @exec('dpkg --print-architecture 2>/dev/null', $out, $code);
            if ($code === 0 && !empty($out[0])) {
                $dpkg = trim(strtolower($out[0]));
                if (in_array($dpkg, ['amd64', 'arm64', 'armhf', 'i386'], true)) {
                    $arch = ($dpkg === 'amd64') ? 'x64' :
                            (($dpkg === 'i386') ? 'ia32' : $dpkg);
                }
            }
            // Rpm (RHEL/Fedora/SUSE) as fallback.
            if ($arch === null) {
                $out = [];
                $code = 1;
                @exec('rpm -E %_arch 2>/dev/null', $out, $code);
                if ($code === 0 && !empty($out[0])) {
                    $rpm = trim(strtolower($out[0]));
                    if (in_array($rpm, ['x86_64', 'aarch64', 'armv7hl', 'i686'], true)) {
                        $arch = ($rpm === 'x86_64') ? 'x64' :
                                (($rpm === 'aarch64') ? 'arm64' :
                                (($rpm === 'armv7hl') ? 'armhf' : 'ia32'));
                    }
                }
            }
        }

        // Fallback: map uname -m.
        if ($arch === null) {
            if (in_array($machine, ['x86_64', 'amd64', 'x64'], true)) {
                $arch = 'x64';
            } else if (in_array($machine, ['aarch64', 'arm64', 'arm64e', 'aarch64_be'], true)) {
                $arch = 'arm64';
            } else if (in_array($machine, ['armv8l', 'armv8', 'armv7l', 'armv7', 'armhf'], true)) {
                $arch = 'armhf';
            } else if (in_array($machine, ['i686', 'i586', 'i486', 'i386'], true)) {
                $arch = 'ia32';
            } else {
                $arch = 'other';
            }
        }

        $ext = $os === 'windows' ? '.exe' : '';
        $platform = $os . '-' . $arch;

        return ['os' => $os, 'arch' => $arch, 'platform' => $platform, 'ext' => $ext];
    }

    /**
     * Try to resolve the best wkhtmltopdf binary for the current system.
     *
     * Resolution order:
     *   1) Explicit override ($overridepath or plugin config 'wkhtmltopdf_binary')
     *   2) Plugin-bundled binary in local/quizattemptexport/bin/{platform}/wkhtmltopdf(.exe)
     *   3) Common system locations (/usr/local/bin, /usr/bin, Homebrew, Windows Program Files)
     *   4) Whatever is in PATH (via `command -v wkhtmltopdf`)
     *
     * @param string|null $overridepath Absolute path to a wkhtmltopdf binary, if you want to force it.
     * @return string Absolute path to a valid binary.
     * @throws \moodle_exception If no usable binary is found.
     */
    public static function get_binary(?string $overridepath = null): string {
        global $CFG;

        // 1) Hard override (method arg) or plugin config.
        if (!empty($overridepath)) {
            if (self::is_valid_binary($overridepath)) {
                return $overridepath;
            }
        } else {
            $conf = get_config('local_quizattemptexport');
            if (!empty($conf->wkhtmltopdf_binary) && self::is_valid_binary($conf->wkhtmltopdf_binary)) {
                return $conf->wkhtmltopdf_binary;
            }
        }

        // 2) Plugin-bundled binary (+ try to chmod it if needed).
        $candidate = self::try_fix_and_get_bundled_binary();
        if ($candidate && self::is_valid_binary($candidate)) {
            return $candidate;
        }

        // 3) Common system locations (Linux/macOS/Homebrew/Windows).
        $common = [
            '/usr/local/bin/wkhtmltopdf',
            '/usr/bin/wkhtmltopdf',
            '/opt/homebrew/bin/wkhtmltopdf', // MacOS arm64 (brew).
            '/usr/local/opt/wkhtmltopdf/bin/wkhtmltopdf', // MacOS x64 (brew alt).
            'C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe',
            'C:\Program Files (x86)\wkhtmltopdf\bin\wkhtmltopdf.exe',
        ];
        foreach ($common as $path) {
            if (self::is_valid_binary($path)) {
                return $path;
            }
        }

        // 4) PATH lookup.
        $which = self::which('wkhtmltopdf');
        if ($which && self::is_valid_binary($which)) {
            return $which;
        }

        // Nothing worked.
        throw new \moodle_exception('wkhtmltopdfnotfound', 'local_quizattemptexport');
    }

    /**
     * Check that a path exists, is executable, and responds to `--version`.
     *
     * @param string $path Absolute path to the binary.
     * @return bool True if the binary is usable.
     */
    private static function is_valid_binary(string $path): bool {
        if (!$path || !is_file($path)) {
            return false;
        }
        // Hint: is_executable may not be reliable on Windows, but try anyway.
        if (stripos(PHP_OS_FAMILY, 'Windows') === false && !is_executable($path)) {
            return false;
        }

        $cmd = escapeshellarg($path) . ' --version 2>&1';
        $output = [];
        $code = 0;
        @exec($cmd, $output, $code);
        if ($code !== 0) {
            return false;
        }
        // Minimal sanity check on output.
        $joined = implode("\n", $output);
        return (bool)preg_match('/wkhtmltopdf/i', $joined);
    }

    /**
     * Best-effort PATH lookup for a command.
     *
     * @param string $cmd Command name.
     * @return string|null Absolute path or null if not found.
     */
    private static function which(string $cmd): ?string {
        $iswin = (PHP_OS_FAMILY === 'Windows');
        if ($iswin) {
        // No quoting on Windows; 'where' handles PATHEXT.
            $probe = 'where ' . $cmd;
        } else {
            $probe = 'command -v ' . escapeshellarg($cmd) . ' 2>/dev/null';
        }
        $out = [];
        $code = 0;
        @exec($probe, $out, $code);
        if ($code === 0 && !empty($out[0])) {
            return trim($out[0]);
        }
        return null;
    }

    /**
     * Ensure a wkhtmltopdf binary is executable if it lives under this plugin dir.
     *
     * Security: only operates on files inside local/quizattemptexport.
     * Limitations: will fail if PHP user doesn't own the file or lacks permissions,
     * or if the filesystem is mounted noexec, or if chmod is disabled.
     *
     * @param string $path Absolute path to the candidate binary.
     * @return bool True if file is now (or already) executable or not needed (Windows),
     *              false if we couldn't make it executable.
     */
    public static function ensure_executable_if_under_plugin(string $path): bool {
        global $CFG;

        // Windows: no chmod needed.
        if (PHP_OS_FAMILY === 'Windows') {
            return true;
        }

        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            return false;
        }

        $plugindir = realpath($CFG->dirroot . '/local/quizattemptexport');
        if ($plugindir === false) {
            return false;
        }

        // Only allow chmod for files under the plugin directory.
        $plugindir = rtrim($plugindir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($real, $plugindir) !== 0) {
            return false;
        }

        // Already executable?
        if (@is_executable($real)) {
            return true;
        }

        // Try to set 0755 (u+rwx, g+rx, o+rx).
        $ok = @chmod($real, 0755);
        clearstatcache(true, $real);
        return $ok && @is_executable($real);
    }

    /**
     * Convenience: for the auto-detected bundled binary, try to fix perms once.
     *
     * @return string|null Absolute path if a bundled binary exists (after chmod attempt), else null.
     */
    public static function try_fix_and_get_bundled_binary(): ?string {
        global $CFG;
        $plat = self::detect_platform();
        $candidate = $CFG->dirroot . '/local/quizattemptexport/bin/' . $plat['platform'] . '/wkhtmltopdf' . $plat['ext'];

        if (!is_file($candidate)) {
            return null;
        }
        // Make executable if needed (Linux/macOS).
        self::ensure_executable_if_under_plugin($candidate);
        return $candidate;
    }
}
