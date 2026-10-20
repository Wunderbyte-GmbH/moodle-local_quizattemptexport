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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Tests for the formulaonimage PDF export postprocessing.
 *
 * @package    local_quizattemptexport
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quizattemptexport\processing\html\methods\formulaonimage
 */
final class formulaonimage_test extends \advanced_testcase {
    /**
     * Skips the whole class when the question type is not installed.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        if (!\question_bank::is_qtype_installed('formulaonimage')) {
            $this->markTestSkipped('qtype_formulaonimage is not installed.');
        }
        $this->resetAfterTest();
    }

    /**
     * Returns the "Correct" label the export prefixes correct values with.
     *
     * @return string
     */
    protected function label(): string {
        return get_string('formulaonimage_correctlabel', 'local_quizattemptexport');
    }

    /**
     * Tests the student's answers are drawn, keyed by the field identifier.
     *
     * Regression test: the fields were looked up as $field->name, which no longer
     * exists since the column was renamed to "identifier", so the flattened image
     * came out empty.
     *
     * @return void
     */
    public function test_field_texts_contain_the_students_answers(): void {
        /** @var \qtype_formulaonimage_question $question */
        $question = \test_question_maker::make_question('formulaonimage', 'sum');

        $texts = formulaonimage::field_texts($question, ['a' => '10', 'b' => '5', 'total' => '15', 'tax' => '3']);

        $this->assertSame(['a', 'b', 'total', 'tax'], array_keys($texts));
        $this->assertSame('10', $texts['a']['answer']);
        $this->assertSame('5', $texts['b']['answer']);
        $this->assertSame('15', $texts['total']['answer']);
        $this->assertSame('3', $texts['tax']['answer']);
    }

    /**
     * Tests unparseable input is still shown as the student typed it.
     *
     * @return void
     */
    public function test_field_texts_show_invalid_input_verbatim(): void {
        /** @var \qtype_formulaonimage_question $question */
        $question = \test_question_maker::make_question('formulaonimage', 'sum');

        $texts = formulaonimage::field_texts($question, ['a' => '10', 'b' => '5', 'total' => '15', 'tax' => 'abc']);

        $this->assertSame('abc', $texts['tax']['answer']);
    }

    /**
     * Tests no correct values are added while the question option is off.
     *
     * @return void
     */
    public function test_correct_values_are_omitted_when_the_option_is_off(): void {
        /** @var \qtype_formulaonimage_question $question */
        $question = \test_question_maker::make_question('formulaonimage', 'sum');
        $this->assertEmpty($question->showcorrectinpdf);

        $texts = formulaonimage::field_texts($question, ['a' => '10', 'b' => '5', 'total' => '15', 'tax' => '3']);

        foreach ($texts as $identifier => $text) {
            $this->assertSame([], $text['corrects'], "Field {$identifier} should have no correct value");
        }
    }

    /**
     * Tests the correct value each rule expected is drawn when the option is on.
     *
     * @return void
     */
    public function test_correct_values_are_added_when_the_option_is_on(): void {
        /** @var \qtype_formulaonimage_question $question */
        $question = \test_question_maker::make_question('formulaonimage', 'sum');
        $question->showcorrectinpdf = true;

        // The student got "total" wrong: 10 + 5 is 15, not 99.
        $texts = formulaonimage::field_texts($question, ['a' => '10', 'b' => '5', 'total' => '99', 'tax' => '3']);

        $this->assertSame([$this->label() . ': 15'], $texts['total']['corrects']);
        // Rule 2 is tax = total * 0.2, evaluated with the total the student entered.
        $this->assertSame([$this->label() . ': 19,8'], $texts['tax']['corrects']);
        // Rule 3 is b = total - a.
        $this->assertSame([$this->label() . ': 89'], $texts['b']['corrects']);
        // No rule targets "a", so there is nothing to show for it.
        $this->assertSame([], $texts['a']['corrects']);
    }

