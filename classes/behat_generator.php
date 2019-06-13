<?php

use mod_personalschedule\items\schedule;

require_once(__DIR__ . '/../../../lib/behat/behat_base.php');

class behat_generator extends behat_base {

    /**
     * @Given /^I fill personalschedule "(?P<personalscheduleName>(?:[^"]|\\")*)" activity settings with the test data$/
     * @param $personalscheduleName
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function i_fill_personalschedule_activity_settings_with_test_data($personalscheduleName) {
        global $DB;

        $personalschedule = $DB->get_record('personalschedule', array('name' => $personalscheduleName),
            '*', MUST_EXIST);


        $courseId = $personalschedule->course;
        $courseModules = get_array_of_activities($courseId);

        /** @var array $cmProps Array key - cm id. */
        $cmProps = array();
        foreach ($courseModules as $courseModule)
        {
            $cmProp = new stdClass();
            $cmProp->personalschedule = $personalschedule->id;
            $cmProp->cm = $courseModule->cm;
            $cmProp->duration = 60 * 60; // 1 hour.
            $cmProp->category = $courseModule->section;
            $cmProp->weight = 1;
            $cmProp->is_ignored = false;

            $cmProps[$courseModule->cm] = $cmProp;
        }
        self::set_cm_props($personalschedule->id, $cmProps);
    }

    /**
     * @param $personalscheduleId int
     * @param $cmProps array
     * @throws coding_exception
     * @throws dml_exception
     */
    private static function set_cm_props($personalscheduleId, $cmProps)
    {
        global $DB;

        $delete_conditions = array();
        $delete_conditions["personalschedule"] = $personalscheduleId;

        $DB->delete_records("personalschedule_cm_props", $delete_conditions);
        $DB->insert_records("personalschedule_cm_props", $cmProps);
    }

    /**
     *
     * @Given /^I fill personalschedule "(?P<personalscheduleName>(?:[^"]|\\")*)" user settings with the test data$/
     * @param $personalscheduleName string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function i_fill_personalschedule_user_settings_with_test_data($personalscheduleName) {
        global $DB;

        $personalschedule = $DB->get_record('personalschedule', array('name' => $personalscheduleName),
            '*', MUST_EXIST);

        $user = $this->get_session_user();

        $userId = $user->id;

        $schedule = new schedule();

        $minDayIdx =  mod_personalschedule_config::dayIndexMin;
        $maxDayIdx = mod_personalschedule_config::dayIndexMax;

        for ($dayIdx = $minDayIdx; $dayIdx <= $maxDayIdx; $dayIdx++) {
            for ($periodIdx = 2; $periodIdx <= 11; $periodIdx++) {
                $schedule->add_status(
                    $dayIdx, $periodIdx, mod_personalschedule_config::statusSleep, 0);
            }
        }

        $schedule->fill_empty_day_periods_with_status(
            mod_personalschedule_config::statusFree, 1);

        self::set_schedule($personalschedule->id, $userId, $schedule, 18);

    }

    /**
     * @param $personalscheduleId int
     * @param $userId int
     * @param $schedule mod_personalschedule\items\schedule
     * @param $age int
     * @throws coding_exception
     * @throws dml_exception
     */
    private static function set_schedule($personalscheduleId, $userId, $schedule, $age)
    {
        global $DB;
        $delete_conditions = array();
        $delete_conditions["userid"] = $userId;
        $delete_conditions["personalschedule"] = $personalscheduleId;

        $schedule_inserts = array();
        $statuses = $schedule->get_statuses();
        foreach ($statuses as $day_idx => $val) {
            foreach ($val as $period_idx => $check_status)
            {
                $newdata = new stdClass();
                $newdata->userid = $userId;
                $newdata->personalschedule = $personalscheduleId;
                $newdata->period_idx = $period_idx;
                $newdata->day_idx = $day_idx;
                $newdata->check_status = $check_status;

                $schedule_inserts[] = $newdata;
            }

        }

        $DB->delete_records("personalschedule_schedules", $delete_conditions);
        $DB->insert_records("personalschedule_schedules", $schedule_inserts);

        $readinesses = $schedule->get_readinesses();
        $readinessInserts = array();
        foreach ($readinesses as $periodIdx => $readinessStatus)
        {
            $newdata = new stdClass();
            $newdata->userid = $userId;
            $newdata->personalschedule = $personalscheduleId;
            $newdata->period_idx = $periodIdx;
            $newdata->check_status = $readinessStatus;

            $readinessInserts[] = $newdata;
        }

        $DB->delete_records("personalschedule_readiness", $delete_conditions);
        $DB->insert_records("personalschedule_readiness", $readinessInserts);

        $alreadySubmitted = $DB->record_exists("personalschedule_usrattempts", $delete_conditions);

        $usrAttemptObject = new stdClass();
        $usrAttemptObject->userid = $userId;
        $usrAttemptObject->personalschedule = $personalscheduleId;
        $usrAttemptObject->timemodified = time();

        if ($alreadySubmitted) {
            $DB->update_record("personalschedule_usrattempts", $usrAttemptObject);
        } else {
            $usrAttemptObject->timecreated = time();
            $DB->insert_record("personalschedule_usrattempts", $usrAttemptObject);
        }


        $ageInsert = new stdClass();
        $ageInsert->userid = $userId;
        $ageInsert->personalschedule = $personalscheduleId;

        if ($age < mod_personalschedule_config::ageMin) $age = mod_personalschedule_config::ageMin;
        else if ($age > mod_personalschedule_config::ageMax) $age = mod_personalschedule_config::ageMax;

        $ageInsert->age = $age;
        $DB->delete_records("personalschedule_user_props", $delete_conditions);
        $DB->insert_record("personalschedule_user_props", $ageInsert);
    }

    private function delete_proposed_cache($userId) {
        global $DB;
        $DB->delete_records("personalschedule_proposes", array("userid" => $userId));
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
        $maxTryings = 10;
        $data = "";
        $userId = $this->get_session_user()->id;
        for ($currentTry = 1; $currentTry <= $maxTryings; $currentTry++) {
            $this->delete_proposed_cache($userId);
            $this->getSession()->reload();

            $containerPageLoadTime = $this->get_selected_node("css_element", ".timeused");
            $containerDbQueries = $this->get_selected_node("css_element", ".dbqueries");
            $containerRam = $this->get_selected_node("css_element", ".memoryused");

            $data .= sprintf("---\n%s (%d) WITHOUT CACHE\n%s\n%s\n%s\n---\n",
                $tag,
                $currentTry,
                $containerPageLoadTime->getHtml(),
                $containerDbQueries->getHtml(),
                $containerRam->getHtml());
        }

        for ($currentTry = 1; $currentTry <= $maxTryings; $currentTry++) {
            $this->getSession()->reload();

            $containerPageLoadTime = $this->get_selected_node("css_element", ".timeused");
            $containerDbQueries = $this->get_selected_node("css_element", ".dbqueries");
            $containerRam = $this->get_selected_node("css_element", ".memoryused");

            $data .= sprintf("---\n%s (%d) WITH CACHE\n%s\n%s\n%s\n---\n",
                $tag,
                $currentTry,
                $containerPageLoadTime->getHtml(),
                $containerDbQueries->getHtml(),
                $containerRam->getHtml());
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
     * @Given /^the course "(?P<courseShortName>(?:[^"]|\\")*)" with "(?P<courseSize>\d+)" elements exists$/
     *
     * @throws Exception
     */
    public function the_course_with_elements_exists($courseShortName, $courseSize) {
        $backend = new tool_generator_course_backend(
            $courseShortName,
            $courseSize
        );
        $id = $backend->make();
    }
}



