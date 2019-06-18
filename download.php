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
 * This file is responsible for producing the downloadable versions of a personalschedule
 * module.
 *
 * @package   mod_personalschedule
 * @copyright 2019 onwards Vladislav Kovalev  snouwer@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");

// Check that all the parameters have been provided.

$id    = required_param('id', PARAM_INT); // Course Module ID.
$type  = optional_param('type', 'xls', PARAM_ALPHA);
$group = optional_param('group', 0, PARAM_INT);

if (! $cm = get_coursemodule_from_id('personalschedule', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

$context = context_module::instance($cm->id);

$PAGE->set_url('/mod/personalschedule/download.php', array('id' => $id, 'type' => $type, 'group' => $group));

require_login($course, false, $cm);
require_capability('mod/personalschedule:download', $context);

if (! $personalschedule = $DB->get_record("personalschedule", array("id" => $cm->instance))) {
    print_error('invalidpersonalscheduleid', 'personalschedule');
}

$params = array(
    'objectid' => $personalschedule->id,
    'context' => $context,
    'courseid' => $course->id,
    'other' => array('type' => $type, 'groupid' => $group)
);
$event = \mod_personalschedule\event\report_downloaded::create($params);
$event->trigger();

// Check to see if groups are being used in this personalschedule.

$groupmode = groups_get_activity_groupmode($cm);   // Groups are being used.

if ($groupmode and $group) {
    $users = get_users_by_capability($context, 'mod/personalschedule:participate', '',
        '', '', '', $group, null, false);
} else {
    $users = get_users_by_capability($context, 'mod/personalschedule:participate', '',
        '', '', '', '', null, false);
    $group = false;
}

// Get the actual questions from the database.
$schedules = $DB->get_records("personalschedule_schedules");

$outputdata = array();

foreach ($schedules as $schedule) {
    if (!$u = $DB->get_record("user", array("id" => $schedule->userid))) {
        print_error('invaliduserid');
    }

    $outputdataitem = array();
    $outputdataitem['personalschedule'] = $schedule->personalschedule;
    $outputdataitem['firstname'] = $u->firstname;
    $outputdataitem['lastname'] = $u->lastname;
    $outputdataitem['userid'] = $schedule->userid;
    $outputdataitem['period_idx'] = $schedule->period_idx;
    $outputdataitem['day_idx'] = $schedule->day_idx;
    $outputdataitem['check_status'] = $schedule->check_status;

    $outputdata[] = $outputdataitem;
}


// Output the file as a valid ODS spreadsheet if required.
$coursecontext = context_course::instance($course->id);
$courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));

if ($type == "ods") {
    output_ods_file($CFG, $outputdata, $courseshortname, $personalschedule, $DB);
    exit;
}

if ($type == "xls") {
    output_xls_file($CFG, $outputdata, $courseshortname, $personalschedule, $DB);
    exit;
}

output_txt_file($outputdata, $courseshortname, $personalschedule, $DB);
exit;


/**
 * @param $outputdata
 * @param $courseshortname
 * @param $personalschedule
 * @param moodle_database $DB
 */
function output_txt_file(
    $outputdata,
    $courseshortname,
    $personalschedule,
    moodle_database $DB
) {
    // Print header to force download.

    header("Content-Type: application/download\n");

    $downloadfilename = clean_filename(strip_tags($courseshortname . ' ' . format_string($personalschedule->name,
            true)));
    header("Content-Disposition: attachment; filename=\"$downloadfilename.txt\"");

    // Print names of all the fields.

    foreach ($outputdata[0] as $name => $value) {
        echo $name . "\t";
    }
    echo "\n";

    foreach ($outputdata as $outputdataitem) {
        foreach ($outputdataitem as $value) {
            echo $value . "\t";
        }
        echo "\n";
    }
}

/**
 * @param stdClass $CFG
 * @param $outputdata
 * @param $courseshortname
 * @param $personalschedule
 * @param moodle_database $DB
 */
function output_ods_file(
    stdClass $CFG,
    $outputdata,
    $courseshortname,
    $personalschedule,
    moodle_database $DB
) {
    require_once("$CFG->libdir/odslib.class.php");

    // Calculate file name.
    $downloadfilename = clean_filename(strip_tags($courseshortname . ' ' . format_string($personalschedule->name,
                true))) . '.ods';
    // Creating a workbook.
    $workbook = new MoodleODSWorkbook("-");
    // Sending HTTP headers.
    $workbook->send($downloadfilename);
    // Creating the first worksheet.
    $myxls = $workbook->add_worksheet(core_text::substr(strip_tags(format_string($personalschedule->name, true)), 0,
        31));

    $header = array();

    foreach ($outputdata[0] as $name => $value) {
        $header[] = $name;
    }

    $col = 0;
    foreach ($header as $item) {
        $myxls->write_string(0, $col++, $item);
    }

    $row = 0;
    foreach ($outputdata as $outputdataitem) {
        $col = 0;
        $row++;
        foreach ($outputdataitem as $value) {
            $myxls->write_string($row, $col++, $value);
        }
    }

    $workbook->close();
}


/**
 * @param stdClass $CFG
 * @param $outputdata
 * @param $courseshortname
 * @param $personalschedule
 * @param moodle_database $DB
 */
function output_xls_file(
    stdClass $CFG,
    $outputdata,
    $courseshortname,
    $personalschedule,
    moodle_database $DB
) {

    require_once("$CFG->libdir/excellib.class.php");

    // Calculate file name.
    $downloadfilename = clean_filename(strip_tags($courseshortname . ' ' . format_string($personalschedule->name,
                true))) . '.xls';
    // Creating a workbook.
    $workbook = new MoodleExcelWorkbook("-");
    // Sending HTTP headers.
    $workbook->send($downloadfilename);
    // Creating the first worksheet.
    $myxls = $workbook->add_worksheet(core_text::substr(strip_tags(format_string($personalschedule->name, true)), 0,
        31));

    $header = array();

    foreach ($outputdata[0] as $name => $value) {
        $header[] = $name;
    }

    $col = 0;
    foreach ($header as $item) {
        $myxls->write_string(0, $col++, $item);
    }

    $row = 0;
    foreach ($outputdata as $outputdataitem) {
        $col = 0;
        $row++;
        foreach ($outputdataitem as $value) {
            $myxls->write_string($row, $col++, $value);
        }
    }
    $workbook->close();
}