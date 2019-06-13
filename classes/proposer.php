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

use mod_personalschedule\items\category_learning_object;
use mod_personalschedule\items\category_object;
use mod_personalschedule\items\day_free_period_group;
use mod_personalschedule\items\day_free_period_group_element;
use mod_personalschedule\items\day_info;
use mod_personalschedule\items\proposed_activity_object;
use mod_personalschedule\items\proposed_object;
use mod_personalschedule\items\proposed_relax_object;
use mod_personalschedule\items\schedule;
use mod_personalschedule\items\user_practice_info;
use mod_personalschedule\items\user_view_info;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/personalschedule/lib.php');
include_once($CFG->libdir . '/gradelib.php');

class mod_personalschedule_proposer {
    /**
     * Returns true if number of actions with the proposedItem was changed.
     * If proposedItem is the practice, then return true if number of attempts was changed.
     * @param user_view_info[] $userActionsInfo
     * @param proposed_activity_object $proposedItem
     * @return bool
     */
    public static function get_proposed_element_actions_status($userActionsInfo, $proposedItem) {

        if (!($proposedItem instanceof proposed_activity_object)) return false;

        if (!array_key_exists($proposedItem->activity->id, $userActionsInfo)) {
            return false;
        }

        $userActionInfo = $userActionsInfo[$proposedItem->activity->id];
        if ($userActionInfo instanceof user_practice_info) {
            $currentActions = $userActionInfo->attempts;
            $cachedActions = $proposedItem->actions;
            return $currentActions != $cachedActions;
        } else {
            $currentActions = $userActionInfo->actions;
            $cachedActions = $proposedItem->actions;
            return $currentActions != $cachedActions;
        }
    }


    private static function personal_items_get_sin_wave($periodLocalIdx) {
        return sin($periodLocalIdx)/2;
    }

    /**
     * Calculates duration of present time by age.
     * Based on research in the book "Ebbinghaus H. Memory A Contribution to Experimental Psychology".
     * @param int $age Age value.
     * @return array Array with two items:
     * 1) Duration of present time in memory, according to the age.
     * 2) Maximum possible duration of present time.
     */
    private static function get_memory_duration_in_seconds_by_age($age) {
        $xData = array(0, 15, 100);
        $yData = array(1, 6, 2.8);

        $value = self::interpolate($xData, $yData, $age, false);
        $maxValue = max($yData);
        return array($value, $maxValue);
    }

    private static function interpolate($xData, $yData, $x, $extrapolate) {
        $size = count($xData);

        $i = 0; // Find left end of interval for interpolation.
        if ($x >= $xData[$size - 2]) // Special case: beyond right end.
        {
            $i = $size - 2;
        } else {
            while ($x > $xData[$i + 1]) $i++;
        }
        $xL = $xData[$i];
        $yL = $yData[$i];
        $xR = $xData[$i + 1];
        $yR = $yData[$i + 1]; // Points on either side (unless beyond ends).
        if (!$extrapolate) // If beyond ends of array and not extrapolating.
        {
            if ($x < $xL) $yR = $yL;
            if ($x > $xR) $yL = $yR;
        }

        $dydx = ($yR - $yL) / ($xR - $xL); // Gradient.

        return $yL + $dydx * ($x - $xL); // Linear interpolation.
    }

    /**
     * Returns true if activity is a lecture (in terms of this module).
     * @param cm_info $activity Activity instance object.
     * @return bool true if activity is a lecture.
     */
    private static function is_activity_lecture($activity) {
        return in_array($activity->modname, mod_personalschedule_config::lecturesModNames);
    }

    /**
     * Returns true if activity is a practice (in terms of this module).
     * @param cm_info $activity Activity instance object.
     * @return bool true if activity is a practice.
     */
    private static function is_activity_practice($activity) {
        return in_array($activity->modname, mod_personalschedule_config::practiceModNames);
    }

