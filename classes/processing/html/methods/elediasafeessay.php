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

namespace local_quizattemptexport\processing\html\methods;

use local_quizattemptexport\processing\html\domdocument_util;

/**
 * Postprocessing implementation for qtype_elediasafeessay
 *
 * @package		local_quizattemptexport
 * @copyright	2020 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>, 2025 Mahdi Poustini
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class elediasafeessay extends base {

    /**
     * Adaption of postprocessing for qtype_essay. Does basically the same...
     *
     * @param string $questionhtml
     * @param \mod_quiz\quiz_attempt $attempt
     * @param int $slot
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function process(string $questionhtml, \mod_quiz\quiz_attempt $attempt, int $slot): string {
        global $DB;

        // Get DOM and XPath.
        $dom = domdocument_util::initialize_domdocument($questionhtml);
        $xpath = new \DOMXPath($dom);

        // Get the textarea if any.
        $textareas = $xpath->query('//textarea');
        foreach ($textareas as $ta) {
            /** @var \DOMElement $ta */

            // Create a div to replace the textarea with.
            $newnode = $dom->createElement('div');
            $newnode->setAttribute('class', 'qtype_elediasafeessay_editor qtype_elediasafeessay_response readonly'); // Using classes from the HTML-editor display mode.

            // Create a dom fragment, append text with line breaks and append fragment to div.
            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML(nl2br($ta->textContent));
            $newnode->appendChild($fragment);

            // Replace the textarea with the new div.
            $taparent = $ta->parentNode;
            $taparent->insertBefore($newnode, $ta);
            $taparent->removeChild($ta);

            // Remove "answer" label.
            $alabel = null;
            foreach ($taparent->childNodes as $childNode) {
                /** @var \DOMElement $childNode */
                if ($childNode->tagName == 'label') {
                    $alabel = $childNode;
                    break;
                }
            }
            if ($alabel) {
                $taparent->removeChild($alabel);
            }
        }

        // Get the question attempt steps and collect the steps
        // where grading happened. Save the step and the graders
        // userid.
        $gradingsteps = [];
        $qa = $attempt->get_question_attempt($slot);
        foreach ($qa->get_step_iterator() as $key => $step) {

            if ($step->get_state() instanceof \question_state_mangrright) {
                $gradingsteps[$key] = $step->get_user_id();

            } else if ($step->get_state() instanceof \question_state_mangrpartial) {
                $gradingsteps[$key] = $step->get_user_id();

            } else if ($step->get_state() instanceof \question_state_mangrwrong) {
                $gradingsteps[$key] = $step->get_user_id();
            }
        }

        // Get the question steps table rows and the specific cells we want to edit in there and append
        // the fullname of the user that has done the grading in the relevant steps.
        $cells = $xpath->query('//div[@class="responsehistoryheader"]/table/tbody/tr/td[@class="cell c2"]');
        foreach ($cells as $key => $cell) {
            /** @var \DOMElement $cell */

            if (!empty($gradingsteps[$key])) {

                $user = $DB->get_record('user', ['id' => $gradingsteps[$key]]);
                $username = get_string('userdeleted', 'moodle');
                if (empty($user->deleted)) {
                    $username = fullname($user);
                }

                $cell->textContent = $cell->textContent . ' (' . $username . ')';
            }

        }

        // Save modified HTML and return.
        return domdocument_util::save_html($dom);
    }

    public static function get_css() : string {
        return '
            .que.elediasafeessay .answer {
                margin-top: 10px;
            }
        ';
    }
}