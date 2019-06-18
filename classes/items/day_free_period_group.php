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

class day_free_period_group {
    /** @var day_free_period_group_element[] */
    public $periods = array();

    public $periodidxbegin = 0;

    /** @var int */
    public $totaldurationsec = 0;

    /** @var int */
    public $modifieddurationsec = 0;

    public function __construct($periodidxbegin) {
        $this->periodidxbegin = $periodidxbegin;
    }


    /**
     * @param $dayfreeperiodgroupelement day_free_period_group_element
     */
    public function add_period($dayfreeperiodgroupelement) {
        $this->periods[] = $dayfreeperiodgroupelement;

        // This section below needed to prevents situations where readiness is zero, but duration can't be zero.

        $this->totaldurationsec += 1 * 60 * 60; // Converts to seconds.
        // TODO: Add this magic value to config.
        $this->modifieddurationsec += (max(0.2, $dayfreeperiodgroupelement->readinessvalue)) * 60 * 60;
    }

    /**
     * @return float|int
     */
    public function get_modified_duration_in_hours() {
        return $this->modifieddurationsec / 60 / 60;
    }

    /**
     * @return float|int
     */
    public function get_total_duration_in_hours() {
        return $this->totaldurationsec / 60 / 60;
    }
}