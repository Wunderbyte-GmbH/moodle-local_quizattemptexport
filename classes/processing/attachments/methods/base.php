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
 * Definition of base class for attachment processing methods.
 *
 * Each implementation handles exactly one question type and is required to be named
 * like the question type it is supposed to handle.
 *
 * A processing method might be called multiple times per processing run and should therefore
 * generally be stateless.
 *
 * @package		local_quizattemptexport
 * @copyright	2021 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\processing\attachments\methods;

use local_quizattemptexport\util;

defined('MOODLE_INTERNAL') || die();

abstract class base {

    /**
     * This method is called for a question attempt within a specific quiz attempt. It will
     * always be provided with the quiz attempt and the ID of a slot within that quiz attempt
     * that contains a question of the type the implementing class handles.
     *
     * @param \quiz_attempt $attempt
     * @param int $slot
     * @return mixed
     */
    abstract public static function process(\quiz_attempt $attempt, int $slot);


    /**
     * Takes an array of \stored_file instances and exports them into the applicable
     * filearea of the export plugin and - if enabled within the plugin settings -
     * also into the applicable directory within moodledata.
     *
     * @param \quiz_attempt $attempt
     * @param int $slot
     * @param \stored_file[] $attachments
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    protected static function export_files(\quiz_attempt $attempt, int $slot, array $attachments) {
        global $DB;

        $userid = $attempt->get_userid();
        $user = $DB->get_record('user', ['id' => $userid]);
        $cm = $attempt->get_cm();
        $instance = $DB->get_record('quiz', ['id' => $cm->instance]);
        $quizname = clean_param($instance->name, PARAM_FILE);

        $cmid = $attempt->get_cmid();
        $context = \context_module::instance($cmid);

        $exportpath = '';
        if (get_config('local_quizattemptexport', 'exportfilesystem')) {
            $exportpath = util::prepare_filearea_server($attempt);
        }

        $fs = get_file_storage();
        foreach ($attachments as $attachment) {
            $filename = self::generate_filename($quizname, $user, $attempt, $attachment, $slot);

            $newfile = new \stdClass;
            $newfile->context = $context->id;
            $newfile->component = 'local_quizattemptexport';
            $newfile->filearea = 'attemptattachments';
            $newfile->filename = $filename;
            $newfile->itemid = $attempt->get_attemptid();
            $newfile->filepath = '/';
            $newfile->userid = $userid;

            // Only write file into filesystem if it does not exist yet. (If the attachment file has the same name but not the same content anymore, e.g. since
            // the user was allowed to edit his attempt, the content hash part of the filename will make sure this is recognized as a different file...)
            if (!$fs->get_file($newfile->context, $newfile->component, $newfile->filearea, $newfile->itemid, $newfile->filepath, $newfile->filename)) {
                $fs->create_file_from_storedfile($newfile, $attachment);
            }

            // Write file into server filesystem, if the option is enabled.
            if (!empty($exportpath)) {
                $filepath = $exportpath . '/' . $filename;

                // Only write file if it does not exist already.
                if (!file_exists($filepath)) {
                    file_put_contents($filepath, $attachment->get_content());
                }
            }
        }
    }

    /**
     * Generates a filename for a given attempt.
     * 
     */
    protected static function generate_filename($quizname, $user, $attempt, $attachment, $slot) {
        // Get the format from the settings.
        $format = get_config('local_quizattemptexport', 'dynamicfilename');
        $hashtype = get_config('local_quizattemptexport', 'dynamicfilenamehashalgo');
        $hashlength = get_config('local_quizattemptexport', 'dynamicfilenamehashlength');
        
        // Get the Values for the wildcards.
        $fnamechunkquestion = get_string('attachmentexport_filenamechunk_questionno', 'local_quizattemptexport');
        $fnamechunkattachment = get_string('attachmentexport_filenamechunk_attachment', 'local_quizattemptexport');
        $userid = isset($user->idnumber) ? $user->idnumber : '';
        $username = isset($user->username) ? $user->username : '';
        $attemptid = is_object($attempt) ? $attempt->get_attemptid() : '';
        $oldfilename = is_object($attachment) ? $attachment->get_filename() : '';
        $filenameparts = explode('.', $oldfilename);
        $filetype = array_pop($filenameparts);
        $filenamepart = implode('.', $filenameparts);
        $contenthash = (is_object($attachment) && isset($hashtype)) ? hash($hashtype, $attachment->get_content()) : '';
        $convertedquizname = isset($quizname) ? clean_param(\core_text::convert($quizname, 'utf-8', 'ascii'), PARAM_ALPHANUMEXT) : '';
        
        // Replace the wildcards with the actual values.
        $replacements = [
            'QUIZNAME'    => $convertedquizname,
            'USERID'      => $userid,
            'USERNAME'    => $username,
            'ATTEMPTID'   => $attemptid,
            'FNAMECHUNKQUESTION'  => $fnamechunkquestion,
            'FNAMECHUNKQATTACHMENT'  => $fnamechunkattachment,
            'FILENAMEPART'   => $filenamepart,
            'SLOT'        => $slot,
            'CONTENTHASH' => substr($contenthash, 0, $hashlength),
        ];
    
        foreach ($replacements as $wildcard => $value) {
            $format = str_replace( $wildcard, $value, $format);
        }

        // Remove consecutive underscores for missing values
        $format = preg_replace('/_+/', '_', $format);
        // Replace invalid characters in the file name.
        $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $format);
    
        return $filename . "." . $filetype;
    }

}
