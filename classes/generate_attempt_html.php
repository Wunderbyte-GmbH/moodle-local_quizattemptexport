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
 *
 *
 * @package    local_quizattemptexport
 * @author     Ralf Wiederhold <ralf.wiederhold@eledia.de>, 2025 Mahdi Poustini
 * @copyright  Ralf Wiederhold 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport;

use local_quizattemptexport\processing\html\processor as html_processor;

class generate_attempt_html {
    private $page;
    private $attemptobj;
    private $attemptrec;
    private $quizrec;
    private $accessmanager;
    private $userrec;

    /**
     *
     * @param \moodle_page $page
     */
    public function __construct(\moodle_page $page) {
        $this->page = $page;
    }

    public function generate(\mod_quiz\quiz_attempt $attempt) {
        global $CFG;

        $conf = get_config('local_quizattemptexport');

        // Initialize attempt object.
        $this->initialize_attempt($attempt);

        // Prepare HTML we want to process.
        $htmltoprocess = $this->generate_attempt_output();
        [$processedhtml, $additionalcss] = html_processor::execute($htmltoprocess, $this->attemptobj);

        // Prepare template context.
        $templatecontext = new \stdClass();
        $templatecontext->content = $processedhtml;
        $templatecontext->additional_css = $additionalcss;
        $templatecontext->pdfheader = $this->report_header();

        // Check if MathJax typesetting has been enabled.
        if ($conf->mathjaxenable) {
            // Build base path to MathJax.
            $basepath = $CFG->dirroot . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'quizattemptexport' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'mathjax' . DIRECTORY_SEPARATOR . 'mathjax' . DIRECTORY_SEPARATOR;
            $osinfo = php_uname('s');
            if (false !== strpos($osinfo, 'Windows')) {
                // Within Windows environment the path requires a bit of tweaking...
                $basepath = 'file:///' . $basepath;
                $basepath = str_replace('\\', '%5C', $basepath);
            }

            $templatecontext->mathjax = new \stdClass();
            $templatecontext->mathjax->basepath = $basepath;
            $templatecontext->mathjax->jaxpath = $templatecontext->mathjax->basepath . 'MathJax.js';
        }

        // Render html.
        $renderer = $this->page->get_renderer('core');
        $output = $renderer->render_from_template('local_quizattemptexport/pdf_base', $templatecontext);

        // Process images contained in output.
        $output = html_processor::embed_images($output);

        // Return finished html output.
        return $output;
    }

    private function initialize_attempt(\mod_quiz\quiz_attempt $attempt) {
        global $DB;

        $this->attemptobj = $attempt;
        $this->attemptrec = $this->attemptobj->get_attempt();
        $this->quizrec = $this->attemptobj->get_quiz();
        $this->accessmanager = $this->attemptobj->get_access_manager(time());

        $this->userrec = $DB->get_record('user', ['id' => $this->attemptobj->get_userid()]);
    }

    /**
     * Retrieves the code used for enrolment into the assessment course
     * by the user the currently processed attempt belongs to.
     *
     * If we are not able to find a code this method returns
     * a placeholder string.
     *
     * @return string
     * @throws \dml_exception
     */
    private function get_coursecode() {
        global $DB;

        $enrolkey = 'n/a';
        $course = $this->attemptobj->get_course();
        $userid = $this->attemptobj->get_userid();

        $sql = 'SELECT ue.*
                FROM
                    {user_enrolments} ue,
                    {enrol} e
                WHERE
                    e.enrol = :enrolname
                AND
                    e.courseid = :courseid
                AND
                    ue.enrolid = e.id
                AND
                    ue.userid = :userid';
        $params = [
            'enrolname' => 'elediamultikeys',
            'courseid' => $course->id,
            'userid' => $userid,
        ];

        if ($enrolments = $DB->get_records_sql($sql, $params)) {
            $enrolment = array_shift($enrolments);

            // Make sure required plugin table exists.
            if ($tables = $DB->get_tables()) {
                if (in_array('block_eledia_multikeys', $tables)) {
                    if ($keyrec = $DB->get_record('block_eledia_multikeys', ['enrolid' => $enrolment->enrolid, 'userid' => $userid])) {
                        $enrolkey = $keyrec->code;
                    }
                }
            }
        }

        return $enrolkey;
    }

