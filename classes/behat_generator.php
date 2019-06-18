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
 * @package    mod_personalschedule
 * @copyright  2019 onwards Vladislav Kovalev snouwer@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use mod_personalschedule\items\schedule;

require_once(__DIR__ . '/../../../lib/behat/behat_base.php');

class behat_generator extends behat_base {

    /**
     * @Given /^I fill personalschedule "(?P<personalschedulename>(?:[^"]|\\")*)" activity settings with the test data$/
     * @param $personalschedulename
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function i_fill_personalschedule_activity_settings_with_test_data($personalschedulename) {
        global $DB;

        $personalschedule = $DB->get_record('personalschedule', array('name' => $personalschedulename),
            '*', MUST_EXIST);

        $courseid = $personalschedule->course;
        $coursemodules = get_array_of_activities($courseid);

        /** @var array $cmprops */
        // Array key - cm id.
        $cmprops = array();
        foreach ($coursemodules as $coursemodule) {
            $cmprop = new stdClass();
            $cmprop->personalschedule = $personalschedule->id;
            $cmprop->cm = $coursemodule->cm;
            $cmprop->duration = 60 * 60; // 1 hour.
            $cmprop->category = $coursemodule->section;
            $cmprop->weight = 1;
            $cmprop->is_ignored = false;

            $cmprops[$coursemodule->cm] = $cmprop;
        }
        self::set_cm_props($personalschedule->id, $cmprops);
    }

    /**
     * @param $personalscheduleid int
     * @param $cmprops array
     * @throws coding_exception
     * @throws dml_exception
     */
    private static function set_cm_props($personalscheduleid, $cmprops) {
        global $DB;

        $deleteconditions = array();
        $deleteconditions["personalschedule"] = $personalscheduleid;

        $DB->delete_records("personalschedule_cm_props", $deleteconditions);
        $DB->insert_records("personalschedule_cm_props", $cmprops);
    }

    /**
     *
     * @Given /^I fill personalschedule "(?P<personalschedulename>(?:[^"]|\\")*)" user settings with the test data$/
     * @param $personalschedulename string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function i_fill_personalschedule_user_settings_with_test_data($personalschedulename) {
        global $DB;

        $personalschedule = $DB->get_record('personalschedule', array('name' => $personalschedulename),
            '*', MUST_EXIST);

        $user = $this->get_session_user();

        $userid = $user->id;

        $schedule = new schedule();

        $mindayidx = mod_personalschedule_config::DAYINDEXMIN;
        $maxdayidx = mod_personalschedule_config::DAYINDEXMAX;

        for ($dayidx = $mindayidx; $dayidx <= $maxdayidx; $dayidx++) {
            for ($periodidx = 2; $periodidx <= 11; $periodidx++) {
                $schedule->add_status(
                    $dayidx, $periodidx, mod_personalschedule_config::STATUSSLEEP, 0);
            }
        }

        $schedule->fill_empty_day_periods_with_status(
            mod_personalschedule_config::STATUSFREE, 1);

        self::set_schedule($personalschedule->id, $userid, $schedule, 18);

    }

    /**
     * @param $personalscheduleid int
     * @param $userid int
     * @param $schedule mod_personalschedule\items\schedule
     * @param $age int
     * @throws coding_exception
     * @throws dml_exception
     */
    private static function set_schedule($personalscheduleid, $userid, $schedule, $age) {
        global $DB;
        $deleteconditions = array();
        $deleteconditions["userid"] = $userid;
        $deleteconditions["personalschedule"] = $personalscheduleid;

        $scheduleinserts = array();
        $statuses = $schedule->get_statuses();
        foreach ($statuses as $dayidx => $val) {
            foreach ($val as $periodidx => $checkstatus) {
                $newdata = new stdClass();
                $newdata->userid = $userid;
                $newdata->personalschedule = $personalscheduleid;
                $newdata->period_idx = $periodidx;
                $newdata->day_idx = $dayidx;
                $newdata->check_status = $checkstatus;

                $scheduleinserts[] = $newdata;
            }

        }

        $DB->delete_records("personalschedule_schedules", $deleteconditions);
        $DB->insert_records("personalschedule_schedules", $scheduleinserts);

        $readinesses = $schedule->get_readinesses();
        $readinessinserts = array();
        foreach ($readinesses as $periodidx => $readinessstatus) {
            $newdata = new stdClass();
            $newdata->userid = $userid;
            $newdata->personalschedule = $personalscheduleid;
            $newdata->period_idx = $periodidx;
            $newdata->check_status = $readinessstatus;

            $readinessinserts[] = $newdata;
        }

        $DB->delete_records("personalschedule_readiness", $deleteconditions);
        $DB->insert_records("personalschedule_readiness", $readinessinserts);

        $alreadysubmitted = $DB->record_exists("personalschedule_usrattempts", $deleteconditions);

        $usrattemptobject = new stdClass();
        $usrattemptobject->userid = $userid;
        $usrattemptobject->personalschedule = $personalscheduleid;
        $usrattemptobject->timemodified = time();

        if ($alreadysubmitted) {
            $DB->update_record("personalschedule_usrattempts", $usrattemptobject);
        } else {
            $usrattemptobject->timecreated = time();
            $DB->insert_record("personalschedule_usrattempts", $usrattemptobject);
        }

        $ageinsert = new stdClass();
        $ageinsert->userid = $userid;
        $ageinsert->personalschedule = $personalscheduleid;

        if ($age < mod_personalschedule_config::AGEMIN) {
            $age = mod_personalschedule_config::AGEMIN;
        } else if ($age > mod_personalschedule_config::AGEMAX) {
            $age = mod_personalschedule_config::AGEMAX;
        }

        $ageinsert->age = $age;
        $DB->delete_records("personalschedule_user_props", $deleteconditions);
        $DB->insert_record("personalschedule_user_props", $ageinsert);
    }

    private function delete_proposed_cache($userid) {
        global $DB;
        $DB->delete_records("personalschedule_proposes", array("userid" => $userid));
    }

    /**
     * Opens Moodle homepage.
     *
     * @Then /^I should log performance info with tag "(?P<tag>(?:[^"]|\\")*)"$/
     * @param $tag string
     * @return bool
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     * @throws coding_exception
     */
    public function i_should_log_performance_info($tag) {
        $maxtryings = 10;
        $data = "";
        $userid = $this->get_session_user()->id;
        for ($currenttry = 1; $currenttry <= $maxtryings; $currenttry++) {
            $this->delete_proposed_cache($userid);
            $this->getSession()->reload();

            $containerpageloadtime = $this->get_selected_node("css_element", ".timeused");
            $containerdbqueries = $this->get_selected_node("css_element", ".dbqueries");
            $containerram = $this->get_selected_node("css_element", ".memoryused");

            $data .= sprintf("---\n%s (%d) WITHOUT CACHE\n%s\n%s\n%s\n---\n",
                $tag,
                $currenttry,
                $containerpageloadtime->getHtml(),
                $containerdbqueries->getHtml(),
                $containerram->getHtml());
        }

        for ($currenttry = 1; $currenttry <= $maxtryings; $currenttry++) {
            $this->getSession()->reload();

            $containerpageloadtime = $this->get_selected_node("css_element", ".timeused");
            $containerdbqueries = $this->get_selected_node("css_element", ".dbqueries");
            $containerram = $this->get_selected_node("css_element", ".memoryused");

            $data .= sprintf("---\n%s (%d) WITH CACHE\n%s\n%s\n%s\n---\n",
                $tag,
                $currenttry,
                $containerpageloadtime->getHtml(),
                $containerdbqueries->getHtml(),
                $containerram->getHtml());
        }

        file_put_contents('C:\\moodle\\log.txt', $data, FILE_APPEND);
        $data = $this->get_selected_node("css_element", "body")->getHtml();
        file_put_contents("C:\\moodle\\$tag.txt", $data);
        return true;
    }

    /**
     * Opens Moodle homepage.
     *
     * @Given /^I am on my homepage$/
     */
    public function i_am_on_my_homepage() {
        $this->getSession()->visit($this->locate_path('/my'));
    }

    /**
     * Creates the specified element. More info about available elements in http://docs.moodle.org/dev/Acceptance_testing#Fixtures.
     *
     * @Given /^the course "(?P<courseshortname>(?:[^"]|\\")*)" with "(?P<coursesize>\d+)" elements exists$/
     *
     * @throws Exception
     */
    public function the_course_with_elements_exists($courseshortname, $coursesize) {
        $backend = new tool_generator_course_backend(
            $courseshortname,
            $coursesize
        );
        $id = $backend->make();
    }
}



