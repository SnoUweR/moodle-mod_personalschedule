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
 * This file is responsible for producing the personalschedule reports.
 *
 * @package   mod_personalschedule
 * @copyright 2019 onwards Vladislav Kovalev  snouwer@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_personalschedule\items\proposed_activity_object;

require(__DIR__ . '/../../config.php');
require_once("lib.php");

// Check that all the parameters have been provided.

$id = required_param('id', PARAM_INT); // Course Module ID.
$action = optional_param('action', '', PARAM_ALPHA); // What we want to look at.
$student = optional_param('student', 0, PARAM_INT); // Student ID.
$notes = optional_param('notes', '', PARAM_RAW); // Save teachers notes.

if (!$cm = get_coursemodule_from_id('personalschedule', $id)) {
    print_error('invalidcoursemodule');
}

if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

$url = new moodle_url('/mod/personalschedule/report.php', array('id' => $id));
if ($action !== '') {
    $url->param('action', $action);
}
if ($student !== 0) {
    $url->param('student', $student);
}
if ($notes !== '') {
    $url->param('notes', $notes);
}
$PAGE->set_url($url);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/personalschedule:readresponses', $context);

if (!$personalschedule = $DB->get_record("personalschedule", array("id" => $cm->instance))) {
    print_error('invalidpersonalscheduleid', 'personalschedule');
}

$strreport = get_string("report", "personalschedule");
$strpersonalschedule = get_string("modulename", "personalschedule");
$strpersonalschedules = get_string("modulenameplural", "personalschedule");
$strdownload = get_string("download", "personalschedule");
$strnotes = get_string("notes", "personalschedule");

switch ($action) {
    case 'download':
        $PAGE->navbar->add(get_string('downloadresults', 'personalschedule'));
        break;
    case 'students':
        $PAGE->navbar->add($strreport);
        $PAGE->navbar->add(get_string('participants'));
        break;
    case '':
        $PAGE->navbar->add($strreport);
        break;
    default:
        $PAGE->navbar->add($strreport);
        break;
}

$PAGE->set_title("$course->shortname: " . format_string($personalschedule->name));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($personalschedule->name);

// Check to see if groups are being used in this module.
if ($groupmode = groups_get_activity_groupmode($cm)) {
    $menuaction = $action == "student" ? "students" : $action;
    $currentgroup = groups_get_activity_group($cm, true);
    groups_print_activity_menu($cm,
        $CFG->wwwroot . "/mod/personalschedule/report.php?id=$cm->id&amp;action=$menuaction");
} else {
    $currentgroup = 0;
}

$params = array(
    'objectid' => $personalschedule->id,
    'context' => $context,
    'courseid' => $course->id,
    'relateduserid' => $student,
    'other' => array('action' => $action, 'groupid' => $currentgroup)
);
$event = mod_personalschedule\event\report_viewed::create($params);
$event->trigger();

if ($currentgroup) {
    $users = get_users_by_capability($context, 'mod/personalschedule:participate',
        '', '', '', '', $currentgroup, null, false);
} else {
    if (!empty($cm->groupingid)) {
        $groups = groups_get_all_groups($courseid, 0, $cm->groupingid);
        $groups = array_keys($groups);
        $users = get_users_by_capability($context, 'mod/personalschedule:participate',
            '', '', '', '', $groups, null, false);
    } else {
        $users = get_users_by_capability($context, 'mod/personalschedule:participate',
            '', '', '', '', '', null, false);
        $group = false;
    }
}

$groupingid = $cm->groupingid;

echo $OUTPUT->box_start("generalbox boxaligncenter");

echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=students&amp;id=$id\">" . get_string('participants') . "</a>";
if (has_capability('mod/personalschedule:download', $context)) {
    echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=download&amp;id=$id\">$strdownload</a>";
}
if (empty($action)) {
    $action = "summary";
}

echo $OUTPUT->box_end();

echo $OUTPUT->spacer(array('height' => 30, 'width' => 30, 'br' => true)); // TODO: Should be done with CSS instead.

// Functions to print report.

