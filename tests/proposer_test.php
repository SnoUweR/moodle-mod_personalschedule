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
 * Personalization module proposer simple functions tests
 *
 * @package    mod_personalschedule
 * @category   external
 * @copyright  2019 onwards Vladislav Kovalev snouwer@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_personalschedule\items\user_practice_info;
use mod_personalschedule\items\user_view_info;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/personalschedule/lib.php');

class mod_personalschedule_proposer_simple_testcase extends basic_testcase {

    public function test_is_proposed_element_skipped_by_time() {

        $hourseconds = (60 * 0) + (60 * 60 * 1);

        $elementperiodidxbegin = 12;
        $elementdaybegindayidx = 1;

        $proposedelement = new mod_personalschedule\items\proposed_activity_object(
            null, $hourseconds, $elementperiodidxbegin, 0, $elementdaybegindayidx,
            1, 1);

        $isskipped = mod_personalschedule_proposer_ui::is_proposed_element_skipped_by_time(
            $proposedelement, $elementdaybegindayidx, $elementperiodidxbegin + 2);

        $this->assertEquals(true, $isskipped);
    }

    public function test_get_duration_components() {
        // 1 minute.
        $totalseconds = (60 * 1) + // Minutes.
            (60 * 60 * 0) + // Hours.
            (24 * 60 * 60 * 0); // Days.

        mod_personalschedule_proposer_ui::get_duration_components(
            $totalseconds, $days, $hours, $minutes, $seconds);

        $this->assertEquals(1, $minutes);
        $this->assertEquals(0, $hours);
        $this->assertEquals(0, $days);

        // 1 hour.
        $totalseconds = (60 * 0) + // Minutes.
            (60 * 60 * 1) + // Hours.
            (24 * 60 * 60 * 0); // Days.

        mod_personalschedule_proposer_ui::get_duration_components(
            $totalseconds, $days, $hours, $minutes, $seconds);

        $this->assertEquals(0, $minutes);
        $this->assertEquals(1, $hours);
        $this->assertEquals(0, $days);
    }
}

