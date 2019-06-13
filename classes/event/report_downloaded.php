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
 * The mod_personalschedule report downloaded event.
 *
 * @package    mod_personalschedule
 * @copyright  2019 onwards Vladislav Kovalev snouwer@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_personalschedule\event;

defined('MOODLE_INTERNAL') || die();

class report_downloaded extends \core\event\base {

    /**
     * Set basic properties for the event.
     */
    protected function init() {
        $this->data['objecttable'] = 'personalschedule';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventreportdownloaded', 'mod_personalschedule');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' downloaded the report for the personalschedule with course module id '
        $this->contextinstanceid'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        $params = array('id' => $this->contextinstanceid, 'type' => $this->other['type']);
        if (isset($this->other['groupid'])) {
            $params['group'] = $this->other['groupid'];
        }
        return new \moodle_url("/mod/personalschedule/download.php", $params);
    }

    /**
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, "personalschedule", "download", $this->get_url(), $this->objectid, $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (empty($this->other['type'])) {
            throw new \coding_exception('The \'type\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'personalschedule', 'restore' => 'personalschedule');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['groupid'] = array('db' => 'groups', 'restore' => 'group');

        return $othermapped;
    }
}
