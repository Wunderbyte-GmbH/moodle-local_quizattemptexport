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

namespace local_quizattemptexport\output;


/**
 * Plugin renderer.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>, 2025 Mahdi Poustini
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Function render_attemptexportlist.
     * @param mixed $rawdata
     * @param mixed $cmid
     * @param mixed $canexportagain
     * @return bool|string
     */
    public function render_attemptexportlist($rawdata, $cmid, $canexportagain) {
        global $DB;

        $templatedata = [
            'intro' => get_config('local_quizattemptexport', 'overview_intro'),
            'users' => [],
            'exportallurl' => $canexportagain && !empty($rawdata) ? new \moodle_url('/local/quizattemptexport/overview.php', ['cmid' => $cmid, 'exportall' => 1]) : '',
            'zipdownloadurl' => !empty($rawdata) ? new \moodle_url('/local/quizattemptexport/overview.php', ['cmid' => $cmid, 'downloadzip' => 1]) : '',
        ];

            // Extract all user IDs from $rawdata.
            $userids = array_keys($rawdata);

            // Fetch all user records with a single query.
            $usersrecords = $DB->get_records_list('user', 'id', $userids);

        foreach ($rawdata as $userid => $attempts) {
            // Use the fetched user record from $usersrecords.
            $user = $usersrecords[$userid];
            $toplinedataconfig = get_config('local_quizattemptexport', 'toplinedata');
            $userdata = ['attempts' => []];

            // Define mappings for user information retrieval.
            $infomappings = [
                'fullname' => function ($user) {
                    return fullname($user);
                },
                'username' => function ($user) {
                    return $user->username;
                },
                'idnumber' => function ($user) {
                    return $user->idnumber;
                },
            ];

            // Extract desired user information based on the configuration.
            $parts = [];
            foreach ($infomappings as $key => $func) {
                if (strpos($toplinedataconfig, $key) !== false) {
                    $parts[] = $func($user);
                }
            }

            // Construct the top line heading.
            $userdata['toplineheading'] = array_shift($parts) . (empty($parts) ? '' : ' (' . implode(', ', $parts) . ')');

            foreach ($attempts as $attemptid => $filearrays) {
                $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);

                $reexporturl = null;
                if ($canexportagain) {
                    $reexporturl = new \moodle_url('/local/quizattemptexport/overview.php', ['cmid' => $attemptobj->get_cmid(), 'reexport' => $attemptid]);
                }

                $attemptdata = [
                    'timefinished' => date('d.m.Y - H:i:s', $attemptobj->get_attempt()->timefinish),
                    'files' => [],
                    'reexporturl' => $reexporturl,
                ];

                /** @var \stored_file $file */
                foreach ($filearrays['pdfs'] as $file) {
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

                /** @var \stored_file $file */
                foreach ($filearrays['attachments'] as $file) {
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
