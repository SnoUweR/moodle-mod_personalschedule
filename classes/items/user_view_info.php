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

class user_view_info {
    /** @var int */
    public $cm;

    public $actions = 0;

    /**
     * user_view_info constructor.
     * @param int $cm Course module ID.
     * @param int|float $actions Will be casted into int, even if float passed.
     */
    public function __construct($cm, $actions) {
        $this->cm = $cm;
        $this->actions = (int)$actions;
    }
}