/**
 * @param moodle_database $DB
 * @param $student
 * @param core_renderer $OUTPUT
 * @param $notes
 * @param $personalschedule
 * @param $course
 * @param $strnotes
 * @param stdClass $cm
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function show_student_report(
    $DB,
    $student,
    $OUTPUT,
    $notes,
    $personalschedule,
    $course,
    $strnotes,
    stdClass $cm
) {
    if (!$user = $DB->get_record("user", array("id" => $student))) {
        print_error('invaliduserid');
    }

    echo $OUTPUT->heading(get_string("analysisof", "personalschedule", fullname($user)), 3);

    if ($notes != '' and confirm_sesskey()) {
        if (personalschedule_get_analysis($personalschedule->id, $user->id)) {
            if (!personalschedule_update_analysis($personalschedule->id, $user->id, $notes)) {
                echo $OUTPUT->notification(get_string("errorunabletosavenotes", "personalschedule"), "notifyproblem");
            } else {
                echo $OUTPUT->notification(get_string("savednotes", "personalschedule"), "notifysuccess");
            }
        } else {
            if (!personalschedule_add_analysis($personalschedule->id, $user->id, $notes)) {
                echo $OUTPUT->notification(get_string("errorunabletosavenotes", "personalschedule"), "notifyproblem");
            } else {
                echo $OUTPUT->notification(get_string("savednotes", "personalschedule"), "notifysuccess");
            }
        }
    }

    echo "<p class=\"centerpara\">";
    echo $OUTPUT->user_picture($user, array('courseid' => $course->id));
    echo "</p>";

    // TODO: Локализация.
    echo "<h4>Заполненное расписание пользователя</h4>";
    echo "<p>При щелчке на ячейку, отобразится список предложенных элементов в этот день</p>";
    personalschedule_print_schedule_table($personalschedule->id, $user->id);

    $proposeditems = mod_personalschedule_proposer::get_all_proposed_items_from_cache(
        $personalschedule->id, $course->id, $user->id);

    echo $OUTPUT->box_start("generalbox boxaligncenter");

    /** @var proposed_activity_object[][][] $grouppedproposeditems
     * First key - weekidx;
     * Second key - dayidx;
     * Value - proposed_activity_object[].
     */
    $grouppedproposeditems = array();

    printf("%d элементов<br>", count($proposeditems));

    foreach ($proposeditems as $proposeditem) {
        if (!key_exists($proposeditem->dayperiodinfo->weekidx, $grouppedproposeditems)) {
            $grouppedproposeditems[$proposeditem->dayperiodinfo->weekidx] = array();
        }

        if (!key_exists($proposeditem->dayperiodinfo->dayidx, $grouppedproposeditems[$proposeditem->dayperiodinfo->weekidx])) {
            $grouppedproposeditems[$proposeditem->dayperiodinfo->weekidx][$proposeditem->dayperiodinfo->dayidx] = array();
        }

        $grouppedproposeditems[$proposeditem->dayperiodinfo->weekidx][$proposeditem->dayperiodinfo->dayidx][] = $proposeditem;
    }

    $userviewsinfo = mod_personalschedule_proposer::get_user_views_info($user->id, $course->id);

    $userscheduletimeinfo = personalschedule_get_schedule_creation_modified_time($personalschedule->id, $user->id);
    $dateformat = get_string('strftimedatefullshort', 'langconfig');

    foreach ($grouppedproposeditems as $weekidx => $weekproposeditems) {
        // TODO: Локализация.
        foreach ($weekproposeditems as $dayidx => $dayproposeditems) {
            $daylocalizedname =
                mod_personalschedule_proposer_ui::personalschedule_get_day_localize_from_idx($dayidx);
            echo "<p>$daylocalizedname</p>";

            $dayfulltimecreated = $userscheduletimeinfo->timecreated + (($weekidx ) * 7 * 24 * 60 * 60) +
                (($dayidx * 0) * 24 * 60 * 60);
            echo html_writer::tag("p", userdate($dayfulltimecreated, $dateformat));
            echo html_writer::tag("p", userdate($userscheduletimeinfo->timecreated, $dateformat));
            foreach ($dayproposeditems as $proposeditem) {
                $activityname = $proposeditem->activity->name;
                echo "<p>";
                echo "<span>$activityname</span> ";
                if (key_exists($proposeditem->activity->id, $userviewsinfo)) {
                    $userviewinfo = $userviewsinfo[$proposeditem->activity->id];
                    if ($userviewinfo instanceof proposed_activity_object) {
                        if ($userviewinfo->attempts > 0) {
                            if ($userviewinfo->ispassed) {
                                echo "<span>Пройдено</span>";
                            } else {
                                if ($userviewinfo->notrated) {
                                    echo "<span>Пройдено, но еще не оценено</span>";
                                } else {
                                    echo "<span>Провалено</span>";
                                }
                            }
                        } else {
                            echo "<span>Просмотрено</span>";
                        }
                    } else {
                        if ($userviewinfo->actions == $proposeditem->actions) {
                            echo "<span>Не просмотрено</span>";
                        }
                    }
                } else {
                    echo "<span>Не просмотрено</span>";
                }
                echo "</p>";
                echo "<hr>";
            }
        }
    }

    echo $OUTPUT->box_end();

    if ($rs = personalschedule_get_analysis($personalschedule->id, $user->id)) {
        $notes = $rs;
    } else {
        $notes = "";
    }
    echo "<hr noshade=\"noshade\" size=\"1\" />";
    echo "<div class='studentreport'>";
    echo "<form action=\"report.php\" method=\"post\">";
    echo "<h3>$strnotes:</h3>";
    echo "<blockquote>";
    echo "<textarea class=\"form-control\" name=\"notes\" rows=\"10\" cols=\"60\">";
    p($notes);
    echo "</textarea><br />";
    echo "<input type=\"hidden\" name=\"action\" value=\"student\" />";
    echo "<input type=\"hidden\" name=\"sesskey\" value=\"" . sesskey() . "\" />";
    echo "<input type=\"hidden\" name=\"student\" value=\"$student\" />";
    echo "<input type=\"hidden\" name=\"id\" value=\"$cm->id\" />";
    echo "<input type=\"submit\" class=\"btn btn-primary\" value=\"" . get_string("savechanges") . "\" />";
    echo "</blockquote>";
    echo "</form>";
    echo "</div>";
}

