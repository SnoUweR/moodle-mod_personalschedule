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

class user_practice_info extends user_view_info {
    public $ispassed = false;
    public $notrated = false;
    public $attempts = 0;

    /**
     * user_practice_info constructor.
     * @param int $cm Course module ID.
     * @param int $attempts How many times the user tries to pass the practice.
     * @param bool $ispassed If true, then the practice successfully completed.
     * @param bool $notrated If true, then there was an attempt to pass the practice, but this attempt not rated by teacher yet.
     */
    public function __construct($cm, $attempts, $ispassed, $notrated) {
        $this->attempts = $attempts;
        $this->ispassed = $ispassed;
        $this->notrated = $notrated;
        parent::__construct($cm, $attempts);
    }
}