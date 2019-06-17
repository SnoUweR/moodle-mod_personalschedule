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
 *
 * @package    mod_personalschedule
 * @copyright  2019 onwards Vladislav Kovalev snouwer@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_personalschedule\task;

use coding_exception;
use core_user;
use mod_personalschedule_config;

class notify_nonactive_users extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('notifynoactiveusers', 'mod_personalschedule');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;
        try {
            $useridsobjects = $DB->get_records("personalization_usrattempts", null, '',
                'DISTINCT userid');
            $userids = array();
            foreach ($useridsobjects as $useridobject) {
                $userids[] = $useridobject->userid;
            }
            $users = $DB->get_records_list(
                'user', 'id', $userids, '', 'id, currentlogin, firstname');
        } catch (\dml_exception $e) {
            return;
        }
        $time = time();
        foreach ($users as $user) {
            if ($user->currentlogin == 0) continue;
            // TODO: Check if user has all elements completed.

            // If user don't join on courses for a two days.
            if ($time - $user->currentlogin >= (mod_personalschedule_config::daystosendschedulenotify * 24 * 60 * 60)) {
                self::send_notification_message(0,
                    $user->id, $user->firstname, mod_personalschedule_config::daystosendschedulenotify);
            }
        }
    }

    /**
     * Send message (with parameter 'notification' set to true) through Messages API to a specific user.
     * @param int $courseid Course ID.
     * @param int $receiveruserid Receiver User ID.
     * @param string $receiverfirstname Receiver first name.
     * @param int $skippeddays Number of skipped days.
     * @return int|bool The integer ID of the new message or false if there was a problem with submitted data.
     * @throws coding_exception
     */
    private static function send_notification_message($courseid, $receiveruserid, $receiverfirstname,
        $skippeddays) {

        $supportuser = core_user::get_support_user();

        $message = new \core\message\message();
        $message->courseid = $courseid;
        $message->component = 'mod_personalschedule';
        $message->name = 'longofflinereminder';
        $message->userfrom = $supportuser;
        $message->userto = $receiveruserid;
        $message->notification = 1;

        $message->subject = get_string('notifynoactiveusers_title', 'personalschedule');
        $fullhtmlmessage = sprintf(get_string('notifynoactiveusers_message', 'personalschedule'),
            $receiverfirstname, $skippeddays);
        $message->fullmessage = html_to_text($fullhtmlmessage);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $fullhtmlmessage;
        $message->smallmessage = '';
        $message->contexturl = '';
        $message->contexturlname = '';
        return message_send($message);
    }
}