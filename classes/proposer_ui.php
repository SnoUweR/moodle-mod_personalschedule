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
 * Personalization proposing API.
 *
 * @package    mod_personalschedule
 * @copyright  2019 onwards Vladislav Kovalev snouwer@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_personalschedule\items\proposed_activity_object;
use mod_personalschedule\items\proposed_relax_object;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/personalschedule/lib.php');
include_once($CFG->libdir . '/gradelib.php');

class mod_personalschedule_proposer_ui {

    /**
     * Returns localized string of the actions status.
     * So if the status was true, then returns something like "Watched" or "Completed".
     * @param bool $actionsStatus Actions status.
     * @return string Localized description of the actions status.
     * @throws coding_exception
     */
    private static function get_proposed_element_actions_status_localized($actionsStatus) {
        return $actionsStatus ?
            get_string('proposes_actionsstatus_true', 'personalschedule') :
            get_string('proposes_actionsstatus_false', 'personalschedule');
    }

    /**
     * Returns approximated localized duration, so if the duration in seconds
     * was, for example, 60 minutes, then returns 1 hour. But if the duration was 95 minutes,
     * then returns 1:30 (instead of 1:35). But if the duration was 115 minutes, then returns
     * 2 hours.
     * @param int $durationInSeconds Duration in seconds.
     * @return string Approximated localized duration.
     * @throws coding_exception
     */
    private static function get_approximated_localized_duration($durationInSeconds) {
        mod_personalschedule_proposer_ui::get_duration_components(
            $durationInSeconds, $days, $hours, $minutes, $seconds);
        if ($hours >= 1) {
            if ($minutes <= 15) {
                return get_string('proposes_approxduration_h', 'personalschedule', $hours);
            } else if ($minutes <= 45) {
                return get_string('proposes_approxduration_hm', 'personalschedule', $hours);
            } else {
                return get_string('proposes_approxduration_h', 'personalschedule', $hours + 1);
            }
        }

        if ($minutes >= 1) {
            if ($minutes <= 15) {
                return get_string('proposes_approxduration_15m', 'personalschedule');
            } else if ($minutes <= 45) {
                return get_string('proposes_approxduration_30m', 'personalschedule');
            } else {
                return get_string('proposes_approxduration_1h', 'personalschedule');
            }
        }

        return get_string('proposes_approxduration_1m', 'personalschedule');
    }

    /**
     * Returns localized duration for received total seconds value.
     * For example, if 3600 was passed, then returns something like "3600 sec. (1 hrs.)".
     * @param int $durationInSeconds Duration in seconds.
     * @return string Localized duration.
     * @throws coding_exception
     */
    public static function get_localized_duration($durationInSeconds)
    {
        $durationInMinutes = $durationInSeconds / 60;
        $durationInHours = $durationInMinutes / 60;

        if ($durationInHours > 1) {
            return
                sprintf(get_string('proposes_duration_sh', 'personalschedule'),
                    $durationInSeconds, $durationInHours);
        }

        if ($durationInMinutes > 1) {
            return
                sprintf(get_string('proposes_duration_sm', 'personalschedule'),
                    $durationInSeconds, $durationInMinutes);
        }

        return get_string('proposes_duration_s', 'personalschedule', $durationInSeconds);
    }

