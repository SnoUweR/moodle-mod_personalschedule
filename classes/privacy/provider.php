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
 * Data provider.
 *
 * @package    mod_personalschedule
 * @copyright  2019 onwards Vladislav Kovalev snouwer@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_personalschedule\privacy;
defined('MOODLE_INTERNAL') || die();

use context;
use context_helper;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

require_once($CFG->dirroot . '/mod/personalschedule/lib.php');


class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns metadata.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table('personalschedule_readiness', [
            'userid' => 'privacy:metadata:readiness:userid',
            'period_idx' => 'privacy:metadata:readiness:period_idx',
            'check_status' => 'privacy:metadata:readiness:check_status',
        ], 'privacy:metadata:readiness');

        $collection->add_database_table('personalschedule_proposes', [
            'userid' => 'privacy:metadata:proposes:userid',
            'actions' => 'privacy:metadata:proposes:actions',
        ], 'privacy:metadata:proposes');

        $collection->add_database_table('personalschedule_schedules', [
            'userid' => 'privacy:metadata:schedules:userid',
            'period_idx' => 'privacy:metadata:schedules:period_idx',
            'day_idx' => 'privacy:metadata:schedules:day_idx',
            'check_status' => 'privacy:metadata:schedules:check_status',
        ], 'privacy:metadata:schedules');

        $collection->add_database_table('personalschedule_user_props', [
            'userid' => 'privacy:metadata:user_props:userid',
            'age' => 'privacy:metadata:user_props:age',
        ], 'privacy:metadata:user_props');

        $collection->add_database_table('personalschedule_usrattempts', [
            'userid' => 'privacy:metadata:usrattempts:userid',
            'timecreated' => 'privacy:metadata:usrattempts:timecreated',
            'timemodified' => 'privacy:metadata:usrattempts:timemodified',
        ], 'privacy:metadata:usrattempts');

        $collection->add_database_table('personalschedule_analysis', [
            'userid' => 'privacy:metadata:analysis:userid',
            'notes' => 'privacy:metadata:analysis:notes',
        ], 'privacy:metadata:analysis');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        $sql = "SELECT DISTINCT ctx.id
              FROM {personalschedule} s
              JOIN {modules} m
                ON m.name = :personalschedule
              JOIN {course_modules} cm
                ON cm.instance = s.id
               AND cm.module = m.id
              JOIN {context} ctx
                ON ctx.instanceid = cm.id
               AND ctx.contextlevel = :modulelevel
         LEFT JOIN {personalschedule_proposes} sp
                ON sp.personalschedule = s.id
               AND sp.userid = :userid1
         LEFT JOIN {personalschedule_readiness} sr
                ON sr.personalschedule = s.id
               AND sr.userid = :userid2
			LEFT JOIN {personalschedule_schedules} ss
                ON ss.personalschedule = s.id
               AND ss.userid = :userid3
			LEFT JOIN {personalschedule_user_props} sup
                ON sup.personalschedule = s.id
               AND sup.userid = :userid4	
			LEFT JOIN {personalschedule_usrattempts} sua
                ON sua.personalschedule = s.id
               AND sua.userid = :userid5							      
         LEFT JOIN {personalschedule_analysis} san
                ON san.personalschedule = s.id
               AND san.userid = :userid6
             WHERE (sp.id IS NOT NULL
                OR sr.id IS NOT NULL
					 OR ss.id IS NOT NULL
					 OR sup.id IS NOT NULL
					 OR sua.id IS NOT NULL
					 OR san.id IS NOT NULL)";

        $params = [
            'personalschedule' => 'personalschedule',
            'modulelevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
            'userid5' => $userid,
            'userid6' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $user = $contextlist->get_user();
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $schedulesql = "SELECT cm.id AS cmid,
                       qa.*
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {personalschedule} q ON q.id = cm.instance
            INNER JOIN {personalschedule_schedules} qa ON qa.personalschedule = q.id
                 WHERE c.id {$contextsql}
                       AND qa.userid = :userid
              ORDER BY cm.id, qa.day_idx ASC, qa.period_idx ASC";
        $params = ['modname' => 'personalschedule', 'contextlevel' => CONTEXT_MODULE, 'userid' => $user->id] + $contextparams;
        $schedule = $DB->get_recordset_sql($schedulesql, $params);

        $readinesssql = "SELECT cm.id AS cmid,
                       qa.*
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {personalschedule} q ON q.id = cm.instance
            INNER JOIN {personalschedule_readiness} qa ON qa.personalschedule = q.id
                 WHERE c.id {$contextsql}
                       AND qa.userid = :userid
              ORDER BY cm.id, qa.period_idx ASC";

        $readinessrecords = $DB->get_recordset_sql($readinesssql, $params);

        $usrattemptssql = "SELECT cm.id AS cmid,
                       qa.*
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {personalschedule} q ON q.id = cm.instance
            INNER JOIN {personalschedule_usrattempts} qa ON qa.personalschedule = q.id
                 WHERE c.id {$contextsql}
                       AND qa.userid = :userid
              ORDER BY cm.id ASC";

        $usrattempts = $DB->get_recordset_sql($usrattemptssql, $params);

        $userpropssql = "SELECT cm.id AS cmid,
                       qa.*
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {personalschedule} q ON q.id = cm.instance
            INNER JOIN {personalschedule_user_props} qa ON qa.personalschedule = q.id
                 WHERE c.id {$contextsql}
                       AND qa.userid = :userid
              ORDER BY cm.id ASC";

        $userprops = $DB->get_recordset_sql($userpropssql, $params);

        $proposessql = "SELECT cm.id AS cmid,
                       qa.*
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {personalschedule} q ON q.id = cm.instance
            INNER JOIN {personalschedule_proposes} qa ON qa.personalschedule = q.id
                 WHERE c.id {$contextsql}
                       AND qa.userid = :userid
              ORDER BY cm.id ASC";


        $proposes = $DB->get_recordset_sql($proposessql, $params);

        $analysissql = "SELECT cm.id AS cmid,
                       qa.*
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {personalschedule} q ON q.id = cm.instance
            INNER JOIN {personalschedule_analysis} qa ON qa.personalschedule = q.id
                 WHERE c.id {$contextsql}
                       AND qa.userid = :userid
              ORDER BY cm.id ASC";

        $analysisrecords = $DB->get_recordset_sql($analysissql, $params);

        /** @var array[][][] $attemptdata */
        $attemptdata = [];


        foreach ($schedule as $schedulerecord) {
            if (!key_exists($schedulerecord->cmid, $attemptdata)) {
                $attemptdata[$schedulerecord->cmid] = array();
                $attemptdata[$schedulerecord->cmid]['schedule'] = array();
            }

            if (!key_exists($schedulerecord->day_idx, $attemptdata[$schedulerecord->cmid]['schedule'])) {
                $attemptdata[$schedulerecord->cmid]['schedule'][$schedulerecord->day_idx] = array();
            }

            $attemptdata[$schedulerecord->cmid]['schedule'][$schedulerecord->day_idx][$schedulerecord->period_idx] =
                $schedulerecord->check_status;
        }
        $schedule->close();

        foreach ($readinessrecords as $readinessrecord) {
            if (!key_exists($readinessrecord->cmid, $attemptdata)) {
                $attemptdata[$readinessrecord->cmid] = array();
                $attemptdata[$readinessrecord->cmid]['readiness'] = array();
            }

            $attemptdata[$readinessrecord->cmid]['readiness'][$readinessrecord->period_idx] =
                $readinessrecord->check_status;
        }
        $readinessrecords->close();

        foreach ($usrattempts as $usrattempt) {
            if (!key_exists($usrattempt->cmid, $attemptdata)) {
                $attemptdata[$usrattempt->cmid] = array();
            }

            $attemptdata[$usrattempt->cmid]['timecreated'] = transform::datetime($usrattempt->timecreated);
            $attemptdata[$usrattempt->cmid]['timemodified'] = transform::datetime($usrattempt->timemodified);
        }
        $usrattempts->close();

        foreach ($userprops as $userprop) {
            if (!key_exists($userprop->cmid, $attemptdata)) {
                $attemptdata[$userprop->cmid] = array();
            }

            $attemptdata[$userprop->cmid]['age'] = $userprop->age;
        }
        $userprops->close();

        foreach ($analysisrecords as $analysisrecord) {
            if (!key_exists($analysisrecord->cmid, $attemptdata)) {
                $attemptdata[$analysisrecord->cmid] = array();
            }

            $attemptdata[$analysisrecord->cmid]['adminnote'] = $analysisrecord->notes;
        }
        $analysisrecords->close();

        foreach ($proposes as $proposeelement) {
            if (!key_exists($proposeelement->cmid, $attemptdata)) {
                $attemptdata[$proposeelement->cmid] = array();
                $attemptdata[$proposeelement->cmid]['proposes'] = array();
            }

            $attemptdata[$proposeelement->cmid]['proposes'][$proposeelement->id] = array(
                "cm" => $proposeelement->cm,
                "actions" => $proposeelement->actions,
            );
        }
        $proposes->close();

        foreach ($attemptdata as $cmid => $cmdata) {
            if (!empty($attemptdata[$cmid])) {
                $context = context_module::instance($cmid);
                // Fetch the generic module data for the questionnaire.
                $contextdata = helper::get_context_data($context, $user);
                // Merge with attempt data and write it.
                $contextdata = (object)array_merge((array)$contextdata, $attemptdata[$cmid]);
                writer::with_context($context)->export_data([], $contextdata);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        if ($personalscheduleid = static::get_personalschedule_id_from_context($context)) {
            $DB->delete_records('personalschedule_analysis', ['personalschedule' => $personalscheduleid]);
            $DB->delete_records('personalschedule_readiness', ['personalschedule' => $personalscheduleid]);
            $DB->delete_records('personalschedule_proposes', ['personalschedule' => $personalscheduleid]);
            $DB->delete_records('personalschedule_schedules', ['personalschedule' => $personalscheduleid]);
            $DB->delete_records('personalschedule_user_props', ['personalschedule' => $personalscheduleid]);
            $DB->delete_records('personalschedule_usrattempts', ['personalschedule' => $personalscheduleid]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $cmids = array_reduce($contextlist->get_contexts(), function($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[] = $context->instanceid;
            }
            return $carry;
        }, []);
        if (empty($cmids)) {
            return;
        }

        // Fetch the personalschedule IDs.
        list($insql, $inparams) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $sql = "
            SELECT s.id
              FROM {personalschedule} s
              JOIN {modules} m
                ON m.name = :personalschedule
              JOIN {course_modules} cm
                ON cm.instance = s.id
               AND cm.module = m.id
             WHERE cm.id $insql";
        $params = array_merge($inparams, ['personalschedule' => 'personalschedule']);
        $personalscheduleids = $DB->get_fieldset_sql($sql, $params);

        // Delete all the things.
        list($insql, $inparams) = $DB->get_in_or_equal($personalscheduleids, SQL_PARAMS_NAMED);
        $params = array_merge($inparams, ['userid' => $userid]);
        $DB->delete_records_select(
            'personalschedule_analysis', "personalschedule $insql AND userid = :userid", $params);
        $DB->delete_records_select(
            'personalschedule_readiness', "personalschedule $insql AND userid = :userid", $params);
        $DB->delete_records_select(
            'personalschedule_proposes', "personalschedule $insql AND userid = :userid", $params);
        $DB->delete_records_select(
            'personalschedule_schedules', "personalschedule $insql AND userid = :userid", $params);
        $DB->delete_records_select(
            'personalschedule_user_props', "personalschedule $insql AND userid = :userid", $params);
        $DB->delete_records_select(
            'personalschedule_usrattempts', "personalschedule $insql AND userid = :userid", $params);
        $DB->delete_records_select(
            'personalschedule_analysis', "personalschedule $insql AND userid = :userid", $params);
    }

    /**
     * Get a personalschedule ID from its context.
     *
     * @param context_module $context The module context.
     * @return int
     */
    protected static function get_personalschedule_id_from_context(context_module $context) {
        $cm = get_coursemodule_from_id('personalschedule', $context->instanceid);
        return $cm ? (int) $cm->instance : 0;
    }
}
