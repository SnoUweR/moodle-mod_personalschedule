<?php

namespace mod_personalschedule\items;

defined('MOODLE_INTERNAL') || die;

class proposed_activity_object extends proposed_object {
    /** @var \cm_info */
    public $activity = null;

    /** @var int */
    public $actions = 0;

    /**
     * proposed_object constructor.
     * @param $activity \cm_info
     * @param $modifiedDurationSec int
     * @param $periodIdxBegin float|int
     * @param $actions int
     * @param $dayBeginDayIdx int
     * @param $dayBeginPeriodIdx int
     * @param $weekIdx int
     */
    public function __construct($activity, $modifiedDurationSec, $periodIdxBegin, $actions,
        $dayBeginDayIdx, $dayBeginPeriodIdx, $weekIdx)
    {
        $this->activity = $activity;
        $this->actions = $actions;

        parent::__construct($modifiedDurationSec, $periodIdxBegin, $dayBeginDayIdx, $dayBeginPeriodIdx, $weekIdx);
    }


    /**
     * @param $dayInfo day_info
     * @param $categoryObject category_object
     * @param $periodIdxBegin int
     * @return proposed_activity_object[]
     */
    public static function get_proposed_objects_from_category($dayInfo, $categoryObject, $periodIdxBegin)
    {
        /** @var $proposedObjects proposed_activity_object[] */
        $proposedObjects = array();

        $currentPeriodIdxBegin = $periodIdxBegin;

        foreach ($categoryObject->leftLectures as $lecture) {

            $newObject = new proposed_activity_object($lecture->activity,
                $lecture->modifiedDurationSec, $currentPeriodIdxBegin, $lecture->actions,
                $dayInfo->dayBeginDayIdx, $dayInfo->dayBeginPeriodIdx, $dayInfo->weekIdx);
            $proposedObjects[] = $newObject;

            $periodIdxEnd = $currentPeriodIdxBegin + $lecture->modifiedDurationSec / 60 / 60;
            $currentPeriodIdxBegin = $periodIdxEnd;
        }

        foreach ($categoryObject->leftPractices as $practice) {

            $newObject = new proposed_activity_object($practice->activity,
                $practice->modifiedDurationSec, $currentPeriodIdxBegin, $practice->actions,
                $dayInfo->dayBeginDayIdx, $dayInfo->dayBeginPeriodIdx, $dayInfo->weekIdx);
            $proposedObjects[] = $newObject;

            $periodIdxEnd = $currentPeriodIdxBegin + $practice->modifiedDurationSec / 60 / 60;
            $currentPeriodIdxBegin = $periodIdxEnd;
        }

        return $proposedObjects;
    }
}

