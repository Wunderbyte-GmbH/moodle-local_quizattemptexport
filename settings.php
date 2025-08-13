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
 * General plugin settings.
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$generateHtmlButtons = function($keyvaluelist) {
        $html = '<div id="dynamicfilename-buttons">';

        foreach ($keyvaluelist as $key => $value) {
            $html .= '<button class="btn btn-warning m-2" type="button" data-placeholder="' . $key . '">' . $value . '</button>';
        }
        $html.= '</div>';

        $html .= <<<JS
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            let dynamicfilenamebuttoncontent = document.getElementById("dynamicfilename-buttons");
            let buttons = dynamicfilenamebuttoncontent.getElementsByTagName("button");

            let textField = document.getElementById("id_s_local_quizattemptexport_dynamicfilename");

            Array.from(buttons).forEach(function(button) {
                    button.addEventListener('click', function() {

                    let placeholder = "_" + button.getAttribute('data-placeholder');
                    let cursorPosition = textField.selectionStart;
                    let valueBeforeCursor = textField.value.substring(0, cursorPosition);
                    let valueAfterCursor = textField.value.substring(cursorPosition);

                    textField.value = valueBeforeCursor + placeholder + valueAfterCursor;

                    textField.selectionStart = cursorPosition + placeholder.length;
                    textField.selectionEnd = cursorPosition + placeholder.length;
                    });
            });
        });
        </script>
        JS;

        return $html;
};

