<?php

namespace mod_personalschedule\items;

defined('MOODLE_INTERNAL') || die;

use mod_personalschedule_config;

class schedule {
    /** @var int[][] Array with schedule statuses in specific day and specific day period.
     * First key - dayidx;
     * Second key - periodidx;
     * Value - CHECK_STATUS (int).
     */
    private $statuses = array();

    /** @var float[] Array with readiness statuses in specific day period.
     * First key - periodidx;
     * Value - readiness status (float [0;1]).
     */
    private $readiness = array();


    /**
     * Adds readiness status into this schedule object.
     * @param int $periodidx Day period index.
     * @param float $readinessstatus Readiness status (float [0;1]).
     * @throws \InvalidArgumentException Throws the exception if the $readinessstatus has incorrect value.
     */
    public function add_readiness_status($periodidx, $readinessstatus) {
        if ($readinessstatus < 0 || $readinessstatus > 1) {
            throw new \InvalidArgumentException(
                "readinessstatus should be at range [0;1]. Input was: $readinessstatus");
        }

        $this->readiness[$periodidx] = $readinessstatus;
    }

    /**
     * Adds schedule status into this schedule object.
     * @param int $dayidx Day index.
     * @param int $periodidx Day period index.
     * @param int $checkstatus Schedule status (free, sleep, busy as integer).
     * @throws \InvalidArgumentException Throws the exception if the $checkstatus has incorrect value.
     */
    public function add_schedule_status($dayidx, $periodidx, $checkstatus) {
        if (!key_exists($dayidx, $this->statuses)) {
            $this->statuses[$dayidx] = array();
        }

        if ($checkstatus != mod_personalschedule_config::statusfree &&
            $checkstatus != mod_personalschedule_config::statusbusy &&
            $checkstatus != mod_personalschedule_config::statussleep) {
            throw new \InvalidArgumentException(
                "checkstatus is unknown. Input was: $checkstatus");
        }

        $this->statuses[$dayidx][$periodidx] = $checkstatus;
    }

    /**
     * Adds schedule status and readiness status into this schedule object.
     * @param int $dayidx Day index.
     * @param int $periodidx Day period index.
     * @param int $checkstatus Schedule status (free, sleep, busy as integer).
     * @param float $readinessstatus Readiness status (float [0;1]).
     * @throws \InvalidArgumentException Throws the exception if the $checkstatus or $readinessstatus
     * have incorrect values.
     */
    public function add_status($dayidx, $periodidx, $checkstatus, $readinessstatus) {
        $this->add_schedule_status($dayidx, $periodidx, $checkstatus);
        $this->add_readiness_status($periodidx, $readinessstatus);
    }

    /**
     * Automatically sets not filled elements in the schedule array with the specified data.
     * @param int $checkstatus Schedule status (free, sleep, busy as integer).
     * @param float $readinessstatus Readiness status (float [0;1]).
     */
    public function fill_empty_day_periods_with_status($checkstatus, $readinessstatus)
    {
        if ($readinessstatus < 0 || $readinessstatus > 1) {
            throw new \InvalidArgumentException(
                "readinessstatus should be at range [0;1]. Input was: $readinessstatus");
        }

        if ($checkstatus != mod_personalschedule_config::statusfree &&
            $checkstatus != mod_personalschedule_config::statusbusy &&
            $checkstatus != mod_personalschedule_config::statussleep) {
            throw new \InvalidArgumentException(
                "checkstatus is unknown. Input was: $checkstatus");
        }

        $mindayidx =  \mod_personalschedule_config::dayindexmin;
        $maxdayidx = \mod_personalschedule_config::dayindexmax;

        $minperiodidx =  \mod_personalschedule_config::periodindexmin;
        $maxperiodidx = \mod_personalschedule_config::periodindexmax;


        for ($dayidx = $mindayidx; $dayidx <= $maxdayidx; $dayidx++) {

            if (!key_exists($dayidx, $this->statuses)) {
                $this->statuses[$dayidx] = array();
            }

            for ($periodidx = $minperiodidx; $periodidx <= $maxperiodidx; $periodidx++) {

                if (!key_exists($periodidx, $this->statuses[$dayidx])) {
                    $this->statuses[$dayidx][$periodidx] = $checkstatus;
                }

                if (!key_exists($periodidx, $this->readiness)) {
                    $this->readiness[$periodidx] = $readinessstatus;
                }
            }
        }
    }

    /**
     * Returns array with user's schedule statuses (sleep, busy, free).
     * If schedule doesn't have a status for any day or period, then it will be automatically
     * filled with BUSY status.
     * @return int[][] Array with user's schedule statuses. First key is a dayidx, second key is a periodidx.
     */
    public function get_statuses()
    {
        $mindayidx =  \mod_personalschedule_config::dayindexmin;
        $maxdayidx = \mod_personalschedule_config::dayindexmax;

        $minperiodidx =  \mod_personalschedule_config::periodindexmin;
        $maxperiodidx = \mod_personalschedule_config::periodindexmax;


        for ($dayidx = $mindayidx; $dayidx <= $maxdayidx; $dayidx++) {

            if (!key_exists($dayidx, $this->statuses)) {
                $this->statuses[$dayidx] = array();
            }

            for ($periodidx = $minperiodidx; $periodidx <= $maxperiodidx; $periodidx++) {

                if (!key_exists($periodidx, $this->statuses[$dayidx])) {
                    $this->statuses[$dayidx][$periodidx] = mod_personalschedule_config::statusbusy;
                }
            }
        }

        return $this->statuses;
    }

    /**
     * Returns array with user's readiness statuses.
     * If schedule doesn't have a status for any day or period, then it will be automatically
     * filled with 0.
     * @return float[]
     */
    public function get_readinesses() {
        $minperiodidx =  \mod_personalschedule_config::periodindexmin;
        $maxperiodidx = \mod_personalschedule_config::periodindexmax;


        for ($periodidx = $minperiodidx; $periodidx <= $maxperiodidx; $periodidx++) {

            if (!key_exists($periodidx, $this->readiness)) {
                $this->readiness[$periodidx] = 0;
            }
        }

        return $this->readiness;
    }
}