    /**
     * Tests correct values are produced for every supported function, not just sqrt and pow.
     *
     * Regression test: the check for "are all fields of this expression available"
     * skipped a hardcoded list of only sqrt and pow, so every other function name
     * (tan, round, min, ...) was mistaken for an unknown field and the correct
     * value was silently dropped.
     *
     * @dataProvider function_expression_provider
     * @param string $expression Rule expression for the "total" field.
     * @param string $expected Expected formatted correct value.
     * @return void
     */
    public function test_correct_values_support_every_function(string $expression, string $expected): void {
        /** @var \qtype_formulaonimage_question $question */
        $question = \test_question_maker::make_question('formulaonimage', 'sum');
        $question->showcorrectinpdf = true;
        $question->rules = [
            (object)[
                'id' => 1, 'no' => 1, 'targetfield' => 'total', 'expression' => $expression,
                'grade' => 1, 'penalty' => 0, 'tolerance' => 0.01,
            ],
        ];

        $texts = formulaonimage::field_texts($question, ['a' => '45', 'b' => '4', 'total' => '0', 'tax' => '0']);

        $this->assertSame([$this->label() . ': ' . $expected], $texts['total']['corrects']);
    }

    /**
     * Provides rule expressions with the correct value they must produce for a = 45, b = 4.
     *
     * @return array
     */
    public static function function_expression_provider(): array {
        return [
            'sqrt' => ['sqrt(b)', '2'],
            'pow' => ['pow(b, 2)', '16'],
            'tan in radians' => ['tan(a)', '1,6198'],
            'tan in degrees' => ['tan(deg2rad(a))', '1'],
            'round' => ['round(a / b, 2)', '11,25'],
            'min' => ['min(a, b)', '4'],
            'max' => ['max(a, b)', '45'],
            'abs' => ['abs(b - a)', '41'],
            'nested functions' => ['round(sqrt(a * b), 3)', '13,416'],
        ];
    }

    /**
     * Tests an auto-calculated field shows its computed value, not an empty box.
     *
     * Auto-calculated fields are never part of the submitted response, so the value
     * has to be recomputed from the student's inputs.
     *
     * @return void
     */
    public function test_calculated_field_shows_its_computed_value(): void {
        /** @var \qtype_formulaonimage_question $question */
        $question = \test_question_maker::make_question('formulaonimage', 'calc');

        $texts = formulaonimage::field_texts($question, ['a' => '12', 'b' => '30']);

        $this->assertSame('12', $texts['a']['answer']);
        $this->assertSame('30', $texts['b']['answer']);
        $this->assertSame('42', $texts['total']['answer']);
    }

    /**
     * Tests a calculated field is compared against the answer key, as in grading.
     *
     * @return void
     */
    public function test_calculated_field_correct_value_comes_from_the_answer_key(): void {
        /** @var \qtype_formulaonimage_question $question */
        $question = \test_question_maker::make_question('formulaonimage', 'calc');
        $question->showcorrectinpdf = true;

        // The answer key is a = 10, b = 20, so the total should have been 30.
        $texts = formulaonimage::field_texts($question, ['a' => '12', 'b' => '30']);

        $this->assertSame([$this->label() . ': 10'], $texts['a']['corrects']);
        $this->assertSame([$this->label() . ': 20'], $texts['b']['corrects']);
        $this->assertSame([$this->label() . ': 30'], $texts['total']['corrects']);
    }

    /**
     * Tests the answer key is used when invalid input makes the rule unevaluable.
     *
     * This is the case where a corrector needs the correct value most: the old code
     * printed nothing at all as soon as one referenced field was blank or invalid.
     *
     * @return void
     */
    public function test_correct_values_fall_back_to_the_answer_key_for_invalid_input(): void {
        /** @var \qtype_formulaonimage_question $question */
        $question = \test_question_maker::make_question('formulaonimage', 'calc');
        $question->showcorrectinpdf = true;

        $texts = formulaonimage::field_texts($question, ['a' => 'abc', 'b' => '']);

        $this->assertSame('abc', $texts['a']['answer']);
        $this->assertSame('', $texts['total']['answer']);
        $this->assertSame([$this->label() . ': 10'], $texts['a']['corrects']);
        $this->assertSame([$this->label() . ': 20'], $texts['b']['corrects']);
        $this->assertSame([$this->label() . ': 30'], $texts['total']['corrects']);
    }

