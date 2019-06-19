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
require_once($CFG->libdir . '/gradelib.php');

class mod_personalschedule_proposer {
    /**
     * Returns true if number of actions with the proposeditem was changed.
     * If proposeditem is the practice, then return true if number of attempts was changed.
     * @param user_view_info[] $useractionsinfo
     * @param proposed_object $proposeditem
     * @return bool
     */
    public static function get_proposed_element_actions_status($useractionsinfo, $proposeditem) {

        if (!($proposeditem instanceof proposed_activity_object)) {
            return false;
        }

        if (!array_key_exists($proposeditem->activity->id, $useractionsinfo)) {
            return false;
        }

        $useractioninfo = $useractionsinfo[$proposeditem->activity->id];
        if ($useractioninfo instanceof user_practice_info) {
            $currentactions = $useractioninfo->attempts;
            $cachedactions = $proposeditem->actions;
            return $currentactions != $cachedactions;
        } else {
            $currentactions = $useractioninfo->actions;
            $cachedactions = $proposeditem->actions;
            return $currentactions != $cachedactions;
        }
    }


    private static function personal_items_get_sin_wave($periodlocalidx) {
        return sin($periodlocalidx) / 2;
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
        $xdata = array(0, 15, 100);
        $ydata = array(1, 6, 2.8);

        $value = self::interpolate($xdata, $ydata, $age, false);
        $maxvalue = max($ydata);
        return array($value, $maxvalue);
    }

    private static function interpolate($xdata, $ydata, $x, $extrapolate) {
        $size = count($xdata);

        $i = 0; // Find left end of interval for interpolation.
        // Special case: beyond right end.
        if ($x >= $xdata[$size - 2]) {
            $i = $size - 2;
        } else {
            while ($x > $xdata[$i + 1]) {
                $i++;
            }
        }
        $xl = $xdata[$i];
        $yl = $ydata[$i];
        $xr = $xdata[$i + 1];
        $yr = $ydata[$i + 1]; // Points on either side (unless beyond ends).
        // If beyond ends of array and not extrapolating.
        if (!$extrapolate) {
            if ($x < $xl) {
                $yr = $yl;
            }
            if ($x > $xr) {
                $yl = $yr;
            }
        }

        $dydx = ($yr - $yl) / ($xr - $xl); // Gradient.

        return $yl + $dydx * ($x - $xl); // Linear interpolation.
    }

    /**
     * Returns true if activity is a lecture (in terms of this module).
     * @param cm_info $activity Activity instance object.
     * @return bool true if activity is a lecture.
     */
    private static function is_activity_lecture($activity) {
        return in_array($activity->modname, mod_personalschedule_config::LECTURESMODNAMES);
    }

    /**
     * Returns true if activity is a practice (in terms of this module).
     * @param cm_info $activity Activity instance object.
     * @return bool true if activity is a practice.
     */
    private static function is_activity_practice($activity) {
        return in_array($activity->modname, mod_personalschedule_config::PRACTICEMODNAMES);
    }

