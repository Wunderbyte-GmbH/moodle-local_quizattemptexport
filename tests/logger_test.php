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
 * Tests for the plugin's file logger.
 *
 * @package    local_quizattemptexport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quizattemptexport\local\logger
 */
final class logger_test extends \advanced_testcase {
    /** @var string Absolute path of the log file used by a test. */
    private $logfile;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->logfile = make_temp_directory('local_quizattemptexport_test') . '/' . uniqid('log_') . '.log';
    }

    public function tearDown(): void {
        if ($this->logfile && file_exists($this->logfile)) {
            @unlink($this->logfile);
        }
        parent::tearDown();
    }

    /**
     * The logger must work without any PSR-3 class being involved.
     *
     * The plugin used to log through Monolog 2 bundled in its own vendor directory. Moodle 5.2
     * ships Monolog 3 and Moodle 4.5 ships psr/log, and core registers its own Composer
     * autoloader on every request, so two versions of those namespaces were resolvable at once
     * and the interleaved class graph fatalled. This asserts the replacement is free of that
     * coupling: it neither implements nor references a PSR-3 type, and it still logs.
     */
    public function test_logger_writes_without_psr3(): void {
        $logger = new logger($this->logfile);

        // No PSR-3 coupling: not a LoggerInterface, and nothing in its signature mentions one.
        $this->assertNotInstanceOf(\Psr\Log\LoggerInterface::class, $logger);
        $this->assertNotInstanceOf(\Psr\Log\LoggerAwareInterface::class, $logger);

        $reflection = new \ReflectionClass(logger::class);
        $this->assertSame([], $reflection->getInterfaceNames());
        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $this->assertStringNotContainsString('Psr\\Log', $type->getName());
                    $this->assertStringNotContainsString('Monolog', $type->getName());
                }
            }
        }

        // It still logs.
        $logger->error('something went wrong');
        $logger->critical('worse', ['errorcode' => 'abc', 'trace' => 'somewhere']);

        $this->assertFileExists($this->logfile);
        $contents = file_get_contents($this->logfile);

        $this->assertStringContainsString('[ERROR] something went wrong', $contents);
        $this->assertStringContainsString('[CRITICAL] worse', $contents);
        $this->assertStringContainsString('"errorcode":"abc"', $contents);
        $this->assertSame(2, substr_count($contents, "\n"), 'Each call must produce exactly one line.');
    }

    /**
     * Multi-line messages must not break the one-entry-per-line format.
     */
    public function test_newlines_are_flattened(): void {
        $logger = new logger($this->logfile);

        $logger->error("first line\nsecond line\r\nthird line");

        $contents = file_get_contents($this->logfile);
        $this->assertSame(1, substr_count($contents, "\n"));
        $this->assertStringContainsString('first line second line', $contents);
    }

    /**
     * Entries accumulate rather than overwrite, and the default target is inside dataroot.
     */
    public function test_appends_and_defaults_to_dataroot(): void {
        global $CFG;

        $logger = new logger($this->logfile);
        $logger->error('one');
        $logger->error('two');
        $this->assertSame(2, substr_count(file_get_contents($this->logfile), "\n"));

        $default = new logger();
        $this->assertSame($CFG->dataroot . '/quizattemptexport.log', $default->get_logfile());
    }

    /**
     * An unwritable target is reported through debugging() rather than raised, so that a failure
     * to log cannot take down an export that is otherwise succeeding.
     */
    public function test_write_failure_does_not_throw(): void {
        $logger = new logger('/proc/definitely/not/writable/qae.log');

        $logger->error('this cannot be written');

        $this->assertDebuggingCalled();
    }
}
