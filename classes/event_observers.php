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
 * Event observers.
 *
 * @package    mod_personalschedule
 * @copyright  2019 onwards Vladislav Kovalev snouwer@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_personalschedule;
use html_writer;
use mod_personalschedule_config;

defined('MOODLE_INTERNAL') || die();

class event_observers {
    /**
     * Course module was created.
     *
     * @param \core\event\course_module_created $event The event.
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function course_module_created($event) {

        global $DB, $CFG;

        if (in_array($event->other['modulename'], mod_personalschedule_config::ignoredmodnames)) {
            return;
        }

        $courseinfo = $DB->get_record("course", array("id" => $event->courseid),"fullname, shortname");
        $personalschedules = personalschedule_get_personalschedule_cms_by_course_id($event->courseid);

        if ($personalschedules === false) {
            return;
        }

        $courseurl = course_get_url($event->courseid);
        $courselink = html_writer::link($courseurl, $courseinfo->fullname);

        $newmoduleurl = sprintf("$CFG->wwwroot/mod/%s/view.php?id=%s",
            $event->other['modulename'], $event->contextinstanceid);
        $newmoduleviewlink = html_writer::link($newmoduleurl, $event->other['name']);

        $subject = get_string('event_cmcreated_title', 'personalschedule', $courseinfo->shortname);
        foreach ($personalschedules as $personalschedulecm) {
            $personalscheduleediturl = sprintf("$CFG->wwwroot/course/modedit.php?update=%d", $personalschedulecm->id);
            $fullmessage = sprintf(get_string('event_cmcreated_message', 'personalschedule'),
                $courselink,
                $newmoduleviewlink,
                $event->other['modulename'],
                html_writer::link($personalscheduleediturl,
                    get_string('event_cmcreated_message_update', 'personalschedule')
                )
            );

            personalschedule_send_notification_message(
                'coursemodulecreated', $event->courseid, $event->userid, $subject, $fullmessage,
                $courseurl, $courseinfo->shortname);
        }
    }

    /**
     * Course module was deleted.
     *
     * @param \core\event\course_module_deleted $event The event.
     * @throws \dml_exception
     */
    public static function course_module_deleted($event) {
        global $DB;
        $DB->delete_records("personalschedule_cm_props", array("cm" => $event->contextinstanceid));
        $DB->delete_records("personalschedule_proposes", array("cm" => $event->contextinstanceid));
    }

    /**
     * User was deleted.
     *
     * @param \core\event\user_deleted $event The event.
     * @throws \dml_exception
     */
    public static function user_deleted($event) {
        global $DB;
        $DB->delete_records("personalschedule_analysis", array("userid" => $event->userid));
        $DB->delete_records("personalschedule_readiness", array("userid" => $event->userid));
        $DB->delete_records("personalschedule_proposes", array("userid" => $event->userid));
        $DB->delete_records("personalschedule_schedules", array("userid" => $event->userid));
        $DB->delete_records("personalschedule_user_props", array("userid" => $event->userid));
        $DB->delete_records("personalschedule_usrattempts", array("userid" => $event->userid));
        $DB->delete_records("personalschedule_analysis", array("userid" => $event->userid));
    }

    /**
     * User was unenrolled.
     *
     * @param \core\event\user_enrolment_deleted $event The event.
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function user_enrolment_deleted($event) {
        global $DB;

        $personalschedules = personalschedule_get_personalschedule_cms_by_course_id($event->courseid);

        if ($personalschedules === false) {
            return;
        }

        foreach ($personalschedules as $personalschedule) {
            $conditionsarray = array(
                'userid' => $event->relateduserid,
                'personalschedule' => $personalschedule->id,
            );

            $DB->delete_records("personalschedule_analysis", $conditionsarray);
            $DB->delete_records("personalschedule_readiness", $conditionsarray);
            $DB->delete_records("personalschedule_proposes", $conditionsarray);
            $DB->delete_records("personalschedule_schedules", $conditionsarray);
            $DB->delete_records("personalschedule_user_props", $conditionsarray);
            $DB->delete_records("personalschedule_usrattempts", $conditionsarray);
            $DB->delete_records("personalschedule_analysis", $conditionsarray);
        }
    }
}