if ($hassiteconfig) { // needs this condition or there is error on login page

    $settings = new admin_settingpage('local_quizattemptexport', get_string('pluginname', 'local_quizattemptexport'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading('local_quizattemptexport/plugindesc',
            '', get_string('plugindesc', 'local_quizattemptexport'))
    );


    $settings->add(new admin_setting_configcheckbox('local_quizattemptexport/autoexport',
            get_string('setting_autoexport', 'local_quizattemptexport'),
            get_string('setting_autoexport_desc', 'local_quizattemptexport'),
            0)
    );

    $categories = core_course_category::make_categories_list();
    $settings->add(new admin_setting_configmultiselect(
            'local_quizattemptexport/catfilter',
            get_string('setting_catfilter', 'local_quizattemptexport'),
            get_string('setting_catfilter_desc', 'local_quizattemptexport'),
            [],
            $categories)
    );

    $settings->add(new admin_setting_configcheckbox('local_quizattemptexport/exportfilesystem',
            get_string('setting_exportfilesystem', 'local_quizattemptexport'),
            get_string('setting_exportfilesystem_desc', 'local_quizattemptexport'),
            0)
    );

    $pdfexportdir_default = $CFG->dataroot . '/quizattemptexport';
    $settings->add(new admin_setting_configdirectory('local_quizattemptexport/pdfexportdir',
            get_string('setting_pdfexportdir', 'local_quizattemptexport'),
            get_string('setting_pdfexportdir_desc', 'local_quizattemptexport'),
            $pdfexportdir_default)
    );

    $settings->add(new admin_setting_configtext('local_quizattemptexport/pdfgenerationtimeout',
            get_string('setting_pdfgenerationtimeout', 'local_quizattemptexport'),
            get_string('setting_pdfgenerationtimeout_desc', 'local_quizattemptexport'),
            120,
            PARAM_INT)
    );

    $settings->add(new admin_setting_configcheckbox('local_quizattemptexport/mathjaxenable',
            get_string('setting_mathjaxenable', 'local_quizattemptexport'),
            get_string('setting_mathjaxenable_desc', 'local_quizattemptexport'),
            0)
    );

    $settings->add(new admin_setting_configtext('local_quizattemptexport/mathjaxdelay',
            get_string('setting_mathjaxdelay', 'local_quizattemptexport'),
            get_string('setting_mathjaxdelay_desc', 'local_quizattemptexport'),
            10,
            PARAM_INT)
    );

    $settings->add(new admin_setting_configcheckbox('local_quizattemptexport/enrolmentkey',
        get_string('setting_enrolmentkey', 'local_quizattemptexport'),
        get_string('setting_enrolmentkey_desc', 'local_quizattemptexport'),
        0)
    );

    $settings->add(new admin_setting_heading('local_quizattemptexport/dynamicfilenameheading',
                get_string('setting_dynamicfilenameheading','local_quizattemptexport'),
                get_string('setting_dynamicfilenameheading_desc', 'local_quizattemptexport'))
    );

    $choices = array('sha256' => 'sha256');
    $settings->add(new admin_setting_configselect('local_quizattemptexport/dynamicfilenamehashalgo',
                get_string('setting_dynamicfilenamehashalgo', 'local_quizattemptexport'),
                get_string('setting_dynamicfilenamehashalgo_desc', 'local_quizattemptexport'),
                'sha256',
                $choices));

    $settings->add(new admin_setting_configtext('local_quizattemptexport/dynamicfilenamehashlength',
                get_string('setting_dynamicfilenamehashlength', 'local_quizattemptexport'),
                get_string('setting_dynamicfilenamehashlength_desc', 'local_quizattemptexport'),
                8, PARAM_INT));

    $keyvaluebuttonlist = array(
                "QUIZNAME"    => get_string('filename_quizname', 'local_quizattemptexport'),
                "USERID"      => get_string('filename_idname', 'local_quizattemptexport'),
                "USERNAME"      => get_string('filename_username', 'local_quizattemptexport'),
                "ATTEMPTID"   => get_string('filename_attemptid', 'local_quizattemptexport'),
                );

    $settings->add(new admin_setting_configtextarea('local_quizattemptexport/dynamicfilename',
            get_string('setting_dynamicfilename', 'local_quizattemptexport'),
            $generateHtmlButtons($keyvaluebuttonlist). get_string('setting_dynamicfilename_desc', 'local_quizattemptexport'),
            'QUIZNAME_USERID_ATTEMPTID',
            PARAM_RAW, $cols = '50', $rows = '2')
    );

    $settings->add(new admin_setting_heading('local_quizattemptexport/overview_heading', get_string('setting_usersattemptslist_heading', 'local_quizattemptexport'), null));

    // Show description on overview page.
    $settings->add(new admin_setting_confightmleditor('local_quizattemptexport/overview_intro',
        get_string('setting_usersattemptslist_intro', 'local_quizattemptexport', null, true),
        get_string('setting_usersattemptslist_intro_description', 'local_quizattemptexport', null, true),
        ''
    ));

    $settings->add(new admin_setting_configmultiselect('local_quizattemptexport/toplinedata',
        get_string('setting_toplinedata', 'local_quizattemptexport', null, true),
        get_string('setting_toplinedata_desc', 'local_quizattemptexport', null, true),
        ["fullname"],
        ["fullname" => "fullname", "username" => "username", "idnumber" => "idnumber"]
    ));

    // Heading: WKHTMLTOPDF binary settings.
    $settings->add(new admin_setting_heading(
        'local_quizattemptexport/wkhtmltopdf_heading',
        get_string('wkhtmltopdf_heading', 'local_quizattemptexport'),
        get_string('wkhtmltopdf_heading_desc', 'local_quizattemptexport')
    ));

    // Absolute path to wkhtmltopdf (leave empty to auto-detect in code).
    $settings->add(new admin_setting_configtext(
        'local_quizattemptexport/wkhtmltopdf_binary',
        get_string('wkhtmltopdf_binary', 'local_quizattemptexport'),
        get_string('wkhtmltopdf_binary_desc', 'local_quizattemptexport'),
        '',
        PARAM_RAW_TRIMMED // We'll still validate/escape at runtime before exec.
    ));

    // --- Diagnostics hint (admin-only page link).
    $diagurl = new moodle_url('/local/quizattemptexport/diagnostics.php');
    $settings->add(new admin_setting_heading(
        'local_quizattemptexport/diagnostics_heading',
        get_string('diagnosticsheading', 'local_quizattemptexport'),
        \html_writer::div(get_string('diagnosticsintro', 'local_quizattemptexport'))
        . \html_writer::div(
            \html_writer::link(
                $diagurl,
                get_string('diagnosticslinktext', 'local_quizattemptexport'),
                ['class' => 'btn btn-secondary']
            ),
            'mt-2'
        )
    ));
}
