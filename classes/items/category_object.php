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
     * @param $learningobject category_learning_object
     * @param $islecture bool
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
     * @return float|int
     */
    public function get_modified_duration_in_hours() {
        return $this->modifieddurationsec / 60 / 60;
    }

    public function __construct($categoryindex) {
        $this->categoryindex = $categoryindex;
    }

}