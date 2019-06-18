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
 * This file is responsible for displaying the personalschedule
 *
 * @package   mod_personalschedule
 * @copyright 2019 onwards Vladislav Kovalev  snouwer@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");


$id = required_param('id', PARAM_INT); // Course Module ID.

$forceedit = optional_param('edit', false, PARAM_BOOL);

if (! $cm = get_coursemodule_from_id('personalschedule', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

$PAGE->set_url('/mod/personalschedule/view.php', array('id' => $id));
require_login($course, false, $cm);
$context = context_module::instance($cm->id);

require_capability('mod/personalschedule:participate', $context);

if (! $personalschedule = $DB->get_record("personalschedule", array("id" => $cm->instance))) {
    print_error('invalidpersonalscheduleid', 'personalschedule');
}

// Check the personalschedule hasn't already been filled out.
$personalschedulealreadydone = personalschedule_does_schedule_already_submitted($personalschedule->id, $USER->id);
if ($personalschedulealreadydone) {
    // Trigger course_module_viewed event and completion.
    personalschedule_view($personalschedule, $course, $cm, $context, 'graph');
} else {
    personalschedule_view($personalschedule, $course, $cm, $context, 'form');
}

$strpersonalschedule = get_string("modulename", "personalschedule");
$PAGE->set_title($personalschedule->name);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($personalschedule->name);

// Check to see if groups are being used in this personalschedule.
if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used.
    $currentgroup = groups_get_activity_group($cm);
} else {
    $currentgroup = 0;
}
$groupingid = $cm->groupingid;

if (has_capability('mod/personalschedule:readresponses', $context) || ($groupmode == VISIBLEGROUPS)) {
    $currentgroup = 0;
}

if (!has_capability('mod/personalschedule:readresponses', $context) && !$cm->visible) {
    notice(get_string("activityiscurrentlyhidden"));
}

if (!is_enrolled($context)) {
    echo $OUTPUT->notification(get_string("guestsnotallowed", "personalschedule"));
}

if ($personalschedulealreadydone && !$forceedit) {
    echo "<div class=\"reportlink\"><a href=\"view.php?id=$cm->id&edit=1\">".
        get_string("view_edit_schedule", "personalschedule")."</a></div>";

    echo mod_personalschedule_proposer_ui::get_proposed_table($course, $USER->id, $cm);
} else {
    $totalcoursedurationseconds = personalschedule_get_course_total_duration_in_seconds($personalschedule->id);
    $totalcoursedurationminutes = floor($totalcoursedurationseconds / 60);
    $totalcoursedurationhours = floor($totalcoursedurationminutes / 60);
    $totalcoursedurationdays = floor($totalcoursedurationhours / 24);

    $totalcoursedurationdisplayvalue = $totalcoursedurationseconds;

    $totalcoursedurationtext = get_string('durationformat_seconds', 'personalschedule');
    if ($totalcoursedurationseconds >= 60) {
        $totalcoursedurationtext = get_string('durationformat_minutes', 'personalschedule');
        $totalcoursedurationdisplayvalue = $totalcoursedurationminutes;
        if ($totalcoursedurationminutes >= 60) {
            $totalcoursedurationdisplayvalue = $totalcoursedurationhours;
            $totalcoursedurationtext = get_string('durationformat_hours', 'personalschedule');
            if ($totalcoursedurationhours >= 24) {
                $totalcoursedurationdisplayvalue = $totalcoursedurationdays;
                $totalcoursedurationtext = get_string('durationformat_days', 'personalschedule');
            }
        }
    }

    echo "<form method=\"post\" action=\"save.php\" id=\"personalscheduleform\">";
    echo '<div>';
    echo "<input type=\"hidden\" name=\"id\" value=\"$id\" />";
    echo "<input type=\"hidden\" name=\"sesskey\" value=\"".sesskey()."\" />";
    echo $OUTPUT->box(format_module_intro('personalschedule', $personalschedule, $cm->id),
        'generalbox boxaligncenter bowidthnormal', 'intro');
    echo get_string('view_introtext',  'personalschedule');

    $age = personalschedule_get_user_age($cm->instance, $USER->id);

    echo get_string('view_label_age', 'personalschedule').
        "<input type=\"number\" id='age-input' name=\"age\" value=\"$age\" />";

    echo "<input type=\"hidden\" id=\"total-course-duration-hours-value\" name=\"total-course-duration-hours-value\" value=\"".
        $totalcoursedurationhours."\" />";

    echo "<hr>";

    echo get_string('view_schedule_help', 'personalschedule');

    $schedule = personalschedule_print_schedule_table($personalschedule->id, $USER->id);

    $elapsedcoursehours = $totalcoursedurationhours;
    $scheduledcoursedurationdays = 1;
    $atleastonefree = false;
    $scheduledata = $schedule->get_statuses();

    while ($elapsedcoursehours > 0) {
        for ($dayidx = 1; $dayidx <= 7; $dayidx++) {
            for ($periodidx = 0; $periodidx < 24; $periodidx++) {
                $checkstatus = $scheduledata[$dayidx][$periodidx];
                if ($checkstatus == mod_personalschedule_config::STATUSFREE) {
                    $elapsedcoursehours--;
                    $atleastonefree = true;
                }
            }

            if ($elapsedcoursehours <= 0) {
                break;
            }
            $scheduledcoursedurationdays++;
        }

        if (!$atleastonefree) {
            break;
        }
    }

    echo '<p>'. get_string('totalcourseduration_const', 'personalschedule').
        '<span id="total-course-duration-value">'.$totalcoursedurationdisplayvalue.'</span>'.$totalcoursedurationtext."</p>";

    $scheduledcoursedurationdaysstring = get_string('scheduledcourseduration_days', 'personalschedule');
    $scheduledcoursedurationdaysvaluetoshow = $atleastonefree ? $scheduledcoursedurationdays : "-";
    echo '<p>'. get_string('scheduledcourseduration_const', 'personalschedule').
        "<span id='scheduled-course-duration-days'>".$scheduledcoursedurationdaysvaluetoshow."</span>".
        $scheduledcoursedurationdaysstring."</p>";

    echo '<br />';
    echo '<input type="submit" class="btn btn-primary" value="'.
        get_string("clicktocontinue", "personalschedule").'" />';
    echo '</div>';
    echo "</form>";

    $PAGE->requires->js_call_amd('mod_personalschedule/validation', 'addFunctionalToTable');
    $PAGE->requires->js_call_amd('mod_personalschedule/validation', 'addHandlersToForm');
}

echo $OUTPUT->footer();