    protected function report_header() {
        $conf = get_config('local_quizattemptexport');

        // Prepare data.
        $course = $this->attemptobj->get_course();
        $quiz = $this->quizrec;
        $attemptsubmittedtime = date('d.m.Y - H:i:s', $this->attemptrec->timefinish);
        $attemptstartedtime = date('d.m.Y - H:i:s', $this->attemptrec->timestart);

        // Prepare result data.
        $marksachieved = $this->attemptobj->get_sum_marks();
        $grademultiplier = $quiz->grade / $quiz->sumgrades;
        $grademax = round($quiz->grade, 2);
        $gradeachieved = round($marksachieved * $grademultiplier, 2);
        $gradepercent = round($gradeachieved / $grademax * 100, 0);

        // Prepare result string.
        $params = [
            'grademax' => $grademax,
            'gradeachieved' => $gradeachieved,
            'gradepercent' => $gradepercent,
        ];
        $attemptresultstr = get_string('attemptresult', 'local_quizattemptexport', $params);

        // Prepare data for template.
        $templatedata = [
            'coursename' => $course->fullname,
            'quizname' => $quiz->name,
            'studentname' => fullname($this->userrec),
            'matriculationid' => $this->userrec->idnumber,
            'attemptstarted' => $attemptstartedtime,
            'attemptended' => $attemptsubmittedtime,
            'attemptresult' => $attemptresultstr,
        ];

        if ($conf->enrolmentkey) {
            $coursecode = $this->get_coursecode();
            $templatedata['coursecode'] = $coursecode;
        };

        return $templatedata;
    }

