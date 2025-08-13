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
 * English language strings.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['attachmentexport_filenamechunk_questionno'] = 'Question';
$string['attachmentexport_filenamechunk_attachment'] = 'Attachment';
$string['attemptresult'] = '{$a->gradeachieved} of {$a->grademax} marks ({$a->gradepercent}%)';
$string['ddimageortext_correctanswer_title'] = 'Correct answers';
$string['ddmarker_correctanswer_title'] = 'Correct answers';
$string['ddwtos_emptydrop_placeholderstr'] = '-----------------';
$string['diagnosticsheading'] = 'Diagnostics';
$string['diagnosticsintro'] =
    'Open the diagnostics page to see detected platform, configured/bundled/system binaries, executability, versions, '
    . 'and the effective binary chosen by the locator.';
$string['diagnosticslinktext'] = 'Open diagnostics (wkhtmltopdf)';

$string['except_attemptnotinquiz'] = 'The given attempt does not belong to the current quiz instance.';
$string['except_configinvalid'] = 'A setting of the plugin "local_quizattemptexport" is either missing or contains an invalid value: {$a}';
$string['except_dirmissing'] = 'Directory missing: {$a}';
$string['except_dirnotwritable'] = 'Directory is not writable: {$a}';
$string['except_usernoidnumber'] = 'User does not have an idnumber. User id: {$a}';
$string['except_usernotfound'] = 'User could not be found. User id: {$a}';
$string['label_coursename'] = 'Exam';
$string['label_quizname'] = 'Assessment';
$string['label_studentname'] = 'Student';
$string['label_matriculationid'] = 'Matriculation ID';
$string['label_coursecode'] = 'Enrolment key';
$string['label_attemptstarted'] = 'Attempt started';
$string['label_attemptended'] = 'Attempt ended';
$string['label_attemptresult'] = 'Assessment result';
$string['nav_exportoverview'] = 'Attempt export overview';
$string['page_overview_title'] = 'Exports for "{$a}"';
$string['page_overview_attemptedreexport'] = 'Attempted to export the attempt again.';
$string['page_overview_progressbar_step'] = 'Exporting attempt with id "{$a}".';
$string['page_overview_progressbar_finished'] = 'Finished exporting all attempts.';
$string['plugindesc'] = 'Automatic export of quiz attempts.';
$string['pluginname'] = 'Assessment export';
$string['quizattemptexport:viewpdf'] = 'View/download PDF';
$string['quizattemptexport:generatepdf'] = 'Export PDF';
$string['setting_autoexport'] = 'Enable automatic export';
$string['setting_autoexport_desc'] = 'Enable this setting to export each quiz attempt automatically when the user submits the attempt.';
$string['setting_catfilter'] = 'Category Filter';
$string['setting_catfilter_desc'] = 'Select the course categories where the automatic quiz attempt export should be enabled.
Only attempts of quiz instances within courses that are in a category selected here will be exported automatically.
Selecting a category will also implicitly select its subcategories.<strong>If no category is selected, all attempts in all categories are exported.</strong> <br><br> You may select multiple categories by using CTRL+Click.';
$string['setting_enrolmentkey'] = 'Include enrolment key ("One time course key enrolment")';
$string['setting_enrolmentkey_desc'] = 'Include enrolment key in the pdf report header. This works <strong>only</strong> with the plugin "One time course key enrolment"!';
$string['setting_exportfilesystem'] = 'Export into server filesystem';
$string['setting_exportfilesystem_desc'] = 'Enable this option to export PDFs into the servers filesystem as well.
        <br><br> Each submitted attempt will be exported as PDF and made available through the respective quiz instances
        administration menu where they may be downloaded individually or conveniently packed together into one ZIP archive.
        Additionally you may wish to have these files exported into a path within your servers filesystem where server processes
        like archival jobs may access these files. <br><br>Enable this option to additionally have the files be exported into the directory defined in the setting below.';
$string['setting_mathjaxenable'] = 'Enable MathJax typesetting';
$string['setting_mathjaxenable_desc'] = 'Enables typesetting of math input using MathJax';
$string['setting_mathjaxdelay'] = 'MathJax Processing Delay (seconds)';
$string['setting_mathjaxdelay_desc'] = 'Using the MathJax typesetting requires a processing delay for each PDF being generated that
        allows for MathJax to finish processing/typesetting. The default value should cover most cases. If your quiz instances contain
        a lot of math input and not all of that input was typeset, it might be necessary to set this to a higher value.';
$string['setting_pdfexportdir'] = 'Export path on server';
$string['setting_pdfexportdir_desc'] = 'This is the path of a directory within your servers filesystem where the PDFs will additionally
        be saved to if you enable the option above.';
