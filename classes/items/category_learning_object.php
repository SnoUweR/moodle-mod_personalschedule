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

class category_learning_object {
    /** @var cm_info Moodle activity object for this learning object. */
    public $activity = null;

    /** @var int Number of user's interactions with this learning object (activity).
     * Can be number of test attempts, number of views, etc. Depends on the activity module type.
     * See get_mod_***_user_view_info(...) functions from external.php.
     */
    public $actions = 0;

    /** @var int Approximate duration (in seconds) of this learning object. Equals to duration
     * property from the activity properties, that are set by course's admin in the mod_personalschedule settings.
     */
    public $totaldurationsec = 0;

    /** @var int Modified duration (in seconds) of this learning object. Calculated from $totaldurationsec with
     * some corrections that are based on user's age and number of interactions ($actions).
     */
    public $modifieddurationsec = 0;

    /**
     * @return float|int Just returns modified duration, but in hours, not seconds.
     */
    public function get_modified_duration_in_hours() {
        return $this->modifieddurationsec / 60 / 60;
    }


    /**
     * category_learning_object constructor.
     * @param cm_info $activity
     */
    public function __construct($activity) {
        $this->activity = $activity;
    }
}