    /**
     * Generate HTML with table and proposed elements for the specific course, user, and by the specific personalschedule module.
     * @param stdClass $course Course object.
     * @param int $userId User ID.
     * @param stdClass|cm_info $personalscheduleCm Personalization module instance object. Must contains instance and id fields.
     * @param int $curTime Current UNIX time. If null, then the function will call time() manually.
     * @param int $dayIdx Current day index. If null, then will be set from $curTime info.
     * @param int $periodIdx Current period index. If null, then will be set from $curTime info.
     * @return string HTML with a table with proposed elements.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_proposed_table($course, $userId, $personalscheduleCm,
        $curTime = null, $dayIdx = null, $periodIdx = null) {

        global $CFG, $OUTPUT;

        $icon = $OUTPUT->pix_icon('i/course', get_string('course'));
        $coursecontext = context_course::instance($course->id);
        $courseHtmlTitle = format_string($course->shortname, true, array('context' => $coursecontext));
        $courseHtmlLink = "$CFG->wwwroot/course/view.php?id=$course->id";
        $courseNameHtml = html_writer::tag("span", html_writer::tag("a",
            $icon.format_string(get_course_display_name_for_list($course)),
            array("title" => $courseHtmlTitle, "href" => $courseHtmlLink)));

        $tableCourseElements = new html_table();
        $tableCourseElements->id = "personal-course-elements";
        $tableCourseElements->head =
            array(
                get_string('proposes_tablehead_1', 'personalschedule'),
                get_string('proposes_tablehead_2', 'personalschedule'),
                html_writer::tag(
                    "abbr",
                    get_string('proposes_tablehead_3', 'personalschedule'),
                    array("title" => get_string('proposes_tablehead_3_help', 'personalschedule'))
                ),
                get_string('proposes_tablehead_4', 'personalschedule')
            );

        $tableCourseElements->data = array();
        $scheduleAlreadySubmitted = personalschedule_does_schedule_already_submitted(
            $personalscheduleCm->instance, $userId);

        $resultHtml = '';

        if ($scheduleAlreadySubmitted) {
            $userActionsInfo = mod_personalschedule_proposer::get_user_views_info($userId, $course->id);

            if ($curTime == null) {
                $curTime = time();
            }

            if ($dayIdx == null) {
                $dayIdx = mod_personalschedule_proposer::personal_items_get_day_idx($curTime);
            }

            if ($periodIdx == null) {
                $periodIdx = mod_personalschedule_proposer::personal_items_get_period_idx($curTime);
            }

            $weekIdx =
                self::personalschedule_get_week_idx($curTime, $userId,
                    $personalscheduleCm->instance);

            $personalItems = mod_personalschedule_proposer::personal_get_items($personalscheduleCm->instance,
                $userId, $course->id, $dayIdx, $periodIdx, $weekIdx, $userActionsInfo);

            $alreadyViewedElementsCount = 0;
            $alreadyAddedElementsToData = 0;

            foreach ($personalItems as $personalItem) {
                /** @var html_table_cell[] $tableRowData */
                $tableRowData = array();

                if ($personalItem instanceof proposed_relax_object) {
                    $tableRowData = self::get_relax_rowdata($personalItem);
                } else if ($personalItem instanceof proposed_activity_object) {
                    list($alreadyViewedElementsCount, $tableRowData) = self::get_activity_rowdata($dayIdx, $periodIdx,
                        $personalItem, $userActionsInfo, $alreadyViewedElementsCount);
                }

