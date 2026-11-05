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

namespace local_quizattemptexport\local;

/**
 * Minimal file logger for the PDF export.
 *
 * This deliberately does not implement \Psr\Log\LoggerInterface and does not use Monolog.
 * Moodle 5.2 ships Monolog 3 and Moodle 4.5 ships psr/log, and core registers its own Composer
 * autoloader on every request, so bundling either library in this plugin makes two versions of
 * the same namespace resolvable at once. Keeping the logger free of both means the class graph
 * cannot interleave, on any supported Moodle version.
 *
 * @package    local_quizattemptexport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logger {
    /** @var string Absolute path of the file entries are appended to. */
    protected $logfile;

    /**
     * Constructor.
     *
     * @param string|null $logfile Absolute path to write to, defaults to the dataroot log file.
     */
    public function __construct(?string $logfile = null) {
        global $CFG;

        $this->logfile = $logfile ?? $CFG->dataroot . '/quizattemptexport.log';
    }

    /**
     * Logs a message at ERROR level.
     *
     * @param string $message
     * @return void
     */
    public function error(string $message): void {
        $this->write('ERROR', $message);
    }

    /**
     * Logs a message at CRITICAL level.
     *
     * @param string $message
     * @param array $context Additional key/value pairs appended as JSON.
     * @return void
     */
    public function critical(string $message, array $context = []): void {
        if (!empty($context)) {
            $message .= ' ' . json_encode($context);
        }
        $this->write('CRITICAL', $message);
    }

    /**
     * Returns the file entries are written to.
     *
     * @return string
     */
    public function get_logfile(): string {
        return $this->logfile;
    }

    /**
     * Appends one entry.
     *
     * Logging must never take down an export that is otherwise succeeding, so a failure to write
     * is reported through debugging() rather than raised.
     *
     * @param string $level
     * @param string $message
     * @return void
     */
    protected function write(string $level, string $message): void {
        $entry = date('c') . " [{$level}] " . str_replace(["\r", "\n"], ' ', $message) . "\n";

        if (@file_put_contents($this->logfile, $entry, FILE_APPEND | LOCK_EX) === false) {
            debugging('Could not write to ' . $this->logfile, DEBUG_DEVELOPER);
        }
    }
}
