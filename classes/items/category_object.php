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

use cm_info;

defined('MOODLE_INTERNAL') || die;

class category_object
{
    public $categoryindex = 0;

    /** @var category_learning_object[] */
    public $lectures = array();
    /** @var category_learning_object[] */
    public $practices = array();
    /** @var category_learning_object[] */
    public $leftlectures = array();
    /** @var category_learning_object[] */
    public $leftpractices = array();

    public $shouldbeignored = false;

    public $ispassed = false;

    public $attempts = 0;

    public $modifieddurationsec = 0;
    public $totaldurationsec = 0;


    /**
     * Returns total objects in this category.
     * @return int Count of total objects in this category.
     */
    public function get_total_objects() {
        return count($this->leftlectures) + count($this->leftpractices);
    }

    /**
     * @param category_learning_object $learningobject
     * @param bool $islecture
     */
    public function add_learning_object($learningobject, $islecture) {
        if ($islecture) {
            $this->leftlectures[] = $learningobject;
        } else {
            $this->leftpractices[] = $learningobject;
        }

        $this->totaldurationsec += $learningobject->totaldurationsec;
        $this->modifieddurationsec += $learningobject->modifieddurationsec;
    }

    /**
     * Tries to remove a learning object with specific activity from this category.
     * Returns true if the object found and removed. False if the object not found.
     * @param cm_info $activity Moodle activity that need to be removed from this category.
     * @return bool True if the object found and removed. False if the object not found.
     */
    public function remove_learning_object($activity) {
        foreach ($this->leftlectures as $key => $lecture) {
            if ($lecture->activity === $activity) {
                unset($this->leftlectures[$key]);
                return true;
            }
        }

        foreach ($this->leftpractices as $key => $practice) {
            if ($practice->activity === $activity) {
                unset($this->leftpractices[$key]);
                return true;
            }
        }

        return false;
    }

    /**
     * @return float|int
     */
    public function get_modified_duration_in_hours() {
        return $this->modifieddurationsec / 60 / 60;
    }

    public function __construct($categoryindex) {
        $this->categoryindex = $categoryindex;
    }

}