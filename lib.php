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
 * @package   mod_personalschedule
 * @copyright 2019 onwards Vladislav Kovalev  snouwer@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance or false if there was an error
 *
 * @param stdClass $personalschedule : An object from the form in mod_form.php
 * @return int|bool The id of the newly inserted personalschedule record or false if there was an error
 */
function personalschedule_add_instance($personalschedule) {
    global $DB;

    $personalschedule->timecreated = time();
    $personalschedule->timemodified = $personalschedule->timecreated;

    try {
        $id = $DB->insert_record("personalschedule", $personalschedule);
    } catch (dml_exception $e) {
        return false;
    }

    $personalschedule->id = $id;
    personalschedule_set_cm_props($personalschedule);

    $completiontimeexpected = !empty($personalschedule->completionexpected) ? $personalschedule->completionexpected : null;
    \core_completion\api::update_completion_date_event($personalschedule->coursemodule, 'personalschedule',
        $id, $completiontimeexpected);

    return $id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $personalschedule : An object from the form in mod_form.php
 * @return bool Returns false if there was an error, or true if update was successful
 */
function personalschedule_update_instance($personalschedule) {
    global $DB;

    $personalschedule->id = $personalschedule->instance;
    $personalschedule->timemodified = time();

    personalschedule_set_cm_props($personalschedule);

    return $DB->update_record("personalschedule", $personalschedule);
}

/**
 * @param stdClass $formdata : An object from the form in mod_form.php
 * @throws coding_exception
 * @throws dml_exception
 */
function personalschedule_set_cm_props($formdata) {
    global $DB;

    $arraywithprops = array();
    foreach ($formdata as $key => $item) {

        if (personalschedule_try_parse_cm_prop(
            $arraywithprops, mod_personalschedule_config::CMPROPKEYDURATION, $key, $item)) {
            continue;
        }

        if (personalschedule_try_parse_cm_prop(
            $arraywithprops, mod_personalschedule_config::CMPROPKEYCATEGORY, $key, $item)) {
            continue;
        }

        if (personalschedule_try_parse_cm_prop(
            $arraywithprops, mod_personalschedule_config::CMPROPKEYWEIGHT, $key, $item)) {
            continue;
        }

        if (personalschedule_try_parse_cm_prop(
            $arraywithprops, mod_personalschedule_config::CMPROPKEYISIGNORED, $key, $item)) {
            continue;
        }
    }

    $propsinserts = array();
    foreach ($arraywithprops as $cmkey => $cmprops) {
        $newdata = new stdClass();
        $newdata->personalschedule = $formdata->id;
        $newdata->cm = $cmkey;
        $newdata->duration = $cmprops[mod_personalschedule_config::CMPROPKEYDURATION];
        $newdata->category = $cmprops[mod_personalschedule_config::CMPROPKEYCATEGORY];
        $newdata->weight = $cmprops[mod_personalschedule_config::CMPROPKEYWEIGHT];
        $newdata->is_ignored = $cmprops[mod_personalschedule_config::CMPROPKEYISIGNORED];

        $propsinserts[] = $newdata;
    }

    $deleteconditions = array();
    $deleteconditions["personalschedule"] = $formdata->id;
    $DB->delete_records("personalschedule_cm_props", $deleteconditions);
    $DB->insert_records("personalschedule_cm_props", $propsinserts);
}

/**
 * Tries to parse course module property ($propertyname) from $key, and if it
 * successfully parsed, then adds $item to $arraywithprops and returns true.
 * If can't parse, then returns false.
 * @param array[][] $arraywithprops : Array to add parsed property into.
 * @param string $propertyname : Property name, which should be parsed.
 * @param string $key : Current item key.
 * @param string $item : Current item value.
 * @return bool True if parse success. False if not.
 */
function personalschedule_try_parse_cm_prop(&$arraywithprops, $propertyname, $key, $item) {
    if (substr($key, 0, strlen($propertyname)) === $propertyname) {
        $exploded = explode(mod_personalschedule_config::SEPARATORHIDDENINPUT, $key);
        $cm = $exploded[1];

        if (!array_key_exists($cm, $arraywithprops)) {
            $arraywithprops[$cm] = array();
        }

        $arraywithprops[$cm][$propertyname] = $item;

        return true;
    }
    return false;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 * Called automatically by Moodle.
 * @param int $personalscheduleid : Activity module instance id.
 * @return bool
 */
function personalschedule_delete_instance($personalscheduleid) {
    global $DB;

    if (!$personalschedule = $DB->get_record("personalschedule", array("id" => $personalscheduleid))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('personalschedule', $personalscheduleid);
    core_completion\api::update_completion_date_event(
        $cm->id, 'personalschedule', $personalscheduleid, null);

    $result = true;
    $deleteconditionsfordatatables = array("personalschedule" => $personalschedule->id);

    if (!$DB->delete_records("personalschedule_analysis", $deleteconditionsfordatatables)) {
        $result = false;
    }

    if (!$DB->delete_records("personalschedule_cm_props", $deleteconditionsfordatatables)) {
        $result = false;
    }

    if (!$DB->delete_records("personalschedule_readiness", $deleteconditionsfordatatables)) {
        $result = false;
    }

    if (!$DB->delete_records("personalschedule_proposes", $deleteconditionsfordatatables)) {
        $result = false;
    }

    if (!$DB->delete_records("personalschedule_schedules", $deleteconditionsfordatatables)) {
        $result = false;
    }

    if (!$DB->delete_records("personalschedule_user_props", $deleteconditionsfordatatables)) {
        $result = false;
    }

    if (!$DB->delete_records("personalschedule_usrattempts", $deleteconditionsfordatatables)) {
        $result = false;
    }

    if (!$DB->delete_records("personalschedule", array("id" => $personalschedule->id))) {
        $result = false;
    }
    return $result;
}

/**
 * @param object $course
 * @param mixed $viewfullnames
 * @param int $timestamp
 * @return bool
 * @global stdClass
 * @global object
 */
function personalschedule_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $DB, $OUTPUT;

    $modinfo = get_fast_modinfo($course);
    $ids = array();
    foreach ($modinfo->cms as $cm) {
        if ($cm->modname != 'personalschedule') {
            continue;
        }
        if (!$cm->uservisible) {
            continue;
        }
        $ids[$cm->instance] = $cm->instance;
    }

    if (!$ids) {
        return false;
    }

    $slist = implode(',', $ids);

    $allusernames = user_picture::fields('u');
    $rs = $DB->get_recordset_sql("SELECT sa.userid, sa.personalschedule, MAX(sa.timemodified) AS time,
                                         $allusernames
                                    FROM {personalschedule_usrattempts} sa
                                    JOIN {user} u ON u.id = sa.userid
                                   WHERE sa.personalschedule IN ($slist) AND sa.timemodified > ?
                                GROUP BY sa.userid, sa.personalschedule, $allusernames
                                ORDER BY time ASC", array($timestart));
    if (!$rs->valid()) {
        $rs->close(); // Not going to iterate (but exit), close rs.
        return false;
    }

    $personalschedules = array();

    foreach ($rs as $personalschedule) {
        $cm = $modinfo->instances['personalschedule'][$personalschedule->personalschedule];
        $personalschedule->name = $cm->name;
        $personalschedule->cmid = $cm->id;
        $personalschedules[] = $personalschedule;
    }
    $rs->close();

    if (!$personalschedules) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newpersonalscheduleresponses', 'personalschedule') . ':', 3);
    foreach ($personalschedules as $personalschedule) {
        $url = $CFG->wwwroot . '/mod/personalschedule/view.php?id=' . $personalschedule->cmid;
        print_recent_activity_note($personalschedule->time, $personalschedule, $personalschedule->name, $url,
            false, $viewfullnames);
    }

    return true;
}


/**
 * Returns the unix time when user's schedule was created and the unix time when user's schedule was
 * modified (only the last one). If there is no information, then returns false.
 * @param int $personalscheduleid Personalization module instance ID.
 * @param int $userid User ID;
 * @return stdClass|bool stdClass with 'timecreated' and 'timemodified' fields. Can be false, if there is no information for the
 * specified user and personalschedule.
 * @throws dml_exception
 */
function personalschedule_get_schedule_creation_modified_time($personalscheduleid, $userid) {
    global $DB;
    $result = $DB->get_record("personalschedule_usrattempts", array(
        'personalschedule' => $personalscheduleid,
        'userid' => $userid,
    ), 'timecreated, timemodified');

    return $result;
}

/**
 * @param int $personalscheduleid
 * @param int $groupid
 * @param int $groupingid
 * @return array
 */
function personalschedule_get_responses($personalscheduleid, $groupid, $groupingid) {
    global $DB;

    $params = array('personalscheduleid' => $personalscheduleid, 'groupid' => $groupid, 'groupingid' => $groupingid);

    if ($groupid) {
        $groupsjoin = "JOIN {groups_members} gm ON u.id = gm.userid AND gm.groupid = :groupid ";

    } else {
        if ($groupingid) {
            $groupsjoin = "JOIN {groups_members} gm ON u.id = gm.userid
                       JOIN {groupings_groups} gg ON gm.groupid = gg.groupid AND gg.groupingid = :groupingid ";
        } else {
            $groupsjoin = "";
        }
    }

    $userfields = user_picture::fields('u');
    $result = $DB->get_records_sql("SELECT $userfields, ua.timecreated, ua.timemodified
                                   FROM {personalschedule_schedules} a
                                   JOIN {user} u ON a.userid = u.id
                                   JOIN {personalschedule_usrattempts} ua ON a.userid = ua.userid
                                       AND ua.personalschedule = a.personalschedule
                            $groupsjoin
                                  WHERE a.personalschedule = :personalscheduleid
                               GROUP BY $userfields", $params);

    return $result;
}

/**
 * Tries to get admin's comment for the specified userid from the specified personalschedule module.
 * Returns false if there is no comment yet.
 * If the comment exists, then returns the comment.
 * @param int $personalscheduleid
 * @param int $userid
 * @return string|bool False if there is no comment yet. If the comment exists, then returns it.
 */
function personalschedule_get_analysis($personalscheduleid, $userid) {
    global $DB;

    try {
        $res = $DB->get_record_sql("SELECT notes
                                      FROM {personalschedule_analysis}
                                     WHERE personalschedule=? AND userid=?", array($personalscheduleid, $userid));
        if ($res) {
            return $res->notes;
        }
        return false;
    } catch (dml_exception $e) {
        return false;
    }
}

/**
 * Updates admin's comment for the specified user on the specified personalschedule module.
 * @param int $personalscheduleid : The module instance id
 * @param int $userid : The id property of the USER object
 * @param string $notes : New notes for the user
 * @return bool True if okay; False if there was an error
 */
function personalschedule_update_analysis($personalscheduleid, $userid, $notes) {
    global $DB;

    try {
        return $DB->execute("UPDATE {personalschedule_analysis}
                                SET notes=?
                              WHERE personalschedule=?
                                AND userid=?", array($notes, $personalscheduleid, $userid));
    } catch (dml_exception $e) {
        return false;
    }
}

/**
 * Sets admin's comment for the specified user on the specified personalschedule module.
 * @param int $personalscheduleid : The module instance id.
 * @param int $userid : The id property of the USER object.
 * @param string $notes : New notes for the user.
 * @return bool|int false if there was an error or record's id if okay.
 */
function personalschedule_add_analysis($personalscheduleid, $userid, $notes) {
    global $DB;

    $record = new stdClass();
    $record->personalschedule = $personalscheduleid;
    $record->userid = $userid;
    $record->notes = $notes;

    try {
        return $DB->insert_record("personalschedule_analysis", $record, false);
    } catch (dml_exception $e) {
        return false;
    }
}

/**
 * Gets number of successfully filled schedules by users in the specified group.
 * @param int $personalscheduleid Module instance id.
 * @param int $groupid Group id to filter users.
 * @param int $groupingid Grouping id to filter users.
 * @return int Number of successfully filled schedules by users in the specified group.
 */
function personalschedule_count_responses($personalscheduleid, $groupid, $groupingid) {
    if ($responses = personalschedule_get_responses($personalscheduleid, $groupid, $groupingid)) {
        return count($responses);
    } else {
        return 0;
    }
}

/**
 * Prints the basic info about users who have filled their own schedule.
 * @param int $cmid : Personalization Module instance Id. It is used for the link to the schedule report.
 * @param array $results : Array with users.
 * @param int $courseid : Course Id.
 */
function personalschedule_print_all_responses($cmid, $results, $courseid) {
    global $OUTPUT;
    $table = new html_table();
    $table->head = array(
        "",
        get_string("name"),
        get_string("timecreated", "personalschedule"),
        get_string("timemodified", "personalschedule")
    );
    $table->align = array("", "left", "left");
    $table->size = array(35, "", "");

    foreach ($results as $a) {
        $table->data[] = array(
            $OUTPUT->user_picture($a, array('courseid' => $courseid)),
            html_writer::link("report.php?action=student&student=$a->id&id=$cmid", fullname($a)),
            userdate($a->timecreated),
            userdate($a->timemodified)
        );
    }

    echo html_writer::table($table);
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the personalschedule.
 * Called automatically by Moodle.
 *
 * @param object $mform form passed by reference.
 */
function personalschedule_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'personalscheduleheader', get_string('modulenameplural', 'personalschedule'));
    $mform->addElement('checkbox', 'reset_personalschedule_answers', get_string('deleteallanswers', 'personalschedule'));
    $mform->addElement('checkbox', 'reset_personalschedule_analysis', get_string('deleteanalysis', 'personalschedule'));
    $mform->disabledIf('reset_personalschedule_analysis', 'reset_personalschedule_answers', 'checked');
}

/**
 * Course reset form defaults. Called automatically by Moodle.
 * @return array Array with default properties and their values.
 */
function personalschedule_reset_course_form_defaults($course) {
    return array(
        'reset_personalschedule_answers' => 1,
        'reset_personalschedule_analysis' => 1
    );
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * personalschedule responses for course $data->courseid.
 * Called automatically by Moodle.
 *
 * @param object $data : The data submitted from the reset course.
 * @return array status array
 * @throws coding_exception
 * @throws dml_exception
 */
function personalschedule_reset_userdata($data) {
    global $DB;

    $componentstr = get_string('modulenameplural', 'personalschedule');
    $status = array();

    $personalschedulessql = "SELECT ch.id
                     FROM {personalschedule} ch
                    WHERE ch.course=?";
    $params = array($data->courseid);

    if (!empty($data->reset_personalschedule_answers)) {
        $DB->delete_records_select('personalschedule_analysis', "personalschedule IN ($personalschedulessql)", $params);
        $DB->delete_records_select('personalschedule_schedules', "personalschedule IN ($personalschedulessql)", $params);
        $DB->delete_records_select('personalschedule_readiness', "personalschedule IN ($personalschedulessql)", $params);
        $DB->delete_records_select('personalschedule_proposes', "personalschedule IN ($personalschedulessql)", $params);
        $DB->delete_records_select('personalschedule_analysis', "personalschedule IN ($personalschedulessql)", $params);
        $DB->delete_records_select('personalschedule_user_props', "personalschedule IN ($personalschedulessql)", $params);
        $DB->delete_records_select('personalschedule_usrattempts', "personalschedule IN ($personalschedulessql)", $params);
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('deleteallanswers',
                'personalschedule'),
            'error' => false
        );
    }

    if (!empty($data->reset_personalschedule_analysis)) {
        $DB->delete_records_select('personalschedule_analysis', "personalschedule IN ($personalschedulessql)", $params);
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('deleteanalysis',
                'personalschedule'),
            'error' => false
        );
    }
    return $status;
}

/**
 * Returns all other caps used in module.
 * Called automatically by Moodle.
 * @return string[] Array with capabilities names.
 */
function personalschedule_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * Returns the features of this activity module.
 * Called automatically by Moodle.
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 */
function personalschedule_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        case FEATURE_SHOW_DESCRIPTION:
            return true;

        default:
            return null;
    }
}

/**
 * This function extends the settings navigation block for the site.
 * Adds the report menu items.
 * Called automatically by Moodle.
 * @param navigation_node $settings
 * @param navigation_node $personalschedulenode
 */
function personalschedule_extend_settings_navigation($settings, $personalschedulenode) {
    global $PAGE;

    if (has_capability('mod/personalschedule:readresponses', $PAGE->cm->context)) {
        $responsesnode = $personalschedulenode->add(get_string("responsereports", "personalschedule"));

        $url = new moodle_url('/mod/personalschedule/report.php', array('id' => $PAGE->cm->id, 'action' => 'students'));
        $responsesnode->add(get_string('participants'), $url);

        if (has_capability('mod/personalschedule:download', $PAGE->cm->context)) {
            $url = new moodle_url('/mod/personalschedule/report.php', array('id' => $PAGE->cm->id, 'action' => 'download'));
            $personalschedulenode->add(get_string('downloadresults', 'personalschedule'), $url);
        }
    }
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param stdClass $personalschedule Personalization object.
 * @param stdClass $course Course object.
 * @param stdClass $cm Course module object.
 * @param stdClass $context Context object.
 * @param string $viewed Which page viewed.
 * @since Moodle 3.0
 */
function personalschedule_view($personalschedule, $course, $cm, $context, $viewed) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $personalschedule->id,
        'courseid' => $course->id,
        'other' => array('viewed' => $viewed)
    );

    $event = \mod_personalschedule\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('personalschedule', $personalschedule);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}


/**
 * Save the user's answers (schedule and age) for the given personalschedule
 *
 * @param stdClass $personalschedule a personalschedule object.
 * @param array $answersrawdata the answers to be saved.
 * @param stdClass $course a course object (required for trigger the submitted event).
 * @param stdClass $context a context object (required for trigger the submitted event).
 * @return bool True if data was successfully added. False if there is something wrong with the data validation.
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function personalschedule_save_answers($personalschedule, $answersrawdata, $course, $context) {
    global $DB, $USER;

    $readiness = array();
    $schedule = array();
    $age = mod_personalschedule_config::AGEMIN;

    $atleastonefree = false;

    foreach ($answersrawdata as $key => $val) {
        if ($key == "userid" && $key == "id") {
            continue;
        }

        if ($key == "age") {
            $age = (int)$val;

            if ($age < mod_personalschedule_config::AGEMIN || $age > mod_personalschedule_config::AGEMAX) {
                $params = array(
                    'context' => $context,
                    'courseid' => $course->id,
                    'other' => array(
                        'personalscheduleid' => $personalschedule->id,
                        'age' => $age,
                    ),
                );
                $event = mod_personalschedule\event\schedule_wrong_age_submitted::create($params);
                $event->trigger();
                return false;
            }
            continue;
        }

        $exploded = explode(mod_personalschedule_config::SEPARATORHIDDENINPUT, $key);
        // We want an item with a template "prefix;key;value".
        if (count($exploded) != 3) {
            continue;
        }

        if ($exploded[1] == mod_personalschedule_config::KEYPREFIXREADINESS) {
            $readiness[(int)$exploded[2]] = (float)$val;
        } else {
            // Keys: [1] - periodidx, [2] - dayidx.
            $schedulevalue = (int)$val;
            $schedule[(int)$exploded[1]][(int)$exploded[2]] = $schedulevalue;

            if ($schedulevalue == mod_personalschedule_config::STATUSFREE) {
                $atleastonefree = true;
            }
        }
    }

    // User can't pass schedule without at least one FREE cell in the schedule.
    // If it happens, then something wrong with client-side validation (in the JS script), so
    // we should trigger an event and return false to the caller.
    if (!$atleastonefree) {
        $params = array(
            'context' => $context,
            'courseid' => $course->id,
            'other' => array('personalscheduleid' => $personalschedule->id),
        );
        $event = mod_personalschedule\event\schedule_wrong_schedule_submitted::create($params);
        $event->trigger();
        return false;
    }

    $sqlfindconditions = array();
    $sqlfindconditions["userid"] = $USER->id;
    $sqlfindconditions["personalschedule"] = $personalschedule->id;

    // Firstly, insert (or update) user's age.
    $ageinsert = new stdClass();
    $ageinsert->userid = $USER->id;
    $ageinsert->personalschedule = $personalschedule->id;
    $ageinsert->age = $age;

    if ($alreadyexistedprop = $DB->get_record("personalschedule_user_props", $sqlfindconditions, "id")) {
        $ageinsert->id = $alreadyexistedprop->id;
        $DB->update_record("personalschedule_user_props", $ageinsert);
    } else {
        $DB->insert_record("personalschedule_user_props", $ageinsert);
    }

    // Now, we should insert readiness statuses.
    $readinessinserts = array();
    foreach ($readiness as $key => $val) {
        $newdata = new stdClass();
        $newdata->userid = $USER->id;
        $newdata->personalschedule = $personalschedule->id;
        $newdata->period_idx = $key;
        $newdata->check_status = $val;

        $readinessinserts[] = $newdata;
    }

    // There are many records, so it's easier to just delete old records and insert the new data.
    // TODO: Check if it more slowly than update existed records.
    if (!empty($readinessinserts)) {
        $DB->delete_records("personalschedule_readiness", $sqlfindconditions);
        $DB->insert_records("personalschedule_readiness", $readinessinserts);
    }

    $scheduleinserts = array();
    foreach ($schedule as $periodidx => $val) {
        foreach ($val as $dayidx => $checkstatus) {
            $newdata = new stdClass();
            $newdata->userid = $USER->id;
            $newdata->personalschedule = $personalschedule->id;
            $newdata->period_idx = $periodidx;
            $newdata->day_idx = $dayidx;
            $newdata->check_status = $checkstatus;

            $scheduleinserts[] = $newdata;
        }
    }

    // Again, it's easier to just delete old records and insert the new data.
    if (!empty($scheduleinserts)) {
        $DB->delete_records("personalschedule_schedules", $sqlfindconditions);
        $DB->insert_records("personalschedule_schedules", $scheduleinserts);
    }

    $usrattemptobject = new stdClass();
    $usrattemptobject->userid = $USER->id;
    $usrattemptobject->personalschedule = $personalschedule->id;
    $usrattemptobject->timemodified = time();

    if ($alreadyexistedelement = $DB->get_record("personalschedule_usrattempts", $sqlfindconditions, "id")) {
        $usrattemptobject->id = $alreadyexistedelement->id;
        $DB->update_record("personalschedule_usrattempts", $usrattemptobject);

    } else {
        $usrattemptobject->timecreated = $usrattemptobject->timemodified;
        $DB->insert_record("personalschedule_usrattempts", $usrattemptobject);
    }

    // Update completion state.
    $cm = get_coursemodule_from_instance('personalschedule', $personalschedule->id, $course->id);
    $completion = new completion_info($course);
    if (isloggedin() && !isguestuser() && $completion->is_enabled($cm)) {
        $completion->update_state($cm, COMPLETION_COMPLETE);
    }

    $params = array(
        'context' => $context,
        'courseid' => $course->id,
        'other' => array('personalscheduleid' => $personalschedule->id)
    );
    $event = mod_personalschedule\event\response_submitted::create($params);
    $event->trigger();

    return true;
}

/**
 * Obtains the completion state for this personalschedule.
 * Called automatically by Moodle.
 * @param object $course Course.
 * @param object $cm Course-module.
 * @param int $userid User ID.
 * @param bool $type Type of comparison (or/and).
 * @return bool True if completed, false if not.
 */
function personalschedule_get_completion_state($course, $cm, $userid, $type) {
    return personalschedule_does_schedule_already_submitted($cm->instance, $userid);
}

/**
 * Tries to get cm_info[] of mod_personalschedule instances from the course.
 * If there aren't instances of the module, then returns false.
 * @param int $courseid Course ID.
 * @return cm_info[]|false mod_personalschedule instances from the course. false if there aren't instances.
 * @throws moodle_exception
 */
function personalschedule_get_personalschedule_cms_by_course_id($courseid) {
    $modinfo = get_fast_modinfo($courseid);

    $foundcms = $modinfo->get_instances_of(mod_personalschedule_config::PERSONALSCHEDULEMODNAME);

    if (empty($foundcms)) {
        return false;
    }
    return $foundcms;
}

/**
 * Send message (with parameter 'notification' set to true) through Messages API to a specific user.
 * @param string $name Message provider name from messages.php.
 * @param int $courseid Course ID.
 * @param int $receiveruserid Receiver User ID.
 * @param string $subject Subject of the message.
 * @param string $fullhtmlmessage Body of the message (can contains HTML).
 * @param string $contexturl Optional context URL.
 * @param string $contexturlname Optional context URL name.
 * @return int|bool The integer ID of the new message or false if there was a problem with submitted data.
 * @throws coding_exception
 */
function personalschedule_send_notification_message(
    $name,
    $courseid,
    $receiveruserid,
    $subject,
    $fullhtmlmessage,
    $contexturl = '',
    $contexturlname = ''
) {

    $supportuser = core_user::get_support_user();

    $message = new \core\message\message();
    $message->courseid = $courseid;
    $message->component = 'mod_personalschedule';
    $message->name = $name;
    $message->userfrom = $supportuser;
    $message->userto = $receiveruserid;
    $message->notification = 1;
    $message->subject = $subject;
    $message->fullmessage = html_to_text($fullhtmlmessage);
    $message->fullmessageformat = FORMAT_HTML;
    $message->fullmessagehtml = $fullhtmlmessage;
    $message->smallmessage = '';
    $message->contexturl = $contexturl;
    $message->contexturlname = $contexturlname;
    return message_send($message);
}

/**
 * Checks if user's schedule exists and return true if so.
 * @param int $personalscheduleid Personalization module instance ID.
 * @param int $userid User ID.
 * @return bool True if completed, false if not.
 */
function personalschedule_does_schedule_already_submitted($personalscheduleid, $userid) {
    global $DB;

    $params = array('userid' => $userid, 'personalschedule' => $personalscheduleid);
    // Data from personalschedule_user_props and personalschedule_usrattempts are not necessary.
    // And in the most default cases, if there are no data in these tables, so the other tables will be
    // without the user's data as well.
    try {
        return $DB->record_exists('personalschedule_schedules', $params) &&
            $DB->record_exists('personalschedule_readiness', $params);
    } catch (dml_exception $e) {
        return false;
    }
}

/**
 * Returns user's schedule with day statuses and readiness info.
 * @param int $personalscheduleid Activity instance ID.
 * @param int $userid User ID.
 * @return mod_personalschedule\items\schedule Schedule object, even if user didn't set schedule yet.
 */
function personalschedule_get_user_schedule($personalscheduleid, $userid) {
    global $DB;
    $conditions = array(
        "personalschedule" => $personalscheduleid,
        "userid" => $userid,
    );

    $schedule = new mod_personalschedule\items\schedule();

    try {
        $schedulerecords = $DB->get_records("personalschedule_schedules", $conditions);
    } catch (dml_exception $e) {
        return $schedule;
    }

    foreach ($schedulerecords as $schedulerecord) {
        $schedule->add_schedule_status($schedulerecord->day_idx, $schedulerecord->period_idx, $schedulerecord->check_status);
    }

    try {
        $readinessrecords = $DB->get_records("personalschedule_readiness", $conditions);
    } catch (dml_exception $e) {
        return $schedule;
    }
    foreach ($readinessrecords as $readinessrecord) {
        $schedule->add_readiness_status($readinessrecord->period_idx, $readinessrecord->check_status);
    }

    return $schedule;
}

/**
 * Returns filtered course activities. If the activity has ignored modname,
 * then it will be skipped.
 * @param stdClass $course : The course instance object.
 * @return cm_info[] Array with filtered course activities.
 * @throws moodle_exception
 */
function personalschedule_get_course_activities($course) {
    $filteredcoursemodules = array();
    $coursemodules = get_fast_modinfo($course)->get_cms();

    foreach ($coursemodules as $key => $coursemodule) {
        if (!in_array($coursemodule->modname, mod_personalschedule_config::IGNOREDMODNAMES)) {
            $filteredcoursemodules[$key] = $coursemodule;
        }
    }

    return $filteredcoursemodules;
}

/**
 * Returns course modules props from the database for specified personalschedule id.
 * @param $personalscheduleid
 * @return stdClass[] Array with stdobjects, all of which contains these properties:
 * duration; category; weight; is_ignored.
 * @throws dml_exception
 */
function personalschedule_get_course_modules_props($personalscheduleid) {
    global $DB;
    return $DB->get_records("personalschedule_cm_props", array("personalschedule" => $personalscheduleid), '',
        'cm, duration, category, weight, is_ignored');
}

/**
 * @param $personalscheduleid int Activity instance id
 * @return int Course total duration in seconds.
 * @throws dml_exception
 */
function personalschedule_get_course_total_duration_in_seconds($personalscheduleid) {
    global $DB;
    $answer = $DB->get_record("personalschedule_cm_props", array("personalschedule" => $personalscheduleid),
        "SUM(duration) as total_duration");
    return $answer->total_duration;
}


/**
 * Returns saved user's age for the specified userid in the specified personalschedule module.
 * If the user don't have a saved age (for example, it's a first time when he opened the module page),
 * this function returns the minimum possible value for age.
 * @param int $personalscheduleid : activity instance
 * @param int $userid : user, which age is need to know
 * @return int saved age value or the minimum possible value (if user don't have a saved age yet)
 */
function personalschedule_get_user_age($personalscheduleid, $userid) {
    global $DB;
    try {
        $age = $DB->get_record("personalschedule_user_props", array(
            "personalschedule" => $personalscheduleid,
            "userid" => $userid
        ), "age");
    } catch (dml_exception $e) {
        return mod_personalschedule_config::AGEMIN;
    }
    return $age == false ? mod_personalschedule_config::AGEMIN : $age->age;
}

/**
 * Returns CSS class name for the table cell with a specific status.
 * @param int $checkstatus Schedule period free status (sleep, busy, free, but as integer value).
 * @return string CSS class name for table cell with this status.
 */
function personalschedule_get_schedule_table_schedule_cell_class($checkstatus) {
    if ($checkstatus == mod_personalschedule_config::STATUSSLEEP) {
        return "schedule-status schedule-sleep";
    } else {
        if ($checkstatus == mod_personalschedule_config::STATUSBUSY) {
            return "schedule-status schedule-busy";
        } else {
            if ($checkstatus == mod_personalschedule_config::STATUSFREE) {
                return "schedule-status schedule-free";
            }
        }
    }

    return "";
}

/**
 * Prints (via echo) the user's schedule HTML table.
 * @param int $personalscheduleid Personalization module instance ID.
 * @param int $userid User ID.
 * @return mod_personalschedule\items\schedule Retrieved schedule object.
 * Can be used for further interactions with the schedule, without the need to receive data from database again.
 */
function personalschedule_print_schedule_table(
    $personalscheduleid,
    $userid
) {
    echo '<table id="scheduling-table" class="table table-sm">';
    echo '<thead>';
    echo '<th class="th-period" scope="col">Периоды</th>';
    for ($dayidx = 1; $dayidx <= 7; $dayidx++) {
        echo '<th class="th-day" scope="col">' .
            mod_personalschedule_proposer_ui::personalschedule_get_day_localize_from_idx($dayidx) .
            '</th>';
    }
    echo '<th class="th-readiness" scope="col">Готовность</th>';
    echo '</thead>';

    $userschedule = personalschedule_get_user_schedule($personalscheduleid, $userid);
    $schedulestatuses = $userschedule->get_statuses();
    $schedulereadiness = $userschedule->get_readinesses();

    for ($periodidx = 0; $periodidx < 24; $periodidx++) {
        echo '<tr>';
        echo '<td>' . mod_personalschedule_proposer_ui::personalschedule_get_period_localize_from_idx($periodidx) . '</td>';
        for ($dayidx = 1; $dayidx <= 7; $dayidx++) {
            $checkstatus = $schedulestatuses[$dayidx][$periodidx];
            $cellclass = personalschedule_get_schedule_table_schedule_cell_class($checkstatus);
            $hiddeninputname = mod_personalschedule_config::PREFIXHIDDENINPUT . mod_personalschedule_config::SEPARATORHIDDENINPUT .
                $periodidx . mod_personalschedule_config::SEPARATORHIDDENINPUT . $dayidx;
            echo '<td class="schedule-selectable ' . $cellclass . '"><span>' . $checkstatus .
                '</span><input type="hidden" autocomplete="off" name="' . $hiddeninputname .
                '" value="' . $checkstatus . '" /></td>';
        }

        $readiness = $schedulereadiness[$periodidx];
        $hiddeninputname = mod_personalschedule_config::PREFIXHIDDENINPUT . mod_personalschedule_config::SEPARATORHIDDENINPUT .
            mod_personalschedule_config::KEYPREFIXREADINESS . mod_personalschedule_config::SEPARATORHIDDENINPUT . $periodidx;
        echo '<td class="schedule-selectable schedule-readiness"><span>' . $readiness .
            '</span><input type="hidden" autocomplete="off" name="' . $hiddeninputname .
            '" value="' . $readiness . '" /></td>';
        echo '</tr>';
    }

    echo '</table>';
    return $userschedule;
}