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
 * Plugin renderer.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\output;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

class renderer extends \plugin_renderer_base {

    public function render_attemptexportlist($rawdata, $cmid, $canexportagain) {
        global $DB;

        $templatedata = [
            'intro' => get_config('local_quizattemptexport', 'overview_intro'),
            'users' => [],
            'exportallurl' => $canexportagain && !empty($rawdata) ? new \moodle_url('/local/quizattemptexport/overview.php', ['cmid' => $cmid, 'exportall' => 1]) : '',
            'zipdownloadurl' => !empty($rawdata) ? new \moodle_url('/local/quizattemptexport/overview.php', ['cmid' => $cmid, 'downloadzip' => 1]) : ''
        ];
        foreach ($rawdata as $userid => $attempts) {
            $user = $DB->get_record('user', ['id' => $userid]);
            $userdata = [
                'attempts' => []
            ];
            
            $tld = get_config('local_quizattemptexport', 'toplinedata');
            $h_userinfo = "";
            $h_bracket = "";
            if (FALSE !== strpos($tld, 'fullname')) {
                $h_userinfo .= fullname($user);
            };

            if (FALSE !== strpos($tld, 'username')) {
                if ($h_userinfo == "") {
                    $h_userinfo .= $user->username;
                } else {
                    $h_bracket .= $user->username;
                }
            };

            if (FALSE !== strpos($tld, 'idnumber')) {
                if ($h_bracket != "") {
                    $h_bracket .= ", " . $user->idnumber;
                } else {
                    $h_bracket .= $user->idnumber;
                }
            };
            if ($h_bracket != "") {
                $h_userinfo .= " (" . $h_bracket . ")";
            };
            $userdata['toplineheading'] = $h_userinfo;

            foreach ($attempts as $attemptid => $filearrays) {

                $attemptobj = \quiz_attempt::create($attemptid);

                $reexporturl = null;
                if ($canexportagain) {
                    $reexporturl = new \moodle_url('/local/quizattemptexport/overview.php', ['cmid' => $attemptobj->get_cmid(), 'reexport' => $attemptid]);
                }

                $attemptdata = [
                    'timefinished' => date('d.m.Y - H:i:s', $attemptobj->get_attempt()->timefinish),
                    'files' => [],
                    'reexporturl' => $reexporturl
                ];

                foreach ($filearrays['pdfs'] as $file) {

                    /** @var \stored_file $file */

                    $filedata = [];
                    $filedata['url'] = \moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename(),
                        false
                    );
                    $filedata['name'] = $file->get_filename();
                    $filedata['timecreated'] = date('d.m.Y - H:i:s', $file->get_timecreated());

                    $attemptdata['exportfiles'][] = $filedata;
                }

                foreach ($filearrays['attachments'] as $file) {

                    /** @var \stored_file $file */

                    $filedata = [];
                    $filedata['url'] = \moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename(),
                        false
                    );
                    $filedata['name'] = $file->get_filename();
                    $filedata['timecreated'] = date('d.m.Y - H:i:s', $file->get_timecreated());

                    $attemptdata['attachmentfiles'][] = $filedata;
                }

                $userdata['attempts'][] = $attemptdata;
            }

            $templatedata['users'][] = $userdata;
        }

        return $this->render_from_template('local_quizattemptexport/usersattemptslist', $templatedata);
    }
}
