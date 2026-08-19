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

/**
 * Postprocessing implementation for qtype_mcbonusmalus.
 *
 * The bonus-malus multiple choice question type renders the same answer
 * markup as qtype_multichoice, so the icon replacement is inherited
 * unchanged and only the CSS selectors need rescoping.
 *
 * @package     local_quizattemptexport
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mcbonusmalus extends multichoice {

    /**
     * Returns the multichoice export CSS rescoped to this question type.
     *
     * @return string
     */
    public static function get_css(): string {
        return str_replace('.multichoice', '.mcbonusmalus', parent::get_css());
    }
}
