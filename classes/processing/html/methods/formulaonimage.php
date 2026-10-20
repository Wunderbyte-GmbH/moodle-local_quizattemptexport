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
use qtype_formulaonimage\local\fieldtype\dropdown_field;
use qtype_formulaonimage\local\fieldtype\field_type_registry;
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
            if (self::is_calculated_field($field)) {
                $answer = isset($computed[$identifier])
                    ? number_parser::format($computed[$identifier], $question->numberformat)
                    : '';
            } else {
                // Show what the student actually typed, including input that did not parse.
                $answer = (string)($response[$identifier] ?? '');
                // A dropdown (GL-173) stores the number of the chosen option, so ask the
                // field's type for the text to show. Versions of formulaonimage without
                // dropdowns have no such method, and there the stored value is the answer.
                if (class_exists(dropdown_field::class)) {
                    $answer = field_type_registry::for_field($field)->format_response($field, $answer);
                }
            }

            $corrects = [];
            foreach ($expected[$identifier] ?? [] as $value) {
                // A text rule expects words, which are shown as the teacher wrote them.
                $corrects[] = $label . ': ' . (is_string($value)
                    ? $value
                    : number_parser::format($value, $question->numberformat));
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
     * Whether a field is auto-calculated: its value comes from a formula, not from the student.
     *
     * @param \stdClass $field Field record.
     * @return bool
     */
    protected static function is_calculated_field(\stdClass $field): bool {
        // Formulaonimage 2026100800 and later (the versions with dropdowns) has a class per
        // field type, and the calculated type is the one the student cannot fill in.
        if (class_exists(dropdown_field::class)) {
            return field_type_registry::for_field($field)->is_locked();
        }
        // Older versions only have the stored type name, which is what they checked themselves.
        return ($field->fieldtype ?? '') === 'calculated';
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
        $graded = $service->grade(
            $response,
            $question->fields,
            $question->rules,
            $question->numberformat,
            !empty($question->casesensitive)
        );
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
        $x = (int)round($field->xpos);
        $y = (int)round($field->ypos);
        $width = (int)round($field->width);
        $height = (int)round($field->height);
        imagefilledrectangle($image, $x, $y, $x + $width, $y + $height, $background);

        $font = self::font_file();
        $rows = 1 + \count($corrects);
        $rowheight = (int)round($height / $rows);
        // All rows get the same size, so the answer and its correct values look alike. The
        // text starts 3 pixels into the box, and keeps the same margin on the right.
        $fontsize = self::font_size($rowheight, $width - 6, array_merge([$value], $corrects));

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
     * Calculates the font size for the rows of a field box.
     *
     * The text grows with the box, as on the attempt page, where formulaonimage gives a field
     * about half its height as font size. There is no fixed upper limit (it used to be 14):
     * the PDF shrinks the background image to the page width, so on a large image, e.g. 4400
     * pixels wide, a fixed size ended up only a few pixels high. A text too wide for the box
     * gets a smaller size until it fits, because the PDF cannot scroll it like an input can.
     *
     * Public so it can be unit tested.
     *
     * @param int $height Row height in pixels.
     * @param int $width Width the text may use, in pixels.
     * @param string[] $texts The texts drawn in the rows; the widest one has to fit.
     * @return int Font size in points, at least 6.
     */
    public static function font_size(int $height, int $width, array $texts): int {
        // GD sizes text in points, 1 point being 1.33 pixels. So 0.35 of the row height
        // is about half of it in pixels, like on the attempt page. Never below 6.
        $size = max(6, (int)round($height * 0.35));
        // Make the widest text fit into the box, one point at a time, but not below 6.
        while ($size > 6 && self::text_width($size, $texts) > $width) {
            $size--;
        }
        return $size;
    }

    /**
     * Returns how wide the widest of the texts is at a font size.
     *
     * @param int $size Font size in points.
     * @param string[] $texts Texts to measure.
     * @return int Width in pixels.
     */
    protected static function text_width(int $size, array $texts): int {
        $widest = 0;
        foreach ($texts as $text) {
            // The corners of the text's box: [0] is the lower left x, [2] the lower right x.
            $box = imagettfbbox($size, 0, self::font_file(), $text);
            if ($box !== false) {
                $widest = max($widest, $box[2] - $box[0]);
            }
        }
        return $widest;
    }

    /**
     * Returns the font the answers are drawn with.
     *
     * @return string Path to the TTF file.
     */
    protected static function font_file(): string {
        global $CFG;
        return $CFG->dirroot . '/local/quizattemptexport/font/Open_Sans/OpenSans-Regular.ttf';
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
