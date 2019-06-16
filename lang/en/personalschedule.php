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
 * Strings for component 'personalschedule', language 'en'
 *
 * @package   mod_personalschedule
 * @copyright 2019 onwards Vladislav Kovalev  snouwer@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['timemodified'] = 'Schedule last modification date';
$string['timecreated'] = 'Schedule creation date';

$string['analysisof'] = 'Report for {$a}';
$string['reportuserstitle'] = 'Users reports';
$string['completionsubmit'] = 'User must fill the schedule to complete this module';
$string['clicktocontinue'] = 'Click to continue';
$string['customintro'] = 'Custom intro';
$string['deleteallanswers'] = 'Delete all user\'s schedules';
$string['deleteanalysis'] = 'Delete all administrator\'s notes about users';
$string['done'] = 'Done';
$string['download'] = 'Download';
$string['downloadexcel'] = 'Download as Excel file';
$string['downloadinfo'] = 'You can download all information in the raw representation for further analysis in Excel or other spreadsheet editor';
$string['downloadresults'] = 'Download all information';
$string['downloadtext'] = 'Download all information in text format';
$string['errorunabletosavenotes'] = 'Unable to save notes';
$string['eventreportdownloaded'] = 'Report downloaded';
$string['eventreportviewed'] = 'Report viewed';
$string['eventresponsesubmitted'] = 'User submitted schedule';
$string['guestsnotallowed'] = 'Guests not allowed to submit the schedule';
$string['introtext'] = 'Intro text';
$string['invalidpersonalscheduleid'] = 'Invalid personalschedule module ID';
$string['modulename'] = 'Personalized Schedule';
$string['modulename_help'] = 'A personalized schedule (personalschedule activity module) allows course users to set their own weekly schedule, taking into account when they are free and when they are busy or sleeping.
Based on this schedule, as well as information about the user\'s age, it\'s will automatically selects the most suitable learning elements for the current day.
This module can be paired with a block_personalitems on the user\'s personal page. Thus, the selected learning elements will be displayed for all courses.';
$string['modulename_link'] = 'mod/personalschedule/view';
$string['modulenameplural'] = "Personalized Schedules";
$string['name'] = 'Name';
$string['newpersonalscheduleresponses'] = 'Last schedule modifications';
$string['nobodyyet'] = 'Nobody submitted schedule yet';
$string['notes'] = 'Your notes about user (only you can see them)';
$string['pluginadministration'] = 'Personalized Schedule settings';
$string['pluginname'] = 'Personalized Schedule';
$string['report'] = 'Report';
$string['responsereports'] = 'Reports';
$string['savednotes'] = 'Your note was saved';
$string['summary'] = 'Summary report';
$string['personalschedule:addinstance'] = 'Add instance of Personalized Schedule module';
$string['personalschedule:download'] = 'Download personalized schedules';
$string['personalschedule:participate'] = 'Fill and submit personalized schedule';
$string['personalschedule:readresponses'] = 'See personalized schedules of other users';
$string['personalschedulesaved'] = 'Personalized schedule was saved';
$string['personalscheduletype_link'] = 'mod/personalschedule/mod';
$string['thanksforanswers'] = 'Your personalized schedule has been saved successfully, {$a}. When you click on "Continue",
 you will be transferred to your personal account, where personalized learning elements will be displayed.';
$string['time'] = 'Time';
$string['viewpersonalscheduleresponses'] = 'View {$a} personalized schedules from other users';
$string['notyetanswered'] = 'Nobody submitted schedule yet';
$string['personalschedulealreadydone'] = 'You have already submitted schedule';

$string['mod_form_header_connection_elements'] = 'Course elements connections';
$string['mod_form_header_connection_elements_duration'] = 'Estimated duration';
$string['mod_form_header_connection_elements_category'] = 'Category';
$string['mod_form_header_connection_elements_category_help'] = 'ыфвыфв';
$string['mod_form_header_connection_elements_weight'] = 'Weight coefficient';
$string['mod_form_header_connection_elements_weight_help'] = 'ыфвыфв';
$string['mod_form_header_connection_elements_is_ignored'] = 'Should be ignored';
$string['mod_form_header_connection_elements_is_ignored_help'] = 'ыфвыфв';

$string["view_label_age"] = 'Your age (5-105): ';
$string['totalcourseduration_const'] = 'Average course duration: ';
$string['durationformat_seconds'] = ' sec.';
$string['durationformat_minutes'] = ' min.';
$string['durationformat_hours'] = ' h.';
$string['durationformat_days'] = ' d.';
$string['durationformat_seconds_format'] = '{$a} sec.';
$string['durationformat_minutes_format'] = '{$a} min.';
$string['durationformat_hours_format'] = '{$a} h.';
$string['durationformat_days_format'] = '{$a} d.';
$string['scheduledcourseduration_days'] = ' days';
$string['scheduledcourseduration_const'] = 'According to the schedule, the course will take ';

$string['ageisnotvalid'] = 'Age is not valid.';
$string['nofreeperiods'] = 'No free periods in the schedule.';

$string['eventschedulewrongsubmit'] = 'User filled incorrect information about age';
$string['eventschedulewrongsubmitschedulefree'] = 'User tried to submit schedule without free periods';

$string['adminnotifycantsendwithoutschedule'] = 'You can\'n send notification to the administrator if you don\'t have schedule yet.';
$string['sendnotifytoadmin'] = 'Send';

