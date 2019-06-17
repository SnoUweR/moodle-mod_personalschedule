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
 * This file is responsible for sending notifications from the users to course admins.
 *
 * @package   mod_personalschedule
 * @copyright 2019 onwards Vladislav Kovalev  snouwer@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");


$id = required_param('id', PARAM_INT);    // Course Module ID.

if (! $cm = get_coursemodule_from_id('personalschedule', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

$PAGE->set_url('/mod/personalschedule/admin_notify.php', array('id' => $id));
require_login($course, false, $cm);
$context = context_module::instance($cm->id);

require_capability('mod/personalschedule:participate', $context);

if (! $personalschedule = $DB->get_record("personalschedule", array("id" => $cm->instance))) {
    print_error('invalidpersonalscheduleid', 'personalschedule');
}

$strpersonalschedule = get_string("modulename", "personalschedule");
$PAGE->set_title($personalschedule->name);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($personalschedule->name);

// Check the personalschedule has already been filled out.
$personalschedulealreadydone = personalschedule_does_schedule_already_submitted($personalschedule->id, $USER->id);
if ($personalschedulealreadydone) {

    $issubmit = optional_param("submit", 0, PARAM_BOOL);
    if ($issubmit) {
        $contextmodule = context_module::instance($cm->id);
        $adminids = get_users_by_capability($contextmodule, 'moodle/course:update', 'u.id');
        if (count($adminids) == 0) {
            // TODO: Show error message.
        } else {
            $subject = get_string('adminnotifyemail_title', 'personalschedule', $course->shortname);
            $courseurl = course_get_url($course->id);
            $courselink = html_writer::link($courseurl, $course->fullname);
            $userurl = sprintf("$CFG->wwwroot/user/view.php?id=%d&course=%d", $USER->id, $course->id);
            $messagesendurl = sprintf("$CFG->wwwroot/message/index.php?id=%d", $USER->id);
            $fullmessage = sprintf(get_string('adminnotifyemail_message', 'personalschedule'),
                html_writer::link($userurl, sprintf("%s %s", $USER->lastname, $USER->firstname)),
                $courselink,
                html_writer::link($messagesendurl,
                    get_string('adminnotifyemail_message_this', 'personalschedule'))
            );

            // For now, it's sending message only for the first admin of the course.
            $receiveruserid = reset($adminids)->id;
            personalschedule_send_notification_message(
                'coursemodulecreated', $course->id, $receiveruserid, $subject, $fullmessage,
                $courseurl, $course->shortname);

            notice(get_string('adminnotify_success', 'personalschedule'), "$CFG->wwwroot/my");
        }
    } else {
        echo html_writer::tag("p",
            get_string('adminnotify_description', 'personalschedule', $course->fullname));
        $url = new moodle_url("$CFG->wwwroot/mod/personalschedule/admin_notify.php", array(
            'id' => $id,
            'submit' => 1,
            ));
        $button = $OUTPUT->single_button($url,
            get_string('sendnotifytoadmin', 'personalschedule'), "get");
        echo $button;
    }
} else {
    echo $OUTPUT->notification(get_string("adminnotifycantsendwithoutschedule", "personalschedule"));
}

echo $OUTPUT->footer();