    /**
     * @param int $personalscheduleid Personalization activity instance ID.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $userage User age.
     * @param mod_personalschedule\items\user_view_info[] $useractionsinfo
     * @return category_object[]
     * @throws dml_exception
     * @throws moodle_exception
     */
    private static function get_categories($personalscheduleid, $userid, $courseid, $userage, $useractionsinfo) {
        /*
         * Receives:
         * - Uncompleted activities,
         * - Information about interactions number with each activity.
         */
        $uncompletedactivities = self::personal_items_get_uncompleted_activities($courseid, $userid);
        /** @var category_object[] $categoriesobjects */
        $categoriesobjects = array();

        $cmprops = personalschedule_get_course_modules_props($personalscheduleid);
        usort($uncompletedactivities, function($a, $b) use ($cmprops) {

            if (!key_exists($a->id, $cmprops)) {
                return 0;
            }

            if (!key_exists($b->id, $cmprops)) {
                return 0;
            }

            $propsa = $cmprops[$a->id];
            $propsb = $cmprops[$b->id];

            if ($propsa->weight == $propsb->weight) {
                return 0;
            }

            return ($propsa->weight > $propsb->weight) ? 1 : -1;
        });

        foreach ($uncompletedactivities as $activity) {

            // Gets properties of the activity.

            if (!key_exists($activity->id, $cmprops)) {
                continue;
            }

            $props = $cmprops[$activity->id];

            // We should ignore activities with "IS IGNORED" property, or if there are no properties at all.
            if ($props === false) {
                continue;
            }
            if ($props->is_ignored === 1) {
                continue;
            }

            $islecture = self::is_activity_lecture($activity);
            $ispractice = self::is_activity_practice($activity);

            // We should skip activities with unknown type.
            if (!$islecture && !$ispractice) {
                continue;
            }

            if (!array_key_exists($props->category, $categoriesobjects)) {
                $categoriesobjects[$props->category] = new category_object($props->category);
            }

            $newcategorylearningobject = new category_learning_object($activity);

            if ($islecture) {
                if (array_key_exists($activity->id, $useractionsinfo)) {
                    $newcategorylearningobject->actions =
                        $useractionsinfo[$activity->id]->actions;
                }
                $categoriesobjects[$props->category]->lectures[$activity->id] = $newcategorylearningobject;
            } else if ($ispractice) {
                $categoriesobjects[$props->category]->practices[$activity->id] = $newcategorylearningobject;

                if (array_key_exists($activity->id, $useractionsinfo)) {
                    $userviewinfo = $useractionsinfo[$activity->id];
                    if ($userviewinfo instanceof user_practice_info) {
                        $newcategorylearningobject->actions =
                            $userviewinfo->attempts;

                        $categoriesobjects[$props->category]->ispassed = $userviewinfo->ispassed;
                        $categoriesobjects[$props->category]->attempts = $userviewinfo->attempts;
                    } else {
                        $categoriesobjects[$props->category]->ispassed = $userviewinfo->actions > 0;
                        $categoriesobjects[$props->category]->attempts = $userviewinfo->actions;
                    }
                }
            }
        }

        foreach ($categoriesobjects as $categoryindex => $categoryobjects) {
            if (mod_personalschedule_config::SKIPCOMPLETEDCATEGORIES && $categoryobjects->ispassed) {
                $categoryobjects->shouldbeignored = true;
                unset($categoriesobjects[$categoryindex]);
                continue;
            }

            if (mod_personalschedule_config::SKIPCATEGORIESWITHOUTPRACTICE && count($categoryobjects->practices) == 0) {
                $categoryobjects->shouldbeignored = true;
                unset($categoriesobjects[$categoryindex]);
                continue;
            }

            $attempts = $categoryobjects->attempts;
            if ($attempts > mod_personalschedule_config::MAXATTEMPTSTOIGNORECATEGORY) {
                $categoryobjects->shouldbeignored = true;
                unset($categoriesobjects[$categoryindex]);
                continue;
            }

            foreach ($categoryobjects->lectures as $activityid => $activity) {
                $shoulduse = self::calculate_and_fill_duration_to_object($cmprops,
                    $activityid, $attempts, $activity, $userage);

                if (!$shoulduse) {
                    continue;
                }
                $categoryobjects->add_learning_object($activity, true);
            }

            foreach ($categoryobjects->practices as $activityid => $activity) {
                $shoulduse = self::calculate_and_fill_duration_to_object($cmprops,
                    $activityid, $attempts, $activity, $userage);

                if (!$shoulduse) {
                    continue;
                }
                $categoryobjects->add_learning_object($activity, false);
            }
        }

        // Sorts the categories in total corrected duration descending order.
        usort($categoriesobjects, array("mod_personalschedule_proposer", "categories_sort_by_duration_desc"));
        return $categoriesobjects;
    }

    /**
     * @param int $courseid
     * @param int $userid
     * @return cm_info[]
     * @throws dml_exception
     * @throws moodle_exception
     */
    private static function personal_items_get_uncompleted_activities($courseid, $userid) {
        global $DB;

        $courseobject = $DB->get_record('course', array('id' => $courseid), 'id');
        $modinfo = get_fast_modinfo($courseobject, $userid);
        $ciinfo = new completion_info($courseobject);
        $activities = $ciinfo->get_activities();
        /** @var cm_info[] $uncompletedactivities */
        $uncompletedactivities = array();
        foreach ($activities as $activity) {
            if (in_array($activity->modname, mod_personalschedule_config::IGNOREDMODNAMES)) {
                continue;
            }
            $activitycompletiondata = $ciinfo->get_data($activity, true, $userid, $modinfo);
            if ($activitycompletiondata->completionstate == 0) {
                $uncompletedactivities[] = $activity;
            }
        }
        return $uncompletedactivities;
    }