$string['privacy:metadata:readiness:userid'] = 'User ID.';
$string['privacy:metadata:readiness:period_idx'] = 'Day Period.';
$string['privacy:metadata:readiness:check_status'] = 'Readiness coefficient.';
$string['privacy:metadata:readiness'] = 'A record with readiness coefficient in the specified period.';
$string['privacy:metadata:proposes:userid'] = 'User ID.';
$string['privacy:metadata:proposes:actions'] = 'Interactions number with the learning element.';
$string['privacy:metadata:proposes'] = 'Record with proposed learning element to the user.';
$string['privacy:metadata:schedules:userid'] = 'User ID.';
$string['privacy:metadata:schedules:period_idx'] = 'Day Period.';
$string['privacy:metadata:schedules:day_idx'] = 'Day.';
$string['privacy:metadata:schedules:check_status'] = 'Schedule status in the specific period.';
$string['privacy:metadata:schedules'] = 'Record with the schedule status in the specific period.';

$string['privacy:metadata:user_props:userid'] = 'User ID.';
$string['privacy:metadata:user_props:age'] = 'Age.';
$string['privacy:metadata:user_props'] = 'Record with the user basic information.';
$string['privacy:metadata:usrattempts:userid'] = 'User ID.';
$string['privacy:metadata:usrattempts:timecreated'] = 'Schedule creation date.';
$string['privacy:metadata:usrattempts:timemodified'] = 'Schedule last modification date.';
$string['privacy:metadata:usrattempts'] = 'Record with the information about when the schedule was modified.';

$string['privacy:metadata:analysis'] = 'A record of personalschedule answers analysis.';
$string['privacy:metadata:analysis:notes'] = 'Notes saved against a user\'s answers.';
$string['privacy:metadata:analysis:userid'] = 'The ID of the user answering the personalschedule.';

$string['weekidx_1'] = "Mon.";
$string['weekidx_2'] = "Tues.";
$string['weekidx_3'] = "Wed.";
$string['weekidx_4'] = "Thurs.";
$string['weekidx_5'] = "Fri.";
$string['weekidx_6'] = "Sat.";
$string['weekidx_7'] = "Sun.";

$string["view_introtext"] = "<p>A personalized schedule allows module to display selected learning elements of the course on your personal page specifically for you.</p><p>Selection will be carried out under those day periods when you are free. The complexity of the learning elements will be taken into account, whether you have already completed it, readiness to work during a given period, as well as your age.</p><p>You can always return to this page and modify the schedule.</p><hr><p>Specify your age. It will be used to correct the complexity of the training elements.</p>";
$string["view_schedule_help"] = "<p>Indicate your weekly schedule.</p><p>The readiness coefficient describes how much you will be motivated to study the educational elements in a specific period.</p><p>If the coefficient is zero, then learning elements will still be given, but during these periods, simpler elements will be chosen.</p>";

$string["view_edit_schedule"] = "Edit the schedule";

$string['proposes_approxduration_h'] = '≈{$a} h.';
$string['proposes_approxduration_hm'] = '≈{$a} h. 30min.';

$string['proposes_approxduration_15m'] = '≈15 min.';
$string['proposes_approxduration_30m'] = '≈30 min.';
$string['proposes_approxduration_1h'] = '≈1 h.';
$string['proposes_approxduration_1m'] = '≈1 min.';

$string['proposes_duration_sh'] = '%d sec. (%d h.)';
$string['proposes_duration_sm'] = '%d sec. (%d min.)';
$string['proposes_duration_s'] = '{$a} sec.';

$string['proposes_tablehead_1'] = 'Learning Element';
$string['proposes_tablehead_2'] = 'Start Time';
$string['proposes_tablehead_3'] = 'Duration';
$string['proposes_tablehead_3_help'] = 'Estimated duration of the learning element';
$string['proposes_tablehead_4'] = 'Status';

$string['proposes_allcompleted'] = 'Today\'s tasks completed. You can relax now.';
$string['proposes_notasks'] = 'There are no tasks for today';
$string['proposes_noschedule'] = 'You should <a href="%s">fill</a> personalized schedule';

$string['proposes_relax'] = 'Relax';

$string['proposes_actionsstatus_true'] = 'Viewed';
$string['proposes_actionsstatus_false'] = 'Not viewed';

$string['event_cmcreated_title'] = '[{$a}] Metadata update required';
$string['event_cmcreated_message'] = 'The new learning element has been added to the course "%s" - "%s" (%s).<br>
In order for the new element to become available in a personalized schedule module, you need to %s metadata.<br>';
$string['event_cmcreated_message_update'] = 'update';

$string['adminnotifyemail_title'] = '[{$a}] Student requested assistance';
$string['adminnotifyemail_message'] = 'The user %s (course "%s")  encountered some problems while passing
personalized learning elements.<br>
You can contact him by %s link.';
$string['adminnotifyemail_message_this'] = 'this';
$string['adminnotify_success'] = 'Your notification has been sent successfully';
$string['adminnotify_description'] = 'The administrator will be notified that you have encountered some problems while passing the proposed elements of the course "{$a}".';

$string['validation_schedulesetting'] = 'Schedule setting';
$string['validation_readinesssetting'] = 'Readiness setting';
$string['validation_sleep'] = 'Sleep';
$string['validation_busy'] = 'Busy';
$string['validation_free'] = 'Free';

$string['sendnotifytoadmin'] = 'Send notify to admin';
$string['sendnotifytoadmin_help'] = 'The course administrator will receive a notification that you have encountered some problems during the learning of the proposed elements. Thus, he may try to help you.';

$string['notifynoactiveusers'] = 'Checks for users, which have not been on the site for a long time, and which have schedule. Sends message to these users.';

$string['notifynoactiveusers_title'] = 'Schedule Reminder';
$string['notifynoactiveusers_message'] = 'Hello, %s.<br>
We noticed that you have not visited the e-learning site for %d days.<br>
If you encounter problems while going through any learning elements, you can send a notification to the administrator on the page with the schedule, or change the schedule as well.<br> ';