    /**
     * @param int $personalscheduleId Personalization activity instance ID.
     * @param int $userId User ID.
     * @param int $courseId Course ID.
     * @param int $userAge User age.
     * @param mod_personalschedule\items\user_view_info[] $userActionsInfo
     * @return category_object[]
     * @throws dml_exception
     * @throws moodle_exception
     */
    private static function get_categories($personalscheduleId, $userId, $courseId, $userAge, $userActionsInfo) {
        /*
         * Receives:
         * - Uncompleted activities,
         * - Information about interactions number with each activity.
         */
        $uncompletedActivities = self::personal_items_get_uncompleted_activities($courseId, $userId);
        /** @var $categoriesObjects category_object[] */
        $categoriesObjects = array();

        $cmProps = personalschedule_get_course_modules_props($personalscheduleId);
        foreach ($uncompletedActivities as $activity) {

            // Gets properties of the activity.
            $props = $cmProps[$activity->id];

            // We should ignore activities with "IS IGNORED" property, or if there are no properties at all.
            if ($props === false) {
                continue;
            }
            if ($props->is_ignored === 1) {
                continue;
            }

            $isLecture = self::is_activity_lecture($activity);
            $isPractice = self::is_activity_practice($activity);

            // We should skip activities with unknown type.
            if (!$isLecture && !$isPractice) {
                continue;
            }

            if (!array_key_exists($props->category, $categoriesObjects)) {
                $categoriesObjects[$props->category] = new category_object($props->category);
            }

            $newCategoryLearningObject = new category_learning_object($activity);

            if ($isLecture) {
                if (array_key_exists($activity->id, $userActionsInfo)) {
                    $newCategoryLearningObject->actions =
                        $userActionsInfo[$activity->id]->actions;
                }
                $categoriesObjects[$props->category]->lectures[$activity->id] = $newCategoryLearningObject;
            } else if ($isPractice) {
                $categoriesObjects[$props->category]->practices[$activity->id] = $newCategoryLearningObject;

                if (array_key_exists($activity->id, $userActionsInfo)) {
                    $userViewInfo = $userActionsInfo[$activity->id];
                    if ($userViewInfo instanceof user_practice_info) {
                        $newCategoryLearningObject->actions =
                            $userViewInfo->attempts;

                        $categoriesObjects[$props->category]->isPassed = $userViewInfo->isPassed;
                        $categoriesObjects[$props->category]->attempts = $userViewInfo->attempts;
                    } else {
                        $categoriesObjects[$props->category]->isPassed = $userViewInfo->actions > 0;
                        $categoriesObjects[$props->category]->attempts = $userViewInfo->actions;
                    }
                }
            }
        }

        foreach ($categoriesObjects as $categoryIndex => $categoryObjects) {
            if (mod_personalschedule_config::skipCompletedCategories && $categoryObjects->isPassed) {
                $categoryObjects->shouldBeIgnored = true;
                unset($categoriesObjects[$categoryIndex]);
                continue;
            }

            if (mod_personalschedule_config::skipCategoriesWithoutPractice && count($categoryObjects->practices) == 0) {
                $categoryObjects->shouldBeIgnored = true;
                unset($categoriesObjects[$categoryIndex]);
                continue;
            }

            $attempts = $categoryObjects->attempts;
            if ($attempts > mod_personalschedule_config::maxAttemptsToIgnoreCategory) {
                $categoryObjects->shouldBeIgnored = true;
                unset($categoriesObjects[$categoryIndex]);
                continue;
            }

            foreach ($categoryObjects->lectures as $activityId => $activity) {
                $shouldUse = self::calculate_and_fill_duration_to_object($cmProps,
                    $activityId, $attempts, $activity, $userAge);

                if (!$shouldUse) continue;
                $categoryObjects->add_learning_object($activity, true);
            }

            foreach ($categoryObjects->practices as $activityId => $activity) {
                $shouldUse = self::calculate_and_fill_duration_to_object($cmProps,
                    $activityId, $attempts, $activity, $userAge);

                if (!$shouldUse) continue;
                $categoryObjects->add_learning_object($activity, false);
            }
        }

        // Sorts the categories in total corrected duration descending order.
        usort($categoriesObjects, array("mod_personalschedule_proposer", "categories_sort_by_duration_desc"));
        return $categoriesObjects;
    }

    /**
     * @param $course_id int
     * @param $user_id int
     * @return cm_info[]
     * @throws dml_exception
     * @throws moodle_exception
     */
    private static function personal_items_get_uncompleted_activities($course_id, $user_id) {
        global $DB;

        $course_object = $DB->get_record('course', array('id' => $course_id), 'id');
        $modinfo = get_fast_modinfo($course_object, $user_id);
        $ciinfo = new completion_info($course_object);
        $activities = $ciinfo->get_activities();
        /** @var cm_info[] $uncompletedActivities */
        $uncompletedActivities = array();
        foreach ($activities as $activity) {
            if (in_array($activity->modname, mod_personalschedule_config::ignoredModnames)) {
                continue;
            }
            $activityCompletionData = $ciinfo->get_data($activity, true, $user_id, $modinfo);
            if ($activityCompletionData->completionstate == 0) {
                $uncompletedActivities[] = $activity;
            }
        }
        return $uncompletedActivities;
    }