    /**
     * @param $periodidx int
     * @param $dayidx int
     * @param $weekidx int
     * @param $userschedule schedule
     * @return day_info
     */
    private static function get_today_groupped_free_periods($periodidx,
        $dayidx, $weekidx, $userschedule) {

        list($daybeginperiodidx, $daybegindayidx, $dayendperiodidx, $dayenddayidx) =
            self::get_today_day_ranges($periodidx, $dayidx, $userschedule);

        $dayinfo = new day_info($daybegindayidx, $daybeginperiodidx, $dayenddayidx, $dayendperiodidx, $weekidx);
        $curgroupidx = 0;
        $curperiodidx = $daybeginperiodidx;
        $curdayidx = $daybegindayidx;
        $lastperiodwasfree = false;
        $totalfreehoursincurrentday = 0;

        $schedulestatuses = $userschedule->get_statuses();
        $readinesses = $userschedule->get_readinesses();

        while (true) {
            $tempstatus = $schedulestatuses[$curdayidx][$curperiodidx];

            if ($tempstatus == mod_personalschedule_config::STATUSFREE) {
                if (!$dayinfo->is_group_exists($curgroupidx)) {
                    $dayinfo->add_free_period_group($curgroupidx, new day_free_period_group($curperiodidx));
                }

                $dayinfo->get_group($curgroupidx)->add_period(
                    new day_free_period_group_element(
                        $curperiodidx, self::personal_items_get_sin_wave($totalfreehoursincurrentday),
                        $readinesses[$curperiodidx]));

                $lastperiodwasfree = true;
                $totalfreehoursincurrentday++;
            } else {
                if ($lastperiodwasfree) {
                    $curgroupidx++;
                }
                $lastperiodwasfree = false;
            }

            self::increment_period_idx($curperiodidx, $curdayidx);

            if ($curperiodidx == $dayendperiodidx && $curdayidx == $dayenddayidx) {
                break;
            }
        }
        return $dayinfo;
    }

    /**
     * @param array $cmprops
     * @param $activityid int
     * @param $attempts int
     * @param $activity category_learning_object
     * @param $userage int
     * @return bool
     */
    private static function calculate_and_fill_duration_to_object(
        array $cmprops,
        $activityid,
        $attempts,
        &$activity,
        $userage
    ) {
        $props = $cmprops[$activityid];
        $origindurationinseconds = $props->duration;
        if ($attempts == 0) {
            $newduration = $origindurationinseconds;
        } else {
            $newduration = $origindurationinseconds - ($attempts / $origindurationinseconds);
        }

        if ($newduration <= 0) {
            return false;
        }

        $activity->totaldurationsec = $origindurationinseconds;

        list($memoryduration, $maximumpossiblememoryduration) = self::get_memory_duration_in_seconds_by_age($userage);

        $activity->modifieddurationsec = $newduration +
            ($newduration * ($maximumpossiblememoryduration - $memoryduration));

        return true;
    }

    /**
     * @param $daybeginperiodidx
     * @param $daybegindayidx
     */
    private static function decrement_period_idx(&$daybeginperiodidx, &$daybegindayidx) {
        if ($daybeginperiodidx == mod_personalschedule_config::PERIODINDEXMIN) {
            $daybeginperiodidx = mod_personalschedule_config::PERIODINDEXMAX;
            $daybegindayidx--;
        } else {
            $daybeginperiodidx--;
        }

        if ($daybegindayidx < mod_personalschedule_config::DAYINDEXMIN) {
            $daybegindayidx = mod_personalschedule_config::DAYINDEXMAX;
        }
    }