/**
 * @param bootstrap_renderer $OUTPUT
 * @param $personalschedule
 * @param $currentgroup
 * @param $groupingid
 * @param stdClass $cm
 * @param $course
 * @throws coding_exception
 */
function show_students_report(
    $OUTPUT,
    $personalschedule,
    $currentgroup,
    $groupingid,
    $cm,
    $course
) {
    echo $OUTPUT->heading(get_string("reportuserstitle", "personalschedule"), 3);

    if (!$results = personalschedule_get_responses($personalschedule->id, $currentgroup, $groupingid)) {
        echo $OUTPUT->notification(get_string("nobodyyet", "personalschedule"));
    } else {
        personalschedule_print_all_responses($cm->id, $results, $course->id);
    }
}

/**
 * @param bootstrap_renderer $OUTPUT
 * @param $strdownload
 * @param context_module $context
 * @param $personalschedule
 * @param $currentgroup
 * @param $groupingid
 * @param stdClass $cm
 * @throws coding_exception
 * @throws moodle_exception
 * @throws required_capability_exception
 */
function show_download_report(
    $OUTPUT,
    $strdownload,
    $context,
    $personalschedule,
    $currentgroup,
    $groupingid,
    stdClass $cm
) {
    echo $OUTPUT->heading($strdownload, 3);

    require_capability('mod/personalschedule:download', $context);

    $numusers = personalschedule_count_responses($personalschedule->id, $currentgroup, $groupingid);
    if ($numusers > 0) {
        echo html_writer::tag('p', get_string("downloadinfo", "personalschedule"), array('class' => 'centerpara'));

        echo $OUTPUT->container_start('reportbuttons');
        $options = array();
        $options["id"] = "$cm->id";
        $options["group"] = $currentgroup;

        $options["type"] = "ods";
        echo $OUTPUT->single_button(new moodle_url("download.php", $options), get_string("downloadods"));

        $options["type"] = "xls";
        echo $OUTPUT->single_button(new moodle_url("download.php", $options), get_string("downloadexcel"));

        $options["type"] = "txt";
        echo $OUTPUT->single_button(new moodle_url("download.php", $options), get_string("downloadtext"));
        echo $OUTPUT->container_end();

    } else {
        echo html_writer::tag('p', get_string("nobodyyet", "personalschedule"),
            array('class' => 'centerpara'));
    }
}

switch ($action) {
    case "students":
        show_students_report($OUTPUT, $personalschedule, $currentgroup, $groupingid, $cm, $course);
        break;

    case "student":
        show_student_report($DB, $student, $OUTPUT, $notes, $personalschedule, $course, $strnotes, $cm);
        break;

    case "download":
        show_download_report($OUTPUT, $strdownload, $context, $personalschedule, $currentgroup, $groupingid, $cm);
        break;

}
echo $OUTPUT->footer();