    /**
     * @param $periodIdx int
     * @param $dayIdx int
     * @param $weekIdx int
     * @param $userSchedule schedule
     * @return day_info
     */
    private static function get_today_groupped_free_periods($periodIdx,
        $dayIdx, $weekIdx, $userSchedule) {

        list($dayBeginPeriodIdx, $dayBeginDayIdx, $dayEndPeriodIdx, $dayEndDayIdx) =
            self::get_today_day_ranges($periodIdx, $dayIdx, $userSchedule);

        $dayInfo = new day_info($dayBeginDayIdx, $dayBeginPeriodIdx, $dayEndDayIdx, $dayEndPeriodIdx, $weekIdx);
        $curGroupIdx = 0;
        $curPeriodIdx = $dayBeginPeriodIdx;
        $curDayIdx = $dayBeginDayIdx;
        $lastPeriodWasFree = false;
        $totalFreeHoursInCurrentDay = 0;

        $scheduleStatuses = $userSchedule->get_statuses();
        $readinesses = $userSchedule->get_readinesses();

        while (true) {
            $tempStatus = $scheduleStatuses[$curDayIdx][$curPeriodIdx];

            if ($tempStatus == mod_personalschedule_config::statusFree) {
                if (!$dayInfo->is_group_exists($curGroupIdx)) {
                    $dayInfo->add_free_period_group($curGroupIdx, new day_free_period_group($curPeriodIdx));
                }

                $dayInfo->get_group($curGroupIdx)->add_period(
                    new day_free_period_group_element(
                        $curPeriodIdx, self::personal_items_get_sin_wave($totalFreeHoursInCurrentDay),
                        $readinesses[$curPeriodIdx]));

                $lastPeriodWasFree = true;
                $totalFreeHoursInCurrentDay++;
            } else {
                if ($lastPeriodWasFree) {
                    $curGroupIdx++;
                }
                $lastPeriodWasFree = false;
            }

            self::increment_period_idx($curPeriodIdx, $curDayIdx);

            if ($curPeriodIdx == $dayEndPeriodIdx && $curDayIdx == $dayEndDayIdx) {
                break;
            }
        }
        return $dayInfo;
    }

    /**
     * @param array $cmProps
     * @param $activityId int
     * @param $attempts int
     * @param $activity category_learning_object
     * @param $userAge int
     * @return bool
     */
    private static function calculate_and_fill_duration_to_object(
        array $cmProps,
        $activityId,
        $attempts,
        &$activity,
        $userAge
    ) {
        $props = $cmProps[$activityId];
        $originDurationInSeconds = $props->duration;
        if ($attempts == 0) {
            $newDuration = $originDurationInSeconds;
        } else {
            $newDuration = $originDurationInSeconds - ($attempts / $originDurationInSeconds);
        }

        if ($newDuration <= 0) {
            return false;
        }

        $activity->totalDurationSec = $originDurationInSeconds;

        list($memoryDuration, $maximumPossibleMemoryDuration) = self::get_memory_duration_in_seconds_by_age($userAge);

        $activity->modifiedDurationSec = $newDuration +
            ($newDuration * ($maximumPossibleMemoryDuration - $memoryDuration));

        return true;
    }

    /**
     * @param $dayBeginPeriodIdx
     * @param $dayBeginDayIdx
     */
    private static function decrement_period_idx(&$dayBeginPeriodIdx, &$dayBeginDayIdx) {
        if ($dayBeginPeriodIdx == mod_personalschedule_config::periodIndexMin) {
            $dayBeginPeriodIdx = mod_personalschedule_config::periodIndexMax;
            $dayBeginDayIdx--;
        } else {
            $dayBeginPeriodIdx--;
        }


        if ($dayBeginDayIdx < mod_personalschedule_config::dayIndexMin) {
            $dayBeginDayIdx = mod_personalschedule_config::dayIndexMax;
        }
    }