    /**
     * @param int $dayendperiodidx
     * @param int $dayenddayidx
     */
    private static function increment_period_idx(&$dayendperiodidx, &$dayenddayidx) {
        if ($dayendperiodidx == mod_personalschedule_config::PERIODINDEXMAX) {
            $dayendperiodidx = mod_personalschedule_config::PERIODINDEXMIN;

            if ($dayenddayidx == mod_personalschedule_config::DAYINDEXMAX) {
                $dayenddayidx = mod_personalschedule_config::DAYINDEXMIN;
            } else {
                $dayenddayidx++;
            }
        } else {
            $dayendperiodidx++;
        }

    }


    /**
     * Insert proposed elements into the cache (database table).
     * @param int $userid User ID.
     * @param int $personalscheduleid Personalization module instance ID.
     * @param day_info $dayinfo Information about the day, in which the proposed elements were formed.
     * @param proposed_object[] $elements Proposed elements.
     * @throws coding_exception
     * @throws dml_exception
     */
    private static function insert_proposed_elements($userid, $personalscheduleid, $dayinfo, $elements) {
        global $DB;

        /** @var stdClass[] $itemstoinsert */
        $itemstoinsert = array();

        foreach ($elements as $proposedactivityobject) {
            $itemtoinsert = new stdClass();

            $itemtoinsert->userid = $userid;
            $itemtoinsert->personalschedule = $personalscheduleid;

            $itemtoinsert->daybegindayidx = $dayinfo->daybegindayidx;
            $itemtoinsert->daybeginperiodidx = $dayinfo->daybeginperiodidx;

            $itemtoinsert->begindayidx = $dayinfo->daybegindayidx;
            $itemtoinsert->beginperiodidx = $proposedactivityobject->periodidxbegin;

            $itemtoinsert->modifieddurationsec = $proposedactivityobject->modifieddurationsec;

            $itemtoinsert->weekidx = $dayinfo->weekidx;

            if ($proposedactivityobject instanceof proposed_relax_object) {
                $itemtoinsert->cm = null;
                $itemtoinsert->actions = null;
            } else if ($proposedactivityobject instanceof  proposed_activity_object) {
                $itemtoinsert->cm = $proposedactivityobject->activity->id;
                $itemtoinsert->actions = $proposedactivityobject->actions;
            }

            $itemstoinsert[] = $itemtoinsert;
        }

        $DB->insert_records("personalschedule_proposes", $itemstoinsert);
    }

    /**
     *
     * @param int $periodidx
     * @param int $dayidx
     * @param schedule $userschedule
     * @return array
     */
    private static function get_today_day_ranges($periodidx, $dayidx, $userschedule) {
        $daybeginperiodidx = $periodidx;
        $daybegindayidx = $dayidx;

        $dayendperiodidx = $periodidx;
        $dayenddayidx = $dayidx;

        /*
         * Firstly, we should know when the current day began for the user.
         * We're going back until we find a period with SLEEP status. This will be the start of the day.
         * Then we will go forward to find the end of the day. It's, again, a period with SLEEP status.
         */

        $currentiteration = 0;
        $maxiterations =
            mod_personalschedule_config::DAYINDEXMAX *
            mod_personalschedule_config::PERIODINDEXMAX;

        $schedulestatuses = $userschedule->get_statuses();
        $startedinsleepperiod =
            $schedulestatuses[$daybegindayidx][$daybeginperiodidx] == mod_personalschedule_config::STATUSSLEEP;

        while (true) {
            $currentiteration++;
            if ($currentiteration > $maxiterations) {
                $daybegindayidx = $dayidx;
                $daybeginperiodidx = mod_personalschedule_config::PERIODINDEXMIN;
                break;
            }

            $tempstatus = $schedulestatuses[$daybegindayidx][$daybeginperiodidx];

            if ($startedinsleepperiod) {
                if ($tempstatus != mod_personalschedule_config::STATUSSLEEP) {
                    break;
                }
            } else {
                if ($tempstatus == mod_personalschedule_config::STATUSSLEEP) {
                    self::increment_period_idx($daybeginperiodidx, $daybegindayidx);
                    break;
                }
            }

            if ($startedinsleepperiod) {
                self::increment_period_idx($daybeginperiodidx, $daybegindayidx);
            } else {
                self::decrement_period_idx($daybeginperiodidx, $daybegindayidx);
            }
        }

        if ($startedinsleepperiod) {
            $dayenddayidx = $daybegindayidx;
            $dayendperiodidx = $daybeginperiodidx;
        }

        $currentiteration = 0;
        while (true) {
            $currentiteration++;
            if ($currentiteration > $maxiterations) {
                $dayenddayidx = $dayidx;
                $dayendperiodidx = mod_personalschedule_config::PERIODINDEXMIN;
                break;
            }

            $tempstatus = $schedulestatuses[$dayenddayidx][$dayendperiodidx];

            if ($tempstatus == mod_personalschedule_config::STATUSSLEEP) {
                self::decrement_period_idx($dayendperiodidx, $dayenddayidx);
                break;
            }

            self::increment_period_idx($dayendperiodidx, $dayenddayidx);
        }

        return array($daybeginperiodidx, $daybegindayidx, $dayendperiodidx, $dayenddayidx);
    }