$string['setting_pdfgenerationtimeout'] = 'Timeout for PDF generation (seconds)';
$string['setting_pdfgenerationtimeout_desc'] = 'Set the timeout in seconds that should apply for the generation of the PDF files.
        If the generation process has not finished after the given amount of time the process will be cancelled. Set a value of 0 to
        deactivate the timeout.';
$string['setting_usersattemptslist_heading'] = 'Attempt Overview page';
$string['setting_usersattemptslist_intro'] = 'Overview page intro';
$string['setting_usersattemptslist_intro_description'] = 'Here you can enter text which is shown at the top of the attempt overview page.';
$string['setting_toplinedata'] = 'Topline Table Data';
$string['setting_toplinedata_desc'] = 'Select the data to be shown on the overview page.
<br/>You may select multiple items by using CTRL+Click.';
$string['setting_dynamicfilenameheading'] = 'Dynamic Filename Settings';
$string['setting_dynamicfilenameheading_desc'] = 'All settings for the dynamic filename.';
$string['setting_dynamicfilename'] = 'Dynamic Filename';
$string['setting_dynamicfilename_desc'] = 'Enter the format for the file name or press the buttons.<br>
        You can use the following wildcards: <code>QUIZNAME, USERID, USERNAME, ATTEMPTID </code> <br>
        You can use any other text as static text. Typing errors are converted to static text. <br>
        Use <code style="font-size:150%">-</code> or <code style="font-size:120%">_</code> as separators. <br>';

$string['setting_dynamicfilenamehashalgo'] = 'Hash Algorithm';
$string['setting_dynamicfilenamehashalgo_desc'] = 'Select hash algorithm to be used in the filename of the exported pdf.';
$string['setting_dynamicfilenamehashlength'] = 'Hash Length';
$string['setting_dynamicfilenamehashlength_desc'] = 'Select the length of the hash value used in the filename of the exported pdf.';
$string['filename_quizname'] = 'Quiz Name';
$string['filename_idname'] = 'User-ID';
$string['filename_username'] = 'Username';
$string['filename_attemptid'] = 'Attempt ID';
$string['filename_slot'] = 'Attempt Slot';
$string['filename_contexthash'] = 'Context Hash';
$string['filename_filenametimestamp'] = 'File Date';

$string['task_generate_pdf_name'] = 'Generate attempt PDFs';

$string['template_usersattemptslist_attachmentexportheader'] = 'Attachments uploaded by user';
$string['template_usersattemptslist_attemptfrom'] = 'Attempt from';
$string['template_usersattemptslist_exportall'] = 'Re-export all attempts within this quiz instance';
$string['template_usersattemptslist_noattempts'] = 'Could not find any attempts for this quiz.';
$string['template_usersattemptslist_nofiles'] = 'Could not find any files for this attempt.';
$string['template_usersattemptslist_pdfexportheader'] = 'Generated PDF files';
$string['template_usersattemptslist_reexportattempttitle'] = 'Export attempt again';
$string['template_usersattemptslist_zipdownload'] = 'Download all exported files as ZIP';

$string['wkhtmltopdf_binary'] = 'wkhtmltopdf binary path';
$string['wkhtmltopdf_binary_desc'] = 'Absolute path to the wkhtmltopdf executable. '
    . 'Leave empty to auto-detect. Examples: '
    . '/usr/local/bin/wkhtmltopdf, /usr/bin/wkhtmltopdf, '
    . 'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe';

$string['wkhtmltopdf_heading'] = 'WKHTMLTOPDF binary settings';
$string['wkhtmltopdf_heading_desc'] = 'Configure how the plugin locates the wkhtmltopdf executable. '
    . 'You can specify an absolute path, or leave it empty to let the plugin auto-detect a suitable binary.';

$string['wkhtmltopdfnotfound'] =
    'wkhtmltopdf binary not found. Set the plugin setting “wkhtmltopdf binary path” '
    . 'to an absolute path, or install wkhtmltopdf and ensure it is available in the system PATH.';

$string['envcheck_execfailed'] = 'Error when trying to execute CLI call.';
$string['envcheck_notexecutable'] = 'The binary contained in the plugin needs to be executable for the webserver user. Check the readme for details.';
$string['envcheck_sharedlibsmissing'] = 'The binary is missing shared libraries: {$a}';
$string['envcheck_success'] = 'The environment check succeeded. All dependencies are met.';
$string['envcheck_winnotsupported'] = 'Windows is not supported in this minimized version of the plugin. Please install the version with the full dependencies.';
