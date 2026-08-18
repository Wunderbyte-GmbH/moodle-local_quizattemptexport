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
use qtype_formulaonimage\local\helper\number_parser;
use qtype_formulaonimage\local\services\calculator;
use qtype_formulaonimage\local\services\grading_service;

/**
 * Postprocessing implementation for qtype_formulaonimage.
 *
 * @package    local_quizattemptexport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class formulaonimage extends base {
    /**
     * Replaces the interactive field overlay with a flattened image.
     *
     * @param string $questionhtml Rendered question HTML.
     * @param \mod_quiz\quiz_attempt $attempt Quiz attempt.
     * @param int $slot Question slot.
     * @return string
     */
    public static function process(string $questionhtml, \mod_quiz\quiz_attempt $attempt, int $slot): string {
        $qa = $attempt->get_question_attempt($slot);
        $question = $qa->get_question();
        if (!$question instanceof \qtype_formulaonimage_question) {
            return $questionhtml;
        }
        $response = $qa->get_last_qt_data();

        $imagecontent = self::generate_image($question, $response);

        $dom = domdocument_util::initialize_domdocument($questionhtml);
        $xpath = new \DOMXPath($dom);
        $areas = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " formulaonimage-area ")]');
        foreach ($areas as $area) {
            $img = $dom->createElement('img');
            $img->setAttribute('src', 'data:image/png;base64,' . base64_encode($imagecontent));
            $img->setAttribute('class', 'formulaonimage-flattened');
            while ($area->firstChild) {
                $area->removeChild($area->firstChild);
            }
            $area->appendChild($img);
        }

        return domdocument_util::save_html($dom);
    }

    /**
     * Gets CSS for the flattened image.
     *
     * @return string
     */
    public static function get_css(): string {
        return '.formulaonimage-flattened { max-width: 100%; height: auto; }';
    }

    /**
     * Generates a flattened image with response values.
     *
     * @param \qtype_formulaonimage_question $question Question instance.
     * @param array $response Response data.
     * @return string PNG image content.
     */
    public static function generate_image(\qtype_formulaonimage_question $question, array $response): string {
        $file = self::get_background_file($question);
        $image = imagecreatefromstring($file->get_content());
        $black = imagecolorallocate($image, 0, 0, 0);
        $green = imagecolorallocate($image, 8, 95, 56);
        $white = imagecolorallocatealpha($image, 255, 255, 255, 35);

        foreach (self::field_texts($question, $response) as $identifier => $texts) {
            self::draw_field(
                $image,
                $question->fields[$identifier],
                $texts['answer'],
                $texts['corrects'],
                $black,
                $green,
                $white
            );
        }

        ob_start();
        imagepng($image);
        $content = ob_get_clean();
        // No imagedestroy(): GdImage is an object and is freed by the garbage collector.
        // The function has had no effect since PHP 8.0.
        return $content;
    }

    /**
     * Returns the text to draw into each field box.
     *
     * Kept separate from the drawing so it can be unit tested without GD.
     *
     * @param \qtype_formulaonimage_question $question Question instance.
     * @param array $response Response data.
     * @return array Map of field identifier to ['answer' => string, 'corrects' => string[]].
     */
    public static function field_texts(\qtype_formulaonimage_question $question, array $response): array {
        $service = new grading_service();
        // Auto-calculated fields are never part of the response, so their value has to be
        // recomputed from the student's inputs, exactly as the question renderer does.
        $computed = $service->student_values($response, $question->fields, $question->rules, $question->numberformat);

        $expected = [];
        $label = get_string('formulaonimage_correctlabel', 'local_quizattemptexport');
        if (!empty($question->showcorrectinpdf)) {
            $expected = self::expected_values($question, $response);
        }

        $texts = [];
        foreach ($question->fields as $identifier => $field) {
            if (calculator::is_calculated($field)) {
                $answer = isset($computed[$identifier])
                    ? number_parser::format($computed[$identifier], $question->numberformat)
                    : '';
            } else {
                // Show what the student actually typed, including input that did not parse.
                $answer = (string)($response[$identifier] ?? '');
            }

            $corrects = [];
            foreach ($expected[$identifier] ?? [] as $value) {
                $corrects[] = $label . ': ' . number_parser::format($value, $question->numberformat);
            }

            $texts[$identifier] = [
                'answer' => $answer,
                // Two rules on the same field can expect the same value; show it once.
                'corrects' => array_values(array_unique($corrects)),
            ];
        }
        return $texts;
    }

    /**
     * Returns the value each rule expected, so the PDF shows what grading compared against.
     *
     * This mirrors grading_service::grade(): a normal field is expected to satisfy its formula
     * for the values the student entered, while an auto-calculated field is expected to match
     * the answer key. When the student's input was invalid the rule could not be evaluated at
     * all, so the answer key is used as a fallback - that is the case where a corrector most
     * needs to see the correct value.
     *
     * @param \qtype_formulaonimage_question $question Question instance.
     * @param array $response Response data.
     * @return array Map of field identifier to a list of expected values, one per rule.
     */
    protected static function expected_values(\qtype_formulaonimage_question $question, array $response): array {
        $service = new grading_service();
        $graded = $service->grade($response, $question->fields, $question->rules, $question->numberformat);
        $answerkey = $service->correct_values($question->rules);

        $values = [];
        foreach ($graded->rules as $detail) {
            $target = $detail->rule->targetfield;
            if (!isset($question->fields[$target])) {
                continue;
            }
            $value = $detail->expected ?? ($answerkey[$target] ?? null);
            if ($value === null) {
                continue;
            }
            $values[$target][] = $value;
        }
        return $values;
    }

    /**
     * Draws a field box, with the student's answer and, if given, the correct value(s) below it.
     *
     * @param resource|\GdImage $image Image resource.
     * @param \stdClass $field Field definition.
     * @param string $value Student's answer.
     * @param string[] $corrects Formatted correct values for this field, one per rule.
     * @param int $color Text color for the student's answer.
     * @param int $correctcolor Text color for the correct value(s).
     * @param int $background Background color.
     * @return void
     */
    protected static function draw_field(
        $image,
        \stdClass $field,
        string $value,
        array $corrects,
        int $color,
        int $correctcolor,
        int $background
    ): void {
        global $CFG;

        $x = (int)round($field->xpos);
        $y = (int)round($field->ypos);
        $width = (int)round($field->width);
        $height = (int)round($field->height);
        imagefilledrectangle($image, $x, $y, $x + $width, $y + $height, $background);

        $font = $CFG->dirroot . '/local/quizattemptexport/font/Open_Sans/OpenSans-Regular.ttf';
        $rows = 1 + \count($corrects);
        $rowheight = (int)round($height / $rows);
        $fontsize = self::font_size($rowheight);

        self::draw_text($image, $font, $fontsize, $x + 3, $y, $rowheight, $value, $color);
        foreach (array_values($corrects) as $i => $text) {
            $rowy = $y + ($i + 1) * $rowheight;
            self::draw_text($image, $font, $fontsize, $x + 3, $rowy, $rowheight, $text, $correctcolor);
        }
    }

    /**
     * Draws text vertically centred within a row of the given height.
     *
     * @param resource|\GdImage $image Image resource.
     * @param string $font Path to the TTF font file.
     * @param int $fontsize Font size.
     * @param int $x Left position.
     * @param int $y Top position of the row.
     * @param int $height Height of the row.
     * @param string $text Text to draw.
     * @param int $color Text color.
     * @return void
     */
    protected static function draw_text(
        $image,
        string $font,
        int $fontsize,
        int $x,
        int $y,
        int $height,
        string $text,
        int $color
    ): void {
        $texty = $y + (int)round(($height + $fontsize) / 2);
        imagettftext($image, $fontsize, 0, $x, $texty, $color, $font, $text);
    }

    /**
     * Calculates a font size that fits within a row of the given height.
     *
     * @param int $height Row height in pixels.
     * @return int
     */
    protected static function font_size(int $height): int {
        return max(6, min(14, (int)round($height * 0.35)));
    }

    /**
     * Gets the background image file.
     *
     * @param \qtype_formulaonimage_question $question Question instance.
     * @return \stored_file
     */
    protected static function get_background_file(\qtype_formulaonimage_question $question): \stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $question->contextid,
            'qtype_formulaonimage',
            'bgimage',
            $question->id,
            'id',
            false
        );
        return reset($files);
    }
}
