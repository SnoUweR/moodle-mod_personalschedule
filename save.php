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
 * This file is responsible for saving the user's schedule and displaying the final message.
 *
 * @package   mod_personalschedule
 * @copyright 2019 onwards Vladislav Kovalev  snouwer@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');

if (!$formdata = data_submitted() or !confirm_sesskey()) {
    print_error('cannotcallscript');
}

$id = required_param('id', PARAM_INT); // Course Module ID.

if (!$cm = get_coursemodule_from_id('personalschedule', $id)) {
    print_error('invalidcoursemodule');
}

if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

$PAGE->set_url('/mod/personalschedule/save.php', array('id' => $id));
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/personalschedule:participate', $context);

if (!$personalschedule = $DB->get_record("personalschedule", array("id" => $cm->instance))) {
    print_error('invalidpersonalscheduleid', 'personalschedule');
}



$strpersonalschedulesaved = get_string('personalschedulesaved', 'personalschedule');

$PAGE->set_title($strpersonalschedulesaved);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($personalschedule->name);

if (personalschedule_does_schedule_already_submitted($personalschedule->id, $USER->id)) {
    echo html_writer::tag('p', get_string('save_willupdatenextday', 'personalschedule'));
}

personalschedule_save_answers($personalschedule, $formdata, $course, $context);
personalschedule_send_total_course_schedule($course, $personalschedule->id, $cm, $USER->id);

notice(get_string("thanksforanswers", "personalschedule", $USER->firstname), "$CFG->wwwroot/my");

exit;