    /**
     * Tries to pick up learning elements from the categoriesobjects and
     * fit them into current dayinfo.
     * @param day_info $dayinfo Information about the day, in which the proposed elements should be generated.
     * @param category_object[] $categoriesobjects Categories with the activities and their information.
     * @return proposed_object[] Generated elements.
     */
    private static function generate_proposed_elements($dayinfo, $categoriesobjects) {
        /** @var proposed_object[] $proposedactivities */
        $proposedactivities = array();
        $totalmodifiedfreehoursincurrentday = 0;

        /** @var category_learning_object[] $proposedobjects */
        $proposedobjects = array();

        foreach ($dayinfo->freeperiodgroups as $daygroup) {
            $groupdurationinhours = $daygroup->get_modified_duration_in_hours();
            $totalmodifiedfreehoursincurrentday += $groupdurationinhours;
            $freehoursleft = $groupdurationinhours;
            $lastperiodidx = $daygroup->periodidxbegin;
            foreach ($categoriesobjects as $categoryobject) {
                $catdurationinhours = $categoryobject->get_modified_duration_in_hours();
                if ($catdurationinhours <= $freehoursleft) {
                    $freehoursleft -= $catdurationinhours;

                    foreach (array_merge($categoryobject->leftlectures, $categoryobject->leftpractices) as $learningobject) {
                        $proposedobjects[$learningobject->activity->id] = $learningobject;
                    }

                    continue;
                }
            }

            usort($categoriesobjects, array("mod_personalschedule_proposer", "categories_sort_by_duration_asc"));

            foreach ($categoriesobjects as $categoryobjects) {
                foreach ($categoryobjects->leftlectures as $lecture) {
                    $activitydurationinhours = $lecture->get_modified_duration_in_hours();
                    if ($activitydurationinhours <= $freehoursleft) {
                        if (key_exists($lecture->activity->id, $proposedobjects)) {
                            continue;
                        }
                        $freehoursleft -= $activitydurationinhours;
                        $proposedobjects[$lecture->activity->id] = $lecture;

                        continue;
                    }
                }
            }

            $relaxitemscount = $freehoursleft * 60 / mod_personalschedule_config::MINIMUMRELAXTIMEINMINUTES;
            $relaxitemsperiodicity = 0;
            if ($relaxitemscount >= 1) {
                $relaxitemsperiodicity = floor(count($proposedobjects) / $relaxitemscount);
            }

            $currentindex = 0;
            $currentrelaxindex = -1;
            foreach ($proposedobjects as $proposedobject) {
                if ($currentindex != 0 && $relaxitemsperiodicity >= 1 && ($currentindex % $relaxitemsperiodicity == 0)) {
                    $proposedactivities[$currentrelaxindex--] = new proposed_relax_object(
                        60 * mod_personalschedule_config::MINIMUMRELAXTIMEINMINUTES,
                        $lastperiodidx, $dayinfo->daybegindayidx, $dayinfo->daybeginperiodidx, $dayinfo->weekidx);
                    $lastperiodidx += mod_personalschedule_config::MINIMUMRELAXTIMEINMINUTES / 60;
                }

                $proposedactivities[$proposedobject->activity->id] =
                    new proposed_activity_object($proposedobject->activity,
                        $proposedobject->modifieddurationsec,
                        $lastperiodidx, $proposedobject->actions,
                        $dayinfo->daybegindayidx, $dayinfo->daybeginperiodidx, $dayinfo->weekidx);
                $lastperiodidx += $proposedobject->get_modified_duration_in_hours();

                $currentindex++;
            }
        }
        return $proposedactivities;
    }