    /**
     * Tests a text answer is drawn as typed, with the expected words as its correct value.
     *
     * @return void
     */
    public function test_text_answers_are_drawn_as_words(): void {
        /** @var \qtype_formulaonimage_question $question */
        $question = \test_question_maker::make_question('formulaonimage', 'booking');
        $question->showcorrectinpdf = true;

        $texts = formulaonimage::field_texts($question, [
            'amount' => '1200', 'account' => 'Miete', 'side' => 'Soll',
        ]);

        $this->assertSame('Miete', $texts['account']['answer']);
        $this->assertSame([$this->label() . ': Mietaufwand'], $texts['account']['corrects']);
        $this->assertSame([$this->label() . ': Soll'], $texts['side']['corrects']);
        // The number field is still formatted as a number.
        $this->assertSame([$this->label() . ': 1200'], $texts['amount']['corrects']);
    }

    /**
     * Tests a dropdown is drawn with the text of the chosen option, not its stored number.
     *
     * The dropdown question (from qtype_formulaonimage's test helper) has the dropdowns
     * "category" (A|B|C, correct "B") and "verb" (Hören|hören|Lesen, correct "hören").
     * A response stores the number of the chosen option: "3" is the third option.
     *
     * @return void
     */
    public function test_dropdown_answers_are_drawn_as_option_text(): void {
        // Only formulaonimage versions with dropdowns (GL-173) have this test question.
        if (!class_exists(\qtype_formulaonimage\local\fieldtype\dropdown_field::class)) {
            $this->markTestSkipped('This qtype_formulaonimage version has no dropdown fields.');
        }
        /** @var \qtype_formulaonimage_question $question */
        $question = \test_question_maker::make_question('formulaonimage', 'dropdown');
        $question->showcorrectinpdf = true;

        // The student chose C (option 3) and left the verb on "Please choose" ('').
        $texts = formulaonimage::field_texts($question, ['price' => '10', 'category' => '3', 'verb' => '']);

        // The option's text is drawn, not "3"; nothing chosen draws nothing.
        $this->assertSame('C', $texts['category']['answer']);
        $this->assertSame('', $texts['verb']['answer']);
        // The correct option is shown as its text, written as in the options.
        $this->assertSame([$this->label() . ': B'], $texts['category']['corrects']);
        $this->assertSame([$this->label() . ': hören'], $texts['verb']['corrects']);
    }

    /**
     * Tests the flattened PNG really has the answer and the correct value drawn into it.
     *
     * @return void
     */
    public function test_generated_image_contains_black_answers_and_green_correct_values(): void {
        $this->setAdminUser();
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category([]);
        $created = $generator->create_question('formulaonimage', 'sum', ['category' => $category->id]);
        /** @var \qtype_formulaonimage_question $question */
        $question = \question_bank::load_question($created->id);
        $question->showcorrectinpdf = true;

        $png = formulaonimage::generate_image($question, ['a' => '10', 'b' => '5', 'total' => '99', 'tax' => '3']);

        $image = imagecreatefromstring($png);
        $this->assertNotFalse($image, 'The export did not produce a valid PNG.');

        // The "total" field is where both the wrong answer and the correct value land.
        [$dark, $green] = $this->count_ink($image, $question->fields['total']);

        $this->assertGreaterThan(0, $dark, 'The student answer was not drawn into the field box.');
        $this->assertGreaterThan(0, $green, 'The correct value was not drawn into the field box.');
    }

    /**
     * Tests the text in a tall field box is drawn large, not at the old fixed size.
     *
     * Regression test: the size was capped at 14. The PDF shrinks the background image to
     * the page width, so on a large image (4400 pixels wide, with boxes about 120 pixels
     * high) the answers ended up only a few pixels high.
     *
     * @return void
     */
    public function test_generated_image_draws_large_text_into_a_tall_box(): void {
        $this->setAdminUser();
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category([]);
        $created = $generator->create_question('formulaonimage', 'sum', ['category' => $category->id]);
        /** @var \qtype_formulaonimage_question $question */
        $question = \question_bank::load_question($created->id);
        // Make the "total" box as tall as the boxes on a large image (it is 40 in the template).
        $question->fields['total']->height = 120;

        $png = formulaonimage::generate_image($question, ['a' => '10', 'b' => '5', 'total' => '15', 'tax' => '3']);

        // The digits of "15" are drawn about 40 pixels high now. At the old size 14 they
        // were 13 pixels high.
        $this->assertGreaterThan(30, $this->ink_height(imagecreatefromstring($png), $question->fields['total']));
    }