    /**
     * @param $dayEndPeriodIdx
     * @param $dayEndDayIdx
     */
    private static function increment_period_idx(&$dayEndPeriodIdx, &$dayEndDayIdx) {
        if ($dayEndPeriodIdx == mod_personalschedule_config::periodIndexMax) {
            $dayEndPeriodIdx = mod_personalschedule_config::periodIndexMin;

            if ($dayEndDayIdx == mod_personalschedule_config::dayIndexMax) {
                $dayEndDayIdx = mod_personalschedule_config::dayIndexMin;
            } else {
                $dayEndDayIdx++;
            }
        } else {
            $dayEndPeriodIdx++;
        }

    }


    /**
     * Insert proposed elements into the cache (database table).
     * @param int $userId User ID.
     * @param int $personalscheduleId Personalization module instance ID.
     * @param day_info $dayInfo Information about the day, in which the proposed elements were formed.
     * @param proposed_object[] $elements Proposed elements.
     * @throws coding_exception
     * @throws dml_exception
     */
    private static function insert_proposed_elements($userId, $personalscheduleId, $dayInfo, $elements) {
        global $DB;

        $delete_conditions = array();
        $delete_conditions["userid"] = $userId;
        $delete_conditions["personalschedule"] = $personalscheduleId;
        $delete_conditions["daybegindayidx"] = $dayInfo->dayBeginDayIdx;
        $delete_conditions["daybeginperiodidx"] = $dayInfo->dayBeginPeriodIdx;

        /** @var $itemsToInsert stdClass[] */
        $itemsToInsert = array();

        foreach ($elements as $proposedActivityObject) {
            $itemToInsert = new stdClass();

            $itemToInsert->userid = $userId;
            $itemToInsert->personalschedule = $personalscheduleId;

            $itemToInsert->daybegindayidx = $dayInfo->dayBeginDayIdx;
            $itemToInsert->daybeginperiodidx = $dayInfo->dayBeginPeriodIdx;

            $itemToInsert->begindayidx = $dayInfo->dayBeginDayIdx;
            $itemToInsert->beginperiodidx = $proposedActivityObject->periodIdxBegin;

            $itemToInsert->modifieddurationsec = $proposedActivityObject->modifiedDurationSec;

            $itemToInsert->weekidx = $dayInfo->weekIdx;

            if ($proposedActivityObject instanceof proposed_relax_object) {
                $itemToInsert->cm = null;
                $itemToInsert->actions = null;
            } else if ($proposedActivityObject instanceof  proposed_activity_object) {
                $itemToInsert->cm = $proposedActivityObject->activity->id;
                $itemToInsert->actions = $proposedActivityObject->actions;
            }

            $itemsToInsert[] = $itemToInsert;
        }

        $DB->insert_records("personalschedule_proposes", $itemsToInsert);
    }

    /**
     *
     * @param int $periodIdx
     * @param int $dayIdx
     * @param schedule $userSchedule
     * @return array
     */
    private static function get_today_day_ranges($periodIdx, $dayIdx, $userSchedule) {
        $dayBeginPeriodIdx = $periodIdx;
        $dayBeginDayIdx = $dayIdx;

        $dayEndPeriodIdx = $periodIdx;
        $dayEndDayIdx = $dayIdx;

        /*
         * Firstly, we should know when the current day began for the user.
         * We're going back until we find a period with SLEEP status. This will be the start of the day.
         * Then we will go forward to find the end of the day. It's, again, a period with SLEEP status.
         */

        $currentIteration = 0;
        $maxIterations =
            mod_personalschedule_config::dayIndexMax *
            mod_personalschedule_config::periodIndexMax;

        $scheduleStatuses = $userSchedule->get_statuses();
        $startedInSleepPeriod =
            $scheduleStatuses[$dayBeginDayIdx][$dayBeginPeriodIdx] == mod_personalschedule_config::statusSleep;

        while (true) {
            $currentIteration++;
            if ($currentIteration > $maxIterations) {
                $dayBeginDayIdx = $dayIdx;
                $dayBeginPeriodIdx =  mod_personalschedule_config::periodIndexMin;
                break;
            }

            $tempStatus = $scheduleStatuses[$dayBeginDayIdx][$dayBeginPeriodIdx];

            if ($startedInSleepPeriod) {
                if ($tempStatus != mod_personalschedule_config::statusSleep) {
                    break;
                }
            } else {
                if ($tempStatus == mod_personalschedule_config::statusSleep) {
                    self::increment_period_idx($dayBeginPeriodIdx, $dayBeginDayIdx);
                    break;
                }
            }

            if ($startedInSleepPeriod) {
                self::increment_period_idx($dayBeginPeriodIdx, $dayBeginDayIdx);
            } else {
                self::decrement_period_idx($dayBeginPeriodIdx, $dayBeginDayIdx);
            }
        }

        if ($startedInSleepPeriod) {
            $dayEndDayIdx = $dayBeginDayIdx;
            $dayEndPeriodIdx = $dayBeginPeriodIdx;
        }

        $currentIteration = 0;
        while (true) {
            $currentIteration++;
            if ($currentIteration > $maxIterations) {
                $dayEndDayIdx = $dayIdx;
                $dayEndPeriodIdx = mod_personalschedule_config::periodIndexMin;
                break;
            }

            $tempStatus = $scheduleStatuses[$dayEndDayIdx][$dayEndPeriodIdx];

            if ($tempStatus == mod_personalschedule_config::statusSleep) {
                self::decrement_period_idx($dayEndPeriodIdx, $dayEndDayIdx);
                break;
            }

            self::increment_period_idx($dayEndPeriodIdx, $dayEndDayIdx);
        }

        return array($dayBeginPeriodIdx, $dayBeginDayIdx, $dayEndPeriodIdx, $dayEndDayIdx);
    }