    /**
     * Helper method for sorting the category_objects by modifieddurationsec descending
     * @param $a category_object
     * @param $b category_object
     * @return int
     */
    private static function categories_sort_by_duration_desc($a, $b) {
        if ($a->modifieddurationsec == $b->modifieddurationsec) {
            return 0;
        }
        return ($a->modifieddurationsec < $b->modifieddurationsec) ? 1 : -1;
    }

    /**
     * Helper method for sorting the category_objects by modifieddurationsec ascending
     * @param $a category_object
     * @param $b category_object
     * @return int
     */
    private static function categories_sort_by_duration_asc($a, $b) {
        if ($a->modifieddurationsec == $b->modifieddurationsec) {
            return 0;
        }
        return ($a->modifieddurationsec < $b->modifieddurationsec) ? -1 : 1;
    }

    /**
     * Generates proposed learning elements for the
     * learner. If the proposed elements already were generated (for the current time info), then
     * returns cached items from the database.
     * @param int $personalscheduleid Personalization module instance ID.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $dayidx Current day index.
     * @param int $periodidx Current period index.
     * @param int $weekidx Current week index.
     * @param user_view_info[] $useractionsinfo Array with information about user interactions with each course's activity.
     * @return proposed_object[] Generated elements.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function personal_get_items(
        $personalscheduleid, $userid, $courseid, $dayidx, $periodidx, $weekidx, $useractionsinfo) {

        $userage = personalschedule_get_user_age($personalscheduleid, $userid);

        $categoriesobjects = self::get_categories($personalscheduleid,
            $userid, $courseid, $userage, $useractionsinfo);

        $schedule = personalschedule_get_user_schedule($personalscheduleid, $userid);

        // Groups the current day by free periods.
        $dayinfo = self::get_today_groupped_free_periods($periodidx, $dayidx, $weekidx, $schedule);

        $cachedproposedactivities =
            self::get_all_proposed_items_from_cache($personalscheduleid, $courseid, $userid, $dayinfo);

        $proposedactivitiesalreadyexist = count($cachedproposedactivities) > 0;
        if ($proposedactivitiesalreadyexist) {
            $proposedactivities = $cachedproposedactivities;
        } else {
            $proposedactivities = self::generate_proposed_elements($dayinfo, $categoriesobjects);
            self::insert_proposed_elements($userid, $personalscheduleid, $dayinfo, $proposedactivities);
        }

        return $proposedactivities;
    }


    /**
     * Returns period index, which is based on the current time.
     * @param int $currentunixtime Current UNIX time.
     * @return int|false Period index. If a non-numeric value is used for
     * timestamp, false is returned.
     */
    public static function personal_items_get_period_idx($currentunixtime) {
        $hourmin = date('G', $currentunixtime);
        return $hourmin;
    }

    /**
     * Returns day index, which is based on the current time.
     * @param int $currentunixtime Current UNIX time.
     * @return int|false Day index. If a non-numeric value is used for
     * timestamp, false is returned.
     */
    public static function personal_items_get_day_idx($currentunixtime) {
        // From 0 (sunday) to 6 (saturday).
        $day = date('w', $currentunixtime);

        // Converting to module format (sunday became seventh day).
        if ($day == 0) {
            $day = 7;
        }
        return $day;
    }

    /**
     * Returns timestamp based on current user timezone.
     * Calculated like this: time() + (USER_TIMEZONE_OFFSET - SERVER_TIMEZONE_OFFSET).
     * @return int Timestamp based on current user timezone.
     */
    public static function get_user_current_timestamp() {
        try {
            $datetime = new Datetime('now', core_date::get_server_timezone_object());
        } catch (Exception $e) {
            return time();
        }
        $servertzoffset = $datetime->getoffset();
        $datetime->settimezone(core_date::get_user_timezone_object());
        $usertzoffset = $datetime->getoffset();
        $tzoffset = $usertzoffset - $servertzoffset;
        return $datetime->gettimestamp() + $tzoffset;
    }