                $tableCourseElements->data[$alreadyAddedElementsToData++] = $tableRowData;
            }

            if (!empty($personalItems)) {
                $allElementsViewed = $alreadyViewedElementsCount == count($personalItems);
                if ($allElementsViewed) {
                    $cell1 = new html_table_cell();
                    $cell1->text = get_string('proposes_allcompleted', 'personalschedule');
                    $cell1->colspan = count($tableCourseElements->head);
                    $tableCourseElements->data[] = array($cell1);
                }
            } else {
                $cell1 = new html_table_cell();
                $cell1->text = get_string('proposes_notasks', 'personalschedule');
                $cell1->colspan = count($tableCourseElements->head);
                $tableCourseElements->data[] = array($cell1);
            }
        } else {
            $cell1 = new html_table_cell();


            $cell1->text = sprintf(get_string('proposes_noschedule', 'personalschedule'),
                "$CFG->wwwroot/mod/personalschedule/view.php?id=$personalscheduleCm->id");
            $cell1->colspan = count($tableCourseElements->head);
            $tableCourseElements->data[] = array($cell1);
        }

        $resultHtml .= self::table_to_html($tableCourseElements, $courseNameHtml);

        if ($scheduleAlreadySubmitted) {
            $url = new moodle_url(
                "$CFG->wwwroot/mod/personalschedule/admin_notify.php", array('id' => $personalscheduleCm->id));
            $button = $OUTPUT->single_button($url,
                get_string('sendnotifytoadmin', 'block_personal_items'), "get");
            $resultHtml .= $button;
        }

        return $resultHtml;
    }

    /**
     * This function is the copy of html_writer::table(...), but supports
     * a combined header, which adds over the normal columns headers.
     * @param html_table $table data to be rendered.
     * @param string $topHead Combined header name. If empty, then combined header will not be added.
     * @return string HTML code with table.
     */
    private static function table_to_html(html_table $table, $topHead = '') {
        // prepare table data and populate missing properties with reasonable defaults
        if (!empty($table->align)) {
            foreach ($table->align as $key => $aa) {
                if ($aa) {
                    $table->align[$key] = 'text-align:'. fix_align_rtl($aa) .';';  // Fix for RTL languages
                } else {
                    $table->align[$key] = null;
                }
            }
        }
        if (!empty($table->size)) {
            foreach ($table->size as $key => $ss) {
                if ($ss) {
                    $table->size[$key] = 'width:'. $ss .';';
                } else {
                    $table->size[$key] = null;
                }
            }
        }
        if (!empty($table->wrap)) {
            foreach ($table->wrap as $key => $ww) {
                if ($ww) {
                    $table->wrap[$key] = 'white-space:nowrap;';
                } else {
                    $table->wrap[$key] = '';
                }
            }
        }
        if (!empty($table->head)) {
            foreach ($table->head as $key => $val) {
                if (!isset($table->align[$key])) {
                    $table->align[$key] = null;
                }
                if (!isset($table->size[$key])) {
                    $table->size[$key] = null;
                }
                if (!isset($table->wrap[$key])) {
                    $table->wrap[$key] = null;
                }

            }
        }
        if (empty($table->attributes['class'])) {
            $table->attributes['class'] = 'generaltable';
        }
        if (!empty($table->tablealign)) {
            $table->attributes['class'] .= ' boxalign' . $table->tablealign;
        }

        // explicitly assigned properties override those defined via $table->attributes
        $table->attributes['class'] = trim($table->attributes['class']);
        $attributes = array_merge($table->attributes, array(
            'id'            => $table->id,
            'width'         => $table->width,
            'summary'       => $table->summary,
            'cellpadding'   => $table->cellpadding,
            'cellspacing'   => $table->cellspacing,
        ));
        $output = html_writer::start_tag('table', $attributes) . "\n";

        $countcols = 0;

        // Output a caption if present.
        if (!empty($table->caption)) {
            $captionattributes = array();
            if ($table->captionhide) {
                $captionattributes['class'] = 'accesshide';
            }
            $output .= html_writer::tag(
                'caption',
                $table->caption,
                $captionattributes
            );
        }

        if (!empty($table->head)) {
            $countcols = count($table->head);

            $output .= html_writer::start_tag('thead', array()) . "\n";


            if (!empty($topHead)) {
                $output .= html_writer::start_tag('tr', array()) . "\n";
                $output .= html_writer::tag("th", $topHead, array("colspan" => count($table->head))) . "\n";
                $output .= html_writer::end_tag('tr') . "\n";
            }

            $output .= html_writer::start_tag('tr', array()) . "\n";
            $keys = array_keys($table->head);
            $lastkey = end($keys);

            foreach ($table->head as $key => $heading) {
                // Convert plain string headings into html_table_cell objects
                if (!($heading instanceof html_table_cell)) {
                    $headingtext = $heading;
                    $heading = new html_table_cell();
                    $heading->text = $headingtext;
                    $heading->header = true;
                }

                if ($heading->header !== false) {
                    $heading->header = true;
                }

                if ($heading->header && empty($heading->scope)) {
                    $heading->scope = 'col';
                }

                $heading->attributes['class'] .= ' header c' . $key;
                if (isset($table->headspan[$key]) && $table->headspan[$key] > 1) {
                    $heading->colspan = $table->headspan[$key];
                    $countcols += $table->headspan[$key] - 1;
                }

                if ($key == $lastkey) {
                    $heading->attributes['class'] .= ' lastcol';
                }
                if (isset($table->colclasses[$key])) {
                    $heading->attributes['class'] .= ' ' . $table->colclasses[$key];
                }
                $heading->attributes['class'] = trim($heading->attributes['class']);
                $attributes = array_merge($heading->attributes, array(
                    'style'     => $table->align[$key] . $table->size[$key] . $heading->style,
                    'scope'     => $heading->scope,
                    'colspan'   => $heading->colspan,
                ));

                $tagtype = 'td';
                if ($heading->header === true) {
                    $tagtype = 'th';
                }
                $output .= html_writer::tag($tagtype, $heading->text, $attributes) . "\n";
            }
            $output .= html_writer::end_tag('tr') . "\n";
            $output .= html_writer::end_tag('thead') . "\n";

            if (empty($table->data)) {
                // For valid XHTML strict every table must contain either a valid tr
                // or a valid tbody... both of which must contain a valid td
                $output .= html_writer::start_tag('tbody', array('class' => 'empty'));
                $output .= html_writer::tag('tr', html_writer::tag('td', '', array('colspan'=>count($table->head))));
                $output .= html_writer::end_tag('tbody');
            }
        }

        if (!empty($table->data)) {
            $keys       = array_keys($table->data);
            $lastrowkey = end($keys);
            $output .= html_writer::start_tag('tbody', array());

            foreach ($table->data as $key => $row) {
                if (($row === 'hr') && ($countcols)) {
                    $output .= html_writer::tag('td', html_writer::tag('div', '', array('class' => 'tabledivider')), array('colspan' => $countcols));
                } else {
                    // Convert array rows to html_table_rows and cell strings to html_table_cell objects
                    if (!($row instanceof html_table_row)) {
                        $newrow = new html_table_row();

                        foreach ($row as $cell) {
                            if (!($cell instanceof html_table_cell)) {
                                $cell = new html_table_cell($cell);
                            }
                            $newrow->cells[] = $cell;
                        }
                        $row = $newrow;
                    }

                    if (isset($table->rowclasses[$key])) {
                        $row->attributes['class'] .= ' ' . $table->rowclasses[$key];
                    }

                    if ($key == $lastrowkey) {
                        $row->attributes['class'] .= ' lastrow';
                    }

                    // Explicitly assigned properties should override those defined in the attributes.
                    $row->attributes['class'] = trim($row->attributes['class']);
                    $trattributes = array_merge($row->attributes, array(
                        'id'            => $row->id,
                        'style'         => $row->style,
                    ));
                    $output .= html_writer::start_tag('tr', $trattributes) . "\n";
                    $keys2 = array_keys($row->cells);
                    $lastkey = end($keys2);

                    $gotlastkey = false; //flag for sanity checking
                    foreach ($row->cells as $key => $cell) {
                        if ($gotlastkey) {
                            //This should never happen. Why do we have a cell after the last cell?
                            mtrace("A cell with key ($key) was found after the last key ($lastkey)");
                        }

                        if (!($cell instanceof html_table_cell)) {
                            $mycell = new html_table_cell();
                            $mycell->text = $cell;
                            $cell = $mycell;
                        }

                        if (($cell->header === true) && empty($cell->scope)) {
                            $cell->scope = 'row';
                        }

                        if (isset($table->colclasses[$key])) {
                            $cell->attributes['class'] .= ' ' . $table->colclasses[$key];
                        }

                        $cell->attributes['class'] .= ' cell c' . $key;
                        if ($key == $lastkey) {
                            $cell->attributes['class'] .= ' lastcol';
                            $gotlastkey = true;
                        }
                        $tdstyle = '';
                        $tdstyle .= isset($table->align[$key]) ? $table->align[$key] : '';
                        $tdstyle .= isset($table->size[$key]) ? $table->size[$key] : '';
                        $tdstyle .= isset($table->wrap[$key]) ? $table->wrap[$key] : '';
                        $cell->attributes['class'] = trim($cell->attributes['class']);
                        $tdattributes = array_merge($cell->attributes, array(
                            'style' => $tdstyle . $cell->style,
                            'colspan' => $cell->colspan,
                            'rowspan' => $cell->rowspan,
                            'id' => $cell->id,
                            'abbr' => $cell->abbr,
                            'scope' => $cell->scope,
                        ));
                        $tagtype = 'td';
                        if ($cell->header === true) {
                            $tagtype = 'th';
                        }
                        $output .= html_writer::tag($tagtype, $cell->text, $tdattributes) . "\n";
                    }
                }
                $output .= html_writer::end_tag('tr') . "\n";
            }
            $output .= html_writer::end_tag('tbody') . "\n";
        }
        $output .= html_writer::end_tag('table') . "\n";

        return $output;
    }

    /**
     * @param $personalItem
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    private static function get_relax_rowdata($personalItem)
    {
        /** @var html_table_cell[] $tableRowData */
        $tableRowData = array();

        $relaxIconHtml = html_writer::empty_tag('img',
            array('src' => new moodle_url('/mod/personalschedule/pix/relax.png')));

        $tableRowData[] = new html_table_cell($relaxIconHtml .
            get_string('proposes_relax', 'personalschedule'));

        $tableRowData[] = new html_table_cell(
            html_writer::span(sprintf("%d:00", (int)$personalItem->periodIdxBegin)));

        $durationCell = new html_table_cell(
            self::get_approximated_localized_duration(
                $personalItem->modifiedDurationSec)
        );
        $durationCell->colspan = 2;
        $tableRowData[] = $durationCell;
        return $tableRowData;
    }

    /**
     * @param $dayIdx
     * @param $periodIdx
     * @param $personalItem
     * @param array $userActionsInfo
     * @param $alreadyViewedElementsCount
     * @return array
     * @throws coding_exception
     */
    private static function get_activity_rowdata(
        $dayIdx,
        $periodIdx,
        $personalItem,
        array $userActionsInfo,
        $alreadyViewedElementsCount
    ) {
        global $CFG;

        $moduleIconHtml = html_writer::empty_tag('img',
            array('src' => $personalItem->activity->get_icon_url()));


        $actionsStatus =
            mod_personalschedule_proposer::get_proposed_element_actions_status($userActionsInfo,
                $personalItem);

        $allRowCellsCssClass = "";

        $isProposedElementSkipped = false;
        if ($actionsStatus) {
            $actionsStatusCssClass = "element-status-viewed";
            $alreadyViewedElementsCount++;
            $allRowCellsCssClass = $actionsStatusCssClass;
        } else {
            $isProposedElementSkipped =
                self::is_proposed_element_skipped_by_time(
                    $personalItem, $dayIdx, $periodIdx);

            if ($isProposedElementSkipped) {
                $elementBeginPeriodCssClass = "element-period-skipped";
                $allRowCellsCssClass = $elementBeginPeriodCssClass;
            }
        }

        $activityNameHtml = sprintf(
            "<a href=\"$CFG->wwwroot/mod/%s/view.php?id=%s\">%s %s (%s)</a>",
            $personalItem->activity->modname, $personalItem->activity->id, $moduleIconHtml,
            $personalItem->activity->name, $personalItem->activity->modfullname);

        $zerothCell = new html_table_cell($activityNameHtml);

        $firstCell = new html_table_cell(
            html_writer::span(sprintf("%d:00", (int)$personalItem->periodIdxBegin),
                $isProposedElementSkipped ? "element-start-period-skipped" : ""));
        $secondCell = new html_table_cell(
            self::get_approximated_localized_duration(
                $personalItem->modifiedDurationSec)
        );

        $actionsStatusLocalizedText =
            self::get_proposed_element_actions_status_localized($actionsStatus);


        $thirdCell = new html_table_cell(
            html_writer::span($actionsStatusLocalizedText)
        );

        /** @var html_table_cell[] $tableRowData */
        $tableRowData = array();
        $tableRowData[] = $zerothCell;
        $tableRowData[] = $firstCell;
        $tableRowData[] = $secondCell;
        $tableRowData[] = $thirdCell;

        foreach ($tableRowData as $cell) {
            $cell->attributes["class"] = $allRowCellsCssClass;
        }
        return array($alreadyViewedElementsCount, $tableRowData);
    }


    /**
     * Returns time of first schedule creation for the user and personalschedule module instance id
     * @param $userId int
     * @param $personalscheduleId int
     * @return int
     * @throws dml_exception
     */
    private static function personalschedule_get_schedule_create_time($userId, $personalscheduleId)
    {
        global $DB;
        $data = $DB->get_record(
            "personalschedule_usrattempts",
            array("userid" => $userId, "personalschedule" => $personalscheduleId), "timecreated");
        return $data == false ? 0 : $data->timecreated;
    }

    /**
     * Returns how many weeks have passed since user's schedule was created.
     * The value starts from 1. For example, if the current week is the week, when the schedule was created, then this
     * function will return 1 (not 0).
     * @param int $curTime Current UNIX time.
     * @param int $userId User ID.
     * @param int $personalscheduleId Personalization module instance ID.
     * @return int Number of weeks, that have passed since user's schedule was created.
     * @throws dml_exception
     */
    private static function personalschedule_get_week_idx($curTime, $userId, $personalscheduleId)
    {
        $scheduleCreatedTime = self::personalschedule_get_schedule_create_time($userId, $personalscheduleId);
        $weeksCount = (int)ceil(abs($scheduleCreatedTime - $curTime)/60/60/24/7);
        return $weeksCount;
    }


    /**
     * Splits duration in seconds by time components.
     * @param int $durationInSeconds Duration (in seconds) that should be split.
     * @param int|float $days Total days.
     * @param int|float $hours Total hours.
     * @param int|float $minutes Total minutes.
     * @param int|float $seconds Total seconds.
     */
    public static function get_duration_components($durationInSeconds, &$days, &$hours, &$minutes, &$seconds) {
        $seconds = $durationInSeconds;

        $days = (int)($seconds / ( 24 * 60 * 60 ));
        if ($days >= 1) {
            $seconds -= ( $days * ( 24 * 60 * 60 ) );
        }

        $hours = (int)($seconds / ( 60 * 60 ));

        if ($hours >= 1) {
            $seconds -= ( $hours * ( 60 * 60 ) );
        }

        $minutes = (int)($seconds / 60);

        $seconds -= ( $minutes * 60 );
    }

    /**
     * Returns true if the current period is ahead of the proposed element
     * end period
     * @param $proposedElement proposed_activity_object Uses only periodIdxBegin, modifiedDurationSec and dayPeriodInfo
     * variables from the proposedElement object
     * @param $curDayIdx int
     * @param $curPeriodIdx int
     * @return bool
     */
    public static function is_proposed_element_skipped_by_time($proposedElement, $curDayIdx, $curPeriodIdx) {
        $proposedElementEndPeriod = $proposedElement->periodIdxBegin +
            ($proposedElement->modifiedDurationSec / 60 / 60);

        if ($proposedElement->dayPeriodInfo->dayIdx == $curDayIdx) {
            return $curPeriodIdx > $proposedElementEndPeriod;
        }

        return $proposedElement->dayPeriodInfo->dayIdx > $curDayIdx;
    }

    /**
     * Converts day index to localized week day name (short version of it).
     * @param int $dayIdx Day index (from 1 to 7 inclusive).
     * @return string Localized week day name.
     */
    public static function personalschedule_get_day_localize_from_idx($dayIdx) {
        if ($dayIdx >= 1 && $dayIdx <= 7) {
            try {
                return get_string("weekidx_$dayIdx", "personalschedule");
            } catch (coding_exception $e) {
                return '-';
            }
        }
        return '-';
    }

    /**
     * Just returns formatted period index. The format is "{PERIOD}-{PERIOD+1}".
     * For example, if periodIdx = 23, then returns "23-24".
     * @param int $periodIdx Period index.
     * @return string Formatted period index.
     */
    public static function personalschedule_get_period_localize_from_idx($periodIdx) {
        return $periodIdx.'-'.($periodIdx+1);
    }

}