    /**
     * Tries to pick up learning elements from the categoriesObjects and
     * fit them into current dayInfo.
     * @param day_info $dayInfo Information about the day, in which the proposed elements should be generated.
     * @param category_object[] $categoriesObjects Categories with the activities and their information.
     * @return proposed_object[] Generated elements.
     */
    private static function generate_proposed_elements($dayInfo, $categoriesObjects) {
        /** @var proposed_object[] $proposedElements */
        $proposedActivities = array();
        $totalModifiedFreeHoursInCurrentDay = 0;

        /** @var category_learning_object[] $proposedObjects */
        $proposedObjects = array();

        foreach ($dayInfo->freePeriodGroups as $dayGroup) {
            $groupDurationInHours = $dayGroup->get_modified_duration_in_hours();
            $totalModifiedFreeHoursInCurrentDay += $groupDurationInHours;
            $freeHoursLeft = $groupDurationInHours;
            $lastPeriodIdx = $dayGroup->periodIdxBegin;
            foreach ($categoriesObjects as $categoryObject) {
                $catDurationInHours = $categoryObject->get_modified_duration_in_hours();
                if ($catDurationInHours <= $freeHoursLeft) {
                    $freeHoursLeft -= $catDurationInHours;

                    foreach (array_merge($categoryObject->leftLectures, $categoryObject->leftPractices) as $learningObject) {
                        $proposedObjects[$learningObject->activity->id] = $learningObject;
                    }

                    continue;
                }
            }

            usort($categoriesObjects, array("mod_personalschedule_proposer", "categories_sort_by_duration_asc"));

            foreach ($categoriesObjects as $categoryObjects) {
                foreach ($categoryObjects->leftLectures as $lecture) {
                    $activityDurationInHours = $lecture->get_modified_duration_in_hours();
                    if ($activityDurationInHours <= $freeHoursLeft) {
                        if (key_exists($lecture->activity->id, $proposedObjects)) {
                            continue;
                        }
                        $freeHoursLeft -= $activityDurationInHours;
                        $proposedObjects[$lecture->activity->id] = $lecture;

                        continue;
                    }
                }
            }

            $relaxItemsCount = $freeHoursLeft * 60 / mod_personalschedule_config::minimumRelaxTimeInMinutes;
            $relaxItemsPeriodicity = 0;
            if ($relaxItemsCount >= 1) {
                $relaxItemsPeriodicity = floor(count($proposedObjects) / $relaxItemsCount);
            }

            $currentIndex = 0;
            $currentRelaxIndex = -1;
            foreach ($proposedObjects as $proposedObject) {
                if ($currentIndex != 0 && $relaxItemsPeriodicity >= 1 && ($currentIndex % $relaxItemsPeriodicity == 0)) {
                    $proposedActivities[$currentRelaxIndex--] = new proposed_relax_object(
                        60 * mod_personalschedule_config::minimumRelaxTimeInMinutes,
                        $lastPeriodIdx, $dayInfo->dayBeginDayIdx, $dayInfo->dayBeginPeriodIdx, $dayInfo->weekIdx);
                    $lastPeriodIdx += mod_personalschedule_config::minimumRelaxTimeInMinutes / 60;
                }

                $proposedActivities[$proposedObject->activity->id] =
                    new proposed_activity_object($proposedObject->activity,
                        $proposedObject->modifiedDurationSec,
                        $lastPeriodIdx, $proposedObject->actions,
                        $dayInfo->dayBeginDayIdx, $dayInfo->dayBeginPeriodIdx, $dayInfo->weekIdx);
                $lastPeriodIdx += $proposedObject->get_modified_duration_in_hours();

                $currentIndex++;
            }
        }
        return $proposedActivities;
    }