    /**
     * Returns proposed user items from cache (database table).
     * @param int $personalscheduleid Personalization module instance id.
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @param day_info $dayinfo If not null, then tries to return items from the specific day.
     * @return proposed_object[] Array with proposed user items.
     */
    public static function get_all_proposed_items_from_cache($personalscheduleid, $courseid, $userid, $dayinfo = null) {
        global $DB;

        $conditions = array();
        $conditions["userid"] = $userid;
        $conditions["personalschedule"] = $personalscheduleid;

        if ($dayinfo != null) {
            $conditions["daybegindayidx"] = $dayinfo->daybegindayidx;
            $conditions["daybeginperiodidx"] = $dayinfo->daybeginperiodidx;
            $conditions["weekidx"] = $dayinfo->weekidx;
        }

        $proposedelements = array();

        try {
            $records = $DB->get_records("personalschedule_proposes", $conditions);

            if (count($records) > 0) {
                $modinfo = get_fast_modinfo($courseid);
            }

            foreach ($records as $record) {
                if ($record->cm == null || $record->actions == null) {
                    $proposedactivityobject = new proposed_relax_object($record->modifieddurationsec,
                        $record->beginperiodidx, $record->daybegindayidx, $record->daybeginperiodidx,
                        $record->weekidx);
                } else {
                    if (isset($modinfo)) {
                        $cm = $modinfo->get_cm($record->cm);
                    } else {
                        continue;
                    }

                    $proposedactivityobject = new mod_personalschedule\items\proposed_activity_object(
                        $cm, $record->modifieddurationsec, $record->beginperiodidx, $record->actions,
                        $record->daybegindayidx, $record->daybeginperiodidx, $record->weekidx);
                }

                $proposedelements[] = $proposedactivityobject;
            }
        } catch (dml_exception $e) {
            return $proposedelements;
        } catch (moodle_exception $e) {
            return $proposedelements;
        } finally {
            return $proposedelements;
        }
    }

    /**
     * This function calculates user's interactions (actions) number with
     * course's activities. The interactions number based on data from table "logstore_standard_log".
     * Also, for the mod_quiz activities there is number of test attempts, which is calculated using info
     * from Grades API.
     * @param int $userid: User ID.
     * @param int $courseid: Course ID.
     * @return mod_personalschedule\items\user_view_info[] Array with user' view info items, where the key is cmid.
     */
    public static function get_user_views_info($userid, $courseid) {
        global $DB;

        $sql = "SELECT cm.id as 'cmid', m.name, cm.instance, COUNT(l.id) AS 'actions'
FROM {logstore_standard_log} l
  JOIN {course_modules} cm ON cm.id = l.contextinstanceid
  JOIN {modules} m ON m.id = cm.module
WHERE  l.userid = :userid
  AND l.courseid = :courseid
    AND l.contextlevel = :contextlevel
GROUP BY l.userid, l.contextinstanceid";

        /** @var user_view_info[] $userviewsinfo */
        $userviewsinfo = array();
        try {
            $results = $DB->get_records_sql($sql, array(
                "userid" => $userid,
                "courseid" => $courseid,
                "contextlevel" => CONTEXT_MODULE,
            ));
            if ($results == false) {
                return $userviewsinfo;
            }

            foreach ($results as $result) {
                $functionname = "mod_personalschedule_proposer::get_".$result->name."_user_view_info";
                if (is_callable($functionname)) {
                    $userviewinfo = call_user_func(
                        $functionname, $userid, $courseid, $result->instance, $result->cmid, $result->actions, $result->cmid);
                } else {
                    $userviewinfo = new user_view_info($result->cmid, $result->actions);
                }

                $userviewsinfo[$result->cmid] = $userviewinfo;
            }
        } catch (dml_exception $ex) {
            return $userviewsinfo;
        }
        return $userviewsinfo;
    }


