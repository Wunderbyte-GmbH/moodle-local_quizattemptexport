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