    /**
     * Helper method for sorting the category_objects by modifiedDurationSec descending
     * @param $a category_object
     * @param $b category_object
     * @return int
     */
    private static function categories_sort_by_duration_desc($a, $b) {
        if ($a->modifiedDurationSec == $b->modifiedDurationSec) return 0;
        return ($a->modifiedDurationSec < $b->modifiedDurationSec) ? 1 : -1;
    }

    /**
     * Helper method for sorting the category_objects by modifiedDurationSec ascending
     * @param $a category_object
     * @param $b category_object
     * @return int
     */
    private static function categories_sort_by_duration_asc($a, $b)
    {
        if ($a->modifiedDurationSec == $b->modifiedDurationSec) return 0;
        return ($a->modifiedDurationSec < $b->modifiedDurationSec) ? -1 : 1;
    }

    /**
     * Generates proposed learning elements for the
     * learner. If the proposed elements already were generated (for the current time info), then
     * returns cached items from the database.
     * @param int $personalscheduleId Personalization module instance ID.
     * @param int $userId User ID.
     * @param int $courseId Course ID.
     * @param int $dayIdx Current day index.
     * @param int $periodIdx Current period index.
     * @param int $weekIdx Current week index.
     * @param user_view_info[] $userActionsInfo Array with information about user interactions with each course's activity.
     * @return proposed_object[] Generated elements.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function personal_get_items(
        $personalscheduleId, $userId, $courseId, $dayIdx, $periodIdx, $weekIdx, $userActionsInfo) {

        $userAge = personalschedule_get_user_age($personalscheduleId, $userId);

        $categoriesObjects = self::get_categories($personalscheduleId,
            $userId, $courseId, $userAge, $userActionsInfo);

        $schedule = personalschedule_get_user_schedule($personalscheduleId, $userId);

        // Groups the current day by free periods.
        $dayInfo = self::get_today_groupped_free_periods($periodIdx, $dayIdx, $weekIdx, $schedule);

        $cachedProposedActivities =
            self::get_all_proposed_items_from_cache($personalscheduleId, $courseId, $userId, $dayInfo);

        $proposedActivitiesAlreadyExist = count($cachedProposedActivities) > 0;
        if ($proposedActivitiesAlreadyExist) {
            $proposedActivities = $cachedProposedActivities;
        } else {
            $proposedActivities = self::generate_proposed_elements($dayInfo, $categoriesObjects);
            self::insert_proposed_elements($userId, $personalscheduleId, $dayInfo, $proposedActivities);
        }

        return $proposedActivities;
    }


    /**
     * Returns period index, which is based on the current time.
     * @param int $currentUnixTime Current UNIX time.
     * @return int|false Period index. If a non-numeric value is used for
     * timestamp, false is returned.
     */
    public static function personal_items_get_period_idx($currentUnixTime) {
        $hourMin = date('G', $currentUnixTime);
        return $hourMin;
    }

    /**
     * Returns day index, which is based on the current time.
     * @param int $currentUnixTime Current UNIX time.
     * @return int|false Day index. If a non-numeric value is used for
     * timestamp, false is returned.
     */
    public static function personal_items_get_day_idx($currentUnixTime) {
        // From 0 (sunday) to 6 (saturday).
        $day = date('w', $currentUnixTime);

        // Converting to module format (sunday became seventh day).
        if ($day == 0) $day = 7;
        return $day;
    }

