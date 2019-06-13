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

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/personalschedule/lib.php');

class mod_personalschedule_proposer_simple_testcase extends basic_testcase {

    public function test_is_proposed_element_skipped_by_time() {

        $hourSeconds = (60 * 0) + (60 * 60 * 1);

        $elementPeriodIdxBegin = 12;
        $elementDayBeginDayIdx = 1;

        $proposedElement = new mod_personalschedule\items\proposed_activity_object(
            null, $hourSeconds, $elementPeriodIdxBegin, 0, $elementDayBeginDayIdx,
            1, 1);


        $isSkipped = mod_personalschedule_proposer_ui::is_proposed_element_skipped_by_time(
            $proposedElement, $elementDayBeginDayIdx, $elementPeriodIdxBegin + 2);

        $this->assertEquals(true, $isSkipped);
    }

    public function test_get_duration_components() {
        // 1 minute.
        $totalSeconds = (60 * 1) + // Minutes.
            (60 * 60 * 0) + // Hours.
            (24 * 60 * 60 * 0); // Days.

        mod_personalschedule_proposer_ui::get_duration_components(
            $totalSeconds, $days, $hours, $minutes, $seconds);

        $this->assertEquals(1, $minutes);
        $this->assertEquals(0, $hours);
        $this->assertEquals(0, $days);

        // 1 hour.
        $totalSeconds = (60 * 0) + // Minutes.
            (60 * 60 * 1) + // Hours.
            (24 * 60 * 60 * 0); // Days.

        mod_personalschedule_proposer_ui::get_duration_components(
            $totalSeconds, $days, $hours, $minutes, $seconds);

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
        $this->personalschedule = $this->getDataGenerator()->create_module('personalschedule', array('course' => $this->course->id));
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
     * Create a quiz with questions including a started or finished attempt optionally
     *
     * @param  boolean $startattempt whether to start a new attempt
     * @param  boolean $finishattempt whether to finish the new attempt
     * @param  string $behaviour the quiz preferredbehaviour, defaults to 'deferredfeedback'.
     * @return array array containing the quiz, context and the attempt
     */
    private function create_quiz_with_questions($startattempt = false, $finishattempt = false, $behaviour = 'deferredfeedback') {

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

        if ($startattempt or $finishattempt) {
            // Now, do one attempt.
            $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
            $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

            $timenow = time();
            $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $this->student->id);
            quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
            quiz_attempt_save_started($quizobj, $quba, $attempt);
            $attemptobj = quiz_attempt::create($attempt->id);

            if ($finishattempt) {
                // Process some responses from the student.
                $tosubmit = array(1 => array('answer' => '3.14'));
                $attemptobj->process_submitted_actions(time(), false, $tosubmit);
                // Finish the attempt.
                $attemptobj->process_finish(time(), false);
            }
            return array($quiz, $context, $quizobj, $attempt, $attemptobj, $quba);
        } else {
            return array($quiz, $context, $quizobj);
        }

    }

    public function test_get_user_views_info() {

        $this->reset_for_test();
        global $DB;


        // Test user with full capabilities.
        $this->setUser($this->student);

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

        $userViewsInfo = mod_personalschedule_proposer::get_user_views_info($this->student->id, $this->course->id);
        $this->assertCount(0, $userViewsInfo);

        // Create a quiz with one attempt finished.
        list($quiz, $context, $quizobj, $attempt, $attemptobj) = $this->create_quiz_with_questions(true, true);

        // Start a new attempt, but not finish it.
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 2, false, $timenow, false, $this->student->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $userViewsInfo = mod_personalschedule_proposer::get_user_views_info($this->student->id, $this->course->id);
        $this->assertCount(1, $userViewsInfo);
        $this->assertArrayHasKey($quiz->cmid, $userViewsInfo);
        $this->assertEquals(1, $userViewsInfo[$quiz->cmid]->attempts);
        $this->assertEquals(1, $userViewsInfo[$quiz->cmid]->actions);

        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

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

        $resource = $this->getDataGenerator()->create_module('resource', array('course' => $this->course->id));

        $params = array(
            'context' => context_module::instance($resource->cmid),
            'objectid' => $resource->id,
            'userid' => $this->student->id,
            'courseid' => $this->course->id,
        );
        $event = \mod_resource\event\course_module_viewed::create($params);
        $event->trigger();

        $userViewsInfo = mod_personalschedule_proposer::get_user_views_info($this->student->id, $this->course->id);
        $this->assertCount(2, $userViewsInfo);
        $this->assertArrayHasKey($resource->cmid, $userViewsInfo);
        $this->assertEquals(0, $userViewsInfo[$resource->cmid]->attempts);
        $this->assertEquals(1, $userViewsInfo[$resource->cmid]->actions);

        $this->assertCount(2, $result['attempts']);
        return;
    }

    public function test_get_mod_workshop_user_view_info() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $workshop = $this->getDataGenerator()->create_module('workshop', array('course' => $course));
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);
        /** @var mod_workshop_generator $workshopgenerator */
        $workshopgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshop');

        $localId = 0;
        $userViewInfo = mod_personalschedule_proposer::get_mod_workshop_user_view_info(
            $user1->id, $course->id, $workshop->id, 0, $localId++);

        $this->assertEquals(0, $userViewInfo->attempts);

        $submissionid2 = $workshopgenerator->create_submission($workshop->id, $user1->id);
        $assessmentid2 = $workshopgenerator->create_assessment($submissionid2, $user1->id);

        $this->assertEquals(true, is_callable("workshop_update_grades"));

        workshop_update_grades($workshop);

        $userViewInfo = mod_personalschedule_proposer::get_mod_workshop_user_view_info(
            $user1->id, $course->id, $workshop->id, 0, $localId++);

        $submissionid1 = $workshopgenerator->create_submission($workshop->id, $user1->id);
        $assessmentid1 = $workshopgenerator->create_assessment($submissionid1, $user1->id, array(
            'weight' => 3,
            'grade' => 95.00000,
        ));

        workshop_update_grades($workshop);
        $userViewInfo = mod_personalschedule_proposer::get_mod_workshop_user_view_info(
            $user1->id, $course->id, $workshop->id, 0, $localId++);

        $this->assertEquals(1, $userViewInfo->attempts);

        return;
        $assessments = $DB->get_records('workshop_assessments');
        $this->assertEquals(3, $assessments[$assessmentid1]->weight);
        $this->assertEquals(95.00000, $assessments[$assessmentid1]->grade);
        $this->assertEquals(1, $assessments[$assessmentid2]->weight);
        $this->assertNull($assessments[$assessmentid2]->grade);
    }
}
