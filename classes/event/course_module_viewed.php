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
 * The mod_personalschedule course module viewed event.
 *
 * @package    mod_personalschedule
 * @copyright  2019 onwards Vladislav Kovalev snouwer@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_personalschedule\event;

defined('MOODLE_INTERNAL') || die();

class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Set basic properties for the event.
     */
    protected function init() {
        $this->data['objecttable'] = 'personalschedule';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, $this->objecttable, 'view '. $this->other['viewed'], 'view.php?id=' .
            $this->contextinstanceid, $this->objectid, $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (empty($this->other['viewed'])) {
            throw new \coding_exception('Other must contain the key viewed.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'personalschedule', 'restore' => 'personalschedule');
    }

    public static function get_other_mapping() {
        // No need to map 'viewed'.
        return false;
    }
}