    protected function generate_attempt_output() {
        global $USER, $DB;

        // Get super admin to temporarily override global $USER so we
        // get all the nice extra information rendered in here the regular
        // user might not see.
        $mainadmin = get_admin();
        $mainadminfull = get_complete_user_data('id', $mainadmin->id);
        $olduser = $USER;
        $USER = $mainadminfull;

        // set up vars required by copypasta
        $page = 0;
        $showall = true;

        try {
            $options = $this->attemptobj->get_display_options(true);
            $options->flags = 0; // The flags attribute has to be "0".
            $options->rightanswer = \question_display_options::VISIBLE;
            $options->correctness = \question_display_options::VISIBLE;

            $slots = $this->attemptobj->get_slots();
            $headtags = $this->attemptobj->get_html_head_contributions($page, $showall);
            $this->accessmanager->setup_attempt_page($this->page);

            $this->page->set_title(format_string($this->attemptobj->get_quiz_name()));
            $this->page->set_heading($this->attemptobj->get_course()->fullname);

            /////////////////////////////////////////////////////////////////////////////////
            /////////////////////////////////////////////////////////////////////////////////
            /////////////////////////////////////////////////////////////////////////////////
            // COPYPASTA aus mod/quiz/review.php

            // Work out some time-related things.
            $overtime = 0;
            if ($this->attemptrec->timefinish) {
                if ($timetaken = ($this->attemptrec->timefinish - $this->attemptrec->timestart)) {
                    if ($this->quizrec->timelimit && $timetaken > ($this->quizrec->timelimit + 60)) {
                        $overtime = $timetaken - $this->quizrec->timelimit;
                        $overtime = format_time($overtime);
                    }
                    $timetaken = format_time($timetaken);
                } else {
                    $timetaken = "-";
                }
            } else {
                $timetaken = get_string('unfinished', 'quiz');
            }

            // Prepare summary informat about the whole attempt.
            $summarydata = [];

            // always show student-info
            // if (!$attemptobj->get_quiz()->showuserpicture && $attemptobj->get_userid() != $USER->id) {

            $student = $DB->get_record('user', ['id' => $this->attemptobj->get_userid()]);
            $usrepicture = new \user_picture($student);
            $usrepicture->courseid = $this->attemptobj->get_courseid();
            $summarydata['user'] = [
                'title' => $usrepicture,
                'content' => new \action_link(
                    new \moodle_url('/user/view.php', [
                    'id' => $student->id, 'course' => $this->attemptobj->get_courseid()]),
                    fullname($student, true)
                ),
            ];
            // }
            if ($this->attemptobj->has_capability('mod/quiz:viewreports')) {
                $attemptlist = $this->attemptobj->links_to_other_attempts($this->attemptobj->review_url(
                    null,
                    $page,
                    $showall
                ));
                if ($attemptlist) {
                    $summarydata['attemptlist'] = [
                        'title' => get_string('attempts', 'quiz'),
                        'content' => $attemptlist,
                    ];
                }
            }

            // Timing information.
            $summarydata['startedon'] = [
                'title' => get_string('startedon', 'quiz'),
                'content' => userdate($this->attemptrec->timestart),
            ];

            if ($this->attemptrec->timefinish) {
                $summarydata['completedon'] = [
                    'title' => 'Compeleted on',
                    'content' => userdate($this->attemptrec->timefinish),
                ];
                $summarydata['timetaken'] = [
                    'title' => 'time taken',
                    'content' => $timetaken,
                ];
            }

            if (!empty($overtime)) {
                $summarydata['overdue'] = [
                    'title' => get_string('overdue', 'quiz'),
                    'content' => $overtime,
                ];
            }

            // Show marks (if the user is allowed to see marks at the moment).
            $grade = quiz_rescale_grade($this->attemptrec->sumgrades, $this->quizrec, false);
            if ($options->marks >= \question_display_options::MARK_AND_MAX && quiz_has_grades($this->quizrec)) {
                if (!$this->attemptrec->timefinish) {
                    $summarydata['grade'] = [
                        'title' => 'Grade',
                        'content' => 'Attempt still in progress',
                    ];
                } else if (is_null($grade)) {
                    $summarydata['grade'] = [
                        'title' => 'Grade',
                        'content' => quiz_format_grade($this->quizrec, $grade),
                    ];
                } else {
                    // Show raw marks only if they are different from the grade (like on the view page).
                    if ($this->quizrec->grade != $this->quizrec->sumgrades) {
                        $a = new \stdClass();
                        $a->grade = quiz_format_grade($this->quizrec, $this->attemptrec->sumgrades);
                        $a->maxgrade = quiz_format_grade($this->quizrec, $this->quizrec->sumgrades);
                        $summarydata['marks'] = [
                            'title' => get_string('marks', 'quiz'),
                            'content' => get_string('outofshort', 'quiz', $a),
                        ];
                    }

                    // Now the scaled grade.
                    $a = new \stdClass();
                    $a->grade = \html_writer::tag('b', quiz_format_grade($this->quizrec, $grade));
                    $a->maxgrade = quiz_format_grade($this->quizrec, $this->quizrec->grade);
                    if ($this->quizrec->grade != 100) {
                        $a->percent = \html_writer::tag('b', format_float(
                            $this->attemptrec->sumgrades * 100 / $this->quizrec->sumgrades,
                            0
                        ));
                        $formattedgrade = get_string('outofpercent', 'quiz', $a);
                    } else {
                        $formattedgrade = get_string('outof', 'quiz', $a);
                    }
                    $summarydata['grade'] = [
                        'title' => 'Grade',
                        'content' => $formattedgrade,
                    ];
                }
            }

            // Feedback if there is any, and the user is allowed to see it now.
            $feedback = $this->attemptobj->get_overall_feedback($grade);
            if ($options->overallfeedback && $feedback) {
                $summarydata['feedback'] = [
                    'title' => get_string('feedback', 'quiz'),
                    'content' => $feedback,
                ];
            }

            $quizrenderer = $this->page->get_renderer('mod_quiz');
            // echo $quizrenderer->review_summary_table($summarydata, $page);
            $output = $quizrenderer->questions($this->attemptobj, true, $slots, $page, $showall, $options);

            // Put an empty div inside the questions box to prevent a pagebreak on this place.
            $searchpattern = '#<div id=".*?" class="que.*?</div>#';
            if (preg_match_all($searchpattern, $output, $founds)) {
                if (!empty($founds[0])) {
                    foreach ($founds[0] as $replsearch) {
                        $output = str_replace($replsearch, '</div>&nbsp;<div class="nobreak">' . $replsearch, $output);
                    }
                }
            }

            // Make the title for the correct answer strong.
            $searchpattern = '#<div class="rightanswer">(.*?:).*?</div>#';
            if (preg_match_all($searchpattern, $output, $founds)) {
                if (!empty($founds[1])) {
                    foreach ($founds[1] as $replsearch) {
                        $output = str_replace($replsearch, '<strong>' . $replsearch . '</strong>', $output);
                        break; // The result is alway the same. So we just need one replace.
                    }
                }
            }
        } finally {
            // Make sure we set global $USER back to its initial value...
            $USER = $olduser;
        }

        return $output;
    }
}