    /**
     * Returns proposed user items from cache (database table).
     * @param int $personalscheduleId Personalization module instance id.
     * @param int $courseId Course ID.
     * @param int $userId User ID.
     * @param day_info $dayInfo If not null, then tries to return items from the specific day.
     * @return proposed_object[] Array with proposed user items.
     */
    public static function get_all_proposed_items_from_cache($personalscheduleId, $courseId, $userId, $dayInfo = null) {
        global $DB;

        $conditions = array();
        $conditions["userid"] = $userId;
        $conditions["personalschedule"] = $personalscheduleId;

        if ($dayInfo != null) {
            $conditions["daybegindayidx"] = $dayInfo->dayBeginDayIdx;
            $conditions["daybeginperiodidx"] = $dayInfo->dayBeginPeriodIdx;
            $conditions["weekidx"] = $dayInfo->weekIdx;
        }

        /** @var $proposedElements proposed_object[] */
        $proposedElements = array();

        try {
            $records = $DB->get_records("personalschedule_proposes", $conditions);

            if (count($records) > 0) {
                $modinfo = get_fast_modinfo($courseId);
            }

            foreach ($records as $record) {
                if ($record->cm == null || $record->actions == null) {
                    $proposedActivityObject = new proposed_relax_object($record->modifieddurationsec,
                        $record->beginperiodidx, $record->daybegindayidx, $record->daybeginperiodidx,
                        $record->weekidx);
                } else {
                    if (isset($modinfo)) {
                        $cm = $modinfo->get_cm($record->cm);
                    } else {
                        continue;
                    }

                    $proposedActivityObject = new mod_personalschedule\items\proposed_activity_object($cm, $record->modifieddurationsec,
                        $record->beginperiodidx, $record->actions, $record->daybegindayidx, $record->daybeginperiodidx,
                        $record->weekidx);
                }

                $proposedElements[] = $proposedActivityObject;
            }
        } catch (dml_exception $e) {
        } catch (moodle_exception $e) {
        } finally {
            return $proposedElements;
        }
    }

    /**
     * This function calculates user's interactions (actions) number with
     * course's activities. The interactions number based on data from table "logstore_standard_log".
     * Also, for the mod_quiz activities there is number of test attempts, which is calculated using info
     * from Grades API.
     * @param int $userId: User ID.
     * @param int $courseId: Course ID.
     * @return mod_personalschedule\items\user_view_info[] Array with user' view info items.
     */
    public static function get_user_views_info($userId, $courseId) {
        global $DB;

        $sql = "SELECT cm.id as 'cmid', m.name, cm.instance, COUNT(l.id) AS 'actions'
FROM {logstore_standard_log} AS l
  JOIN {course_modules} cm ON cm.id = l.contextinstanceid
  JOIN {modules} m ON m.id = cm.module
WHERE  l.userid = :userid
  AND l.courseid = :courseid
    AND l.contextlevel = :contextlevel
GROUP BY l.userid, l.contextinstanceid";

        /** @var user_view_info[] $userViewsInfo */
        $userViewsInfo = array();
        try {
            $results = $DB->get_records_sql($sql, array(
                "userid" => $userId,
                "courseid" => $courseId,
                "contextlevel" => CONTEXT_MODULE,
            ));
            if ($results == false) {
                return $userViewsInfo;
            }

            foreach ($results as $result) {
                $functionName = "mod_personalschedule_proposer::get_".$result->name."_user_view_info";
                if (is_callable($functionName)) {
                    $userViewInfo = call_user_func(
                        $functionName, $userId, $courseId, $result->instance, $result->cmid, $result->actions, $result->cmid);
                } else {
                    $userViewInfo = new user_view_info($result->cmid, $result->actions);
                }

                $userViewsInfo[$result->cmid] = $userViewInfo;
            }
        } catch (dml_exception $ex) {
            return $userViewsInfo;
        }
        return $userViewsInfo;
    }


    /**
     * Returns user_view_info for the mod_quiz activity.
     * Called implicitly by call_user_func(...) in get_user_views_info(...).
     * @param int $userId User ID.
     * @param int $courseId Course ID.
     * @param int $instance Activity Instance ID.
     * @param int $actions User's interactions count with this activity (from logstore).
     * @param int $id ID from logstore.
     * @return user_view_info
     */
    private static function get_quiz_user_view_info($userId, $courseId, $instance, $cmid, $actions, $id) {
        if (!is_callable("quiz_get_user_attempts")) {
            //TODO: Show error message to admin.
            return new user_practice_info($id, 1, true, false);
        }

        /** @var array $quizAttempts */
        $quizAttempts = quiz_get_user_attempts($instance, $userId, 'finished', true);
        $attempts = count($quizAttempts);
        if (count($quizAttempts) >= 1) {
            $quizAttempt = reset($quizAttempts);
            if ($quizAttempt->sumgrades == null) {
                $notRated = true;
                $isPassed = $actions != count($quizAttempts);
            } else {
                $notRated = false;

                $gradingInfo = grade_get_grades($courseId, 'mod', 'quiz',
                    $instance);

                $quizGradesInfo = reset($gradingInfo->items);
                $percentOfRightAnswers = $quizAttempt->sumgrades / $quizGradesInfo->grademax * 100;
                $isPassed = $percentOfRightAnswers >= mod_personalschedule_config::minPercentToPass;
            }

        } else {
            $notRated = true;
            $isPassed = false;
        }

        $userViewInfo = new user_practice_info($id, $attempts, $isPassed, $notRated);
        return $userViewInfo;
    }