/**
 * Personalization module proposer functions tests
 *
 * @package    mod_personalschedule
 * @category   external
 * @copyright  2019 onwards Vladislav Kovalev snouwer@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_personalschedule_proposer_testcase extends externallib_advanced_testcase {

    /**
     * Set up for every test
     */
    public function setUp() {

    }

    private function reset_for_test() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course();
        $this->personalschedule = $this->getDataGenerator()->create_module('personalschedule',
            array('course' => $this->course->id));
        $this->context = context_module::instance($this->personalschedule->cmid);
        $this->cm = get_coursemodule_from_instance('personalschedule', $this->personalschedule->id);

        // Create users.
        $this->student = self::getDataGenerator()->create_user();
        $this->teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $this->teacherrole->id, 'manual');
    }


    /**
     * @param stdClass $attempt
     */
    private function quiz_attempt_finish($attempt) {
        // Process some responses from the student.
        $tosubmit = array(1 => array('answer' => '3.14'));

        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions(time(), false, $tosubmit);
        // Finish the attempt.
        $attemptobj->process_finish(time(), false);
    }

    /**
     * Create a quiz with questions including a started or finished attempt optionally
     * @param string $behaviour the quiz preferredbehaviour, defaults to 'deferredfeedback'.
     * @return array array containing the quiz, context and the attempt
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function create_quiz_with_questions($behaviour = 'deferredfeedback') {

        // Create a new quiz with attempts.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $data = array('course' => $this->course->id,
            'sumgrades' => 2,
            'preferredbehaviour' => $behaviour);
        $quiz = $quizgenerator->create_instance($data);
        $context = context_module::instance($quiz->cmid);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        quiz_add_quiz_question($question->id, $quiz);
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        quiz_add_quiz_question($question->id, $quiz);

        $quizobj = quiz::create($quiz->id, $this->student->id);

        // Set grade to pass.
        $item = grade_item::fetch(array('courseid' => $this->course->id, 'itemtype' => 'mod',
            'itemmodule' => 'quiz', 'iteminstance' => $quiz->id, 'outcomeid' => null));
        $item->gradepass = 80;
        $item->update();

        return array($quiz, $context, $quizobj);
    }

    public function test_get_user_views_info() {

        $this->reset_for_test();

        // Test user with full capabilities.
        $this->setUser($this->teacher);

        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        set_config('logguests', 1, 'logstore_standard');

        $manager = get_log_manager(true);
        $stores = $manager->get_readers();
        $this->assertCount(1, $stores);
        $this->assertEquals(array('logstore_standard'), array_keys($stores));
        /** @var \logstore_standard\log\store $store */
        $store = $stores['logstore_standard'];
        $this->assertInstanceOf('logstore_standard\log\store', $store);
        $this->assertInstanceOf('tool_log\log\writer', $store);
        $this->assertTrue($store->is_logging());

        $userviewsinfo = mod_personalschedule_proposer::get_user_views_info($this->student->id, $this->course->id);
        $this->assertCount(0, $userviewsinfo);

        // Create a quiz with one attempt finished.
        list($quiz, $context, $quizobj) = $this->create_quiz_with_questions();

        $this->setUser($this->student);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, null, $timenow, false, $this->student->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $userviewsinfo = mod_personalschedule_proposer::get_user_views_info($this->student->id, $this->course->id);
        $this->assertCount(0, $userviewsinfo);

        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        $userviewsinfo = mod_personalschedule_proposer::get_user_views_info($this->student->id, $this->course->id);
        $this->assertCount(1, $userviewsinfo);
        $this->assertArrayHasKey($quiz->cmid, $userviewsinfo);
        // Actions is zero because there is attempt, but it's not finished.
        $this->assertEquals(0, $userviewsinfo[$quiz->cmid]->actions);
        // Attempt is not finished.
        $this->assertEquals(0, $userviewsinfo[$quiz->cmid]->attempts);

        $this->quiz_attempt_finish($attempt);

        $userviewsinfo = mod_personalschedule_proposer::get_user_views_info($this->student->id, $this->course->id);
        $this->assertCount(1, $userviewsinfo);
        $this->assertArrayHasKey($quiz->cmid, $userviewsinfo);
        $this->assertEquals(1, $userviewsinfo[$quiz->cmid]->attempts);
        $this->assertEquals(1, $userviewsinfo[$quiz->cmid]->actions);

        // Test filters. All attempts.
        $result = mod_quiz_external::get_user_attempts($quiz->id, $this->student->id, 'all', false);
        $result = external_api::clean_returnvalue(mod_quiz_external::get_user_attempts_returns(), $result);

        $params = array(
            'context' => context_module::instance($quiz->cmid),
            'objectid' => $quiz->id,
            'userid' => $this->student->id,
            'courseid' => $this->course->id,
        );
        $event1 = \mod_quiz\event\course_module_viewed::create($params);
        $event1->trigger();
        $this->setUser($this->teacher);
        $resource = $this->getDataGenerator()->create_module('resource', array('course' => $this->course->id));
        $this->setUser($this->student);
        $params = array(
            'context' => context_module::instance($resource->cmid),
            'objectid' => $resource->id,
            'userid' => $this->student->id,
            'courseid' => $this->course->id,
        );
        $event = \mod_resource\event\course_module_viewed::create($params);
        $event->trigger();

        $userviewsinfo = mod_personalschedule_proposer::get_user_views_info($this->student->id, $this->course->id);
        $this->assertCount(2, $userviewsinfo);
        $this->assertArrayHasKey($resource->cmid, $userviewsinfo);
        $this->assertFalse($userviewsinfo[$resource->cmid] instanceof user_practice_info);
        $this->assertEquals(1, $userviewsinfo[$resource->cmid]->actions);

        $this->assertCount(1, $result['attempts']);
        return;
    }
}
