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
     * @param $modifieddurationsec int
     * @param $periodidxbegin float|int
     * @param $actions int
     * @param $daybegindayidx int
     * @param $daybeginperiodidx int
     * @param $weekidx int
     */
    public function __construct(
        $activity,
        $modifieddurationsec,
        $periodidxbegin,
        $actions,
        $daybegindayidx,
        $daybeginperiodidx,
        $weekidx
    ) {
        $this->activity = $activity;
        $this->actions = $actions;

        parent::__construct($modifieddurationsec, $periodidxbegin, $daybegindayidx, $daybeginperiodidx, $weekidx);
    }


    /**
     * @param $dayinfo day_info
     * @param $categoryobject category_object
     * @param $periodidxbegin int
     * @return proposed_activity_object[]
     */
    public static function get_proposed_objects_from_category($dayinfo, $categoryobject, $periodidxbegin) {
        $proposedobjects = array();

        $currentperiodidxbegin = $periodidxbegin;

        foreach ($categoryobject->leftlectures as $lecture) {

            $newobject = new proposed_activity_object($lecture->activity,
                $lecture->modifieddurationsec, $currentperiodidxbegin, $lecture->actions,
                $dayinfo->daybegindayidx, $dayinfo->daybeginperiodidx, $dayinfo->weekidx);
            $proposedobjects[] = $newobject;

            $periodidxend = $currentperiodidxbegin + $lecture->modifieddurationsec / 60 / 60;
            $currentperiodidxbegin = $periodidxend;
        }

        foreach ($categoryobject->leftpractices as $practice) {

            $newobject = new proposed_activity_object($practice->activity,
                $practice->modifieddurationsec, $currentperiodidxbegin, $practice->actions,
                $dayinfo->daybegindayidx, $dayinfo->daybeginperiodidx, $dayinfo->weekidx);
            $proposedobjects[] = $newobject;

            $periodidxend = $currentperiodidxbegin + $practice->modifieddurationsec / 60 / 60;
            $currentperiodidxbegin = $periodidxend;
        }

        return $proposedobjects;
    }
}