    /**
     * Returns user_view_info for the mod_survey activity.
     * Called implicitly by call_user_func(...) in get_user_views_info(...).
     * @param int $userId User ID.
     * @param int $courseId Course ID.
     * @param int $instance Activity Instance ID.
     * @param int $actions User's interactions count with this activity (from logstore).
     * @param int $id ID from logstore.
     * @return user_view_info
     */
    public static function get_survey_user_view_info($userId, $courseId, $instance, $cmid, $actions, $id) {
        if (!is_callable("survey_already_done")) {
            //TODO: Show error message to admin.
            $passed = true;
        } else {
            $passed = survey_already_done($instance, $userId);
        }

        $userViewInfo = new user_practice_info($id,$passed ? 1 : 0, $passed, false);
        return $userViewInfo;
    }

    /**
     * Returns user_view_info for the mod_assign activity.
     * Called implicitly by call_user_func(...) in get_user_views_info(...).
     * @param int $userId User ID.
     * @param int $courseId Course ID.
     * @param int $instance Activity Instance ID.
     * @param int $actions User's interactions count with this activity (from logstore).
     * @param int $id ID from logstore.
     * @return user_view_info
     */
    public static function get_assign_user_view_info($userId, $courseId, $instance, $cmid, $actions, $id) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $cm = new stdClass();
        $cm->id = $cmid;
        $cm->course = $courseId;
        $assign = new assign(null, $cm, $courseId);

        if (is_callable(array($assign, "get_user_submission"))) {
            $submission = $assign->get_user_submission($userId, false);
            $isPassed = $submission && $submission->status == ASSIGN_SUBMISSION_STATUS_SUBMITTED;
            //TODO: тут есть момент с тем, что если мы прошли тестирование, а потом нам его повторно дали,
            // то когда будем смотреть actions, то оно не поменяется, и мы можем посчитать, что якобы ничего не делали
        } else {
            $isPassed = true;
        }

        $userViewInfo = new user_practice_info($id,$isPassed ? 1 : 0, $isPassed, false);
        return $userViewInfo;
    }

    /**
     * Returns user_view_info for the mod_feedback activity.
     * Called implicitly by call_user_func(...) in get_user_views_info(...).
     * @param int $userId User ID.
     * @param int $courseId Course ID.
     * @param int $instance Activity Instance ID.
     * @param int $actions User's interactions count with this activity (from logstore).
     * @param int $id ID from logstore.
     * @return user_view_info
     */
    public static function get_feedback_user_view_info($userId, $courseId, $instance, $cmid, $actions, $id) {
        // Feedback can be anonymous, so we can just rely on actions number. It's not important to check
        // if user completes the feedback.
        return new user_practice_info($id, $actions, $actions > 0, false);
    }

    /**
     * Returns user_view_info for the mod_choice activity.
     * Called implicitly by call_user_func(...) in get_user_views_info(...).
     * @param int $userId User ID.
     * @param int $courseId Course ID.
     * @param int $instance Activity Instance ID.
     * @param int $actions User's interactions count with this activity (from logstore).
     * @param int $id ID from logstore.
     * @return user_view_info
     */
    public static function get_choice_user_view_info($userId, $courseId, $instance, $cmid, $actions, $id) {
        global $DB;
        try {
            $attempts = $DB->count_records('choice_answers', array('choiceid' => $instance, 'userid' => $userId));
        } catch (dml_exception $e) {
            // isPassed will be true, and the activity will be not processed by algorithm.
            $attempts = 1;
        }
        return new user_practice_info($id, $attempts, $attempts > 0, false);
    }
}