    /**
     * Tests the font size grows with the height of the box, as on the attempt page.
     *
     * @return void
     */
    public function test_font_size_grows_with_the_box(): void {
        // A box of the usual size keeps the size it always had: 0.35 of 30 is 10.5, so 11.
        $this->assertSame(11, formulaonimage::font_size(30, 400, ['Hören']));
        // A tall box gets a large size instead of the old limit 14: 0.35 of 117 is 41.
        $this->assertSame(41, formulaonimage::font_size(117, 400, ['Hören']));
    }

    /**
     * Tests a text too wide for its box gets a smaller size, so it stays inside the box.
     *
     * @return void
     */
    public function test_font_size_shrinks_text_that_is_too_wide(): void {
        global $CFG;
        // The correct value is the widest of the two texts of this box.
        $correct = $this->label() . ': 1.234.567,89';

        $size = formulaonimage::font_size(117, 200, ['12', $correct]);

        // Smaller than the 41 the height alone would give...
        $this->assertLessThan(41, $size);
        // ...and the correct value now fits into the 200 pixels.
        $font = $CFG->dirroot . '/local/quizattemptexport/font/Open_Sans/OpenSans-Regular.ttf';
        $box = imagettfbbox($size, 0, $font, $correct);
        $this->assertLessThanOrEqual(200, $box[2] - $box[0]);
    }

    /**
     * Tests the font size never goes below 6, so the text stays readable at all.
     *
     * @return void
     */
    public function test_font_size_is_at_least_6(): void {
        // A text that cannot fit even at size 6 is drawn at 6 and may stick out of the box.
        $this->assertSame(6, formulaonimage::font_size(117, 10, ['A long answer']));
        // A very low box also gets 6.
        $this->assertSame(6, formulaonimage::font_size(5, 400, ['12']));
    }

    /**
     * Returns how many pixel rows the dark (answer) text in a field box spans.
     *
     * @param \GdImage $image Generated image.
     * @param \stdClass $field Field definition.
     * @return int Rows from the topmost to the lowest dark pixel, 0 when there is none.
     */
    protected function ink_height(\GdImage $image, \stdClass $field): int {
        $top = null;
        $bottom = null;
        for ($y = (int)$field->ypos; $y <= (int)$field->ypos + (int)$field->height; $y++) {
            for ($x = (int)$field->xpos; $x <= (int)$field->xpos + (int)$field->width; $x++) {
                $colour = imagecolorat($image, $x, $y);
                // Dark means all three channels are low, as in count_ink().
                if ((($colour >> 16) & 0xFF) < 90 && (($colour >> 8) & 0xFF) < 90 && ($colour & 0xFF) < 90) {
                    // The first dark row found is the top; every later one moves the bottom.
                    $top = $top ?? $y;
                    $bottom = $y;
                    // One dark pixel is enough for this row.
                    break;
                }
            }
        }
        return $top === null ? 0 : $bottom - $top + 1;
    }

    /**
     * Counts the dark (answer) and green (correct value) pixels inside a field box.
     *
     * @param \GdImage $image Generated image.
     * @param \stdClass $field Field definition.
     * @return array [dark pixel count, green pixel count]
     */
    protected function count_ink(\GdImage $image, \stdClass $field): array {
        $dark = 0;
        $green = 0;
        for ($x = (int)$field->xpos; $x <= (int)$field->xpos + (int)$field->width; $x++) {
            for ($y = (int)$field->ypos; $y <= (int)$field->ypos + (int)$field->height; $y++) {
                $colour = imagecolorat($image, $x, $y);
                $red = ($colour >> 16) & 0xFF;
                $blue = $colour & 0xFF;
                $greenchannel = ($colour >> 8) & 0xFF;
                if ($red < 90 && $greenchannel < 90 && $blue < 90) {
                    $dark++;
                } else if ($greenchannel > $red + 30 && $greenchannel < 160 && $blue < $greenchannel) {
                    $green++;
                }
            }
        }
        return [$dark, $green];
    }
}