    /**
     * Returns user_view_info for the mod_quiz activity.
     * Called implicitly by call_user_func(...) in get_user_views_info(...).
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $instance Activity Instance ID.
     * @param int $actions User's interactions count with this activity (from logstore).
     * @param int $id ID from logstore.
     * @return user_view_info
     */
    private static function get_quiz_user_view_info($userid, $courseid, $instance, $cmid, $actions, $id) {
        if (!is_callable("quiz_get_user_attempts")) {
            // TODO: Show error message to admin.
            return new user_practice_info($id, 1, true, false);
        }

        /** @var array $quizattempts */
        $quizattempts = quiz_get_user_attempts($instance, $userid, 'finished', true);
        $attempts = count($quizattempts);
        if (count($quizattempts) >= 1) {
            $quizattempt = reset($quizattempts);
            if ($quizattempt->sumgrades == null) {
                $notrated = true;
                $ispassed = $actions != count($quizattempts);
            } else {
                $notrated = false;

                $gradinginfo = grade_get_grades($courseid, 'mod', 'quiz',
                    $instance);

                $quizgradesinfo = reset($gradinginfo->items);
                $percentofrightanswers = $quizattempt->sumgrades / $quizgradesinfo->grademax * 100;
                $ispassed = $percentofrightanswers >= mod_personalschedule_config::MINPERCENTSTOPASS;
            }

        } else {
            $notrated = true;
            $ispassed = false;
        }

        $userviewinfo = new user_practice_info($id, $attempts, $ispassed, $notrated);
        return $userviewinfo;
    }

    /**
     * Returns user_view_info for the mod_survey activity.
     * Called implicitly by call_user_func(...) in get_user_views_info(...).
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $instance Activity Instance ID.
     * @param int $actions User's interactions count with this activity (from logstore).
     * @param int $id ID from logstore.
     * @return user_view_info
     */
    public static function get_survey_user_view_info($userid, $courseid, $instance, $cmid, $actions, $id) {
        if (!is_callable("survey_already_done")) {
            // TODO: Show error message to admin.
            $passed = true;
        } else {
            $passed = survey_already_done($instance, $userid);
        }

        $userviewinfo = new user_practice_info($id, $passed ? 1 : 0, $passed, false);
        return $userviewinfo;
    }

    /**
     * Returns user_view_info for the mod_assign activity.
     * Called implicitly by call_user_func(...) in get_user_views_info(...).
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $instance Activity Instance ID.
     * @param int $actions User's interactions count with this activity (from logstore).
     * @param int $id ID from logstore.
     * @return user_view_info
     */
    public static function get_assign_user_view_info($userid, $courseid, $instance, $cmid, $actions, $id) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $cm = new stdClass();
        $cm->id = $cmid;
        $cm->course = $courseid;
        $assign = new assign(null, $cm, $courseid);

        if (is_callable(array($assign, "get_user_submission"))) {
            $submission = $assign->get_user_submission($userid, false);
            $ispassed = $submission && $submission->status == ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        } else {
            $ispassed = true;
        }

        $userviewinfo = new user_practice_info($id, $ispassed ? 1 : 0, $ispassed, false);
        return $userviewinfo;
    }

    /**
     * Returns user_view_info for the mod_feedback activity.
     * Called implicitly by call_user_func(...) in get_user_views_info(...).
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $instance Activity Instance ID.
     * @param int $actions User's interactions count with this activity (from logstore).
     * @param int $id ID from logstore.
     * @return user_view_info
     */
    public static function get_feedback_user_view_info($userid, $courseid, $instance, $cmid, $actions, $id) {
        // Feedback can be anonymous, so we can just rely on actions number. It's not important to check
        // if user completes the feedback.
        return new user_practice_info($id, $actions, $actions > 0, false);
    }

    /**
     * Returns user_view_info for the mod_choice activity.
     * Called implicitly by call_user_func(...) in get_user_views_info(...).
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $instance Activity Instance ID.
     * @param int $actions User's interactions count with this activity (from logstore).
     * @param int $id ID from logstore.
     * @return user_view_info
     */
    public static function get_choice_user_view_info($userid, $courseid, $instance, $cmid, $actions, $id) {
        global $DB;
        try {
            $attempts = $DB->count_records('choice_answers', array('choiceid' => $instance, 'userid' => $userid));
        } catch (dml_exception $e) {
            // The variable ispassed will be true, and the activity will be not processed by algorithm.
            $attempts = 1;
        }
        return new user_practice_info($id, $attempts, $attempts > 0, false);
    }
}