<?php

namespace mod_personalschedule\items;

defined('MOODLE_INTERNAL') || die;

use mod_personalschedule_config;

class schedule {
    /** @var int[][] Array with schedule statuses in specific day and specific day period.
     * First key - dayIdx;
     * Second key - periodIdx;
     * Value - CHECK_STATUS (int).
     */
    private $statuses = array();

    /** @var float[] Array with readiness statuses in specific day period.
     * First key - periodIdx;
     * Value - readiness status (float [0;1]).
     */
    private $readiness = array();


    /**
     * Adds readiness status into this schedule object.
     * @param int $periodIdx Day period index.
     * @param float $readinessStatus Readiness status (float [0;1]).
     * @throws \InvalidArgumentException Throws the exception if the $readinessStatus has incorrect value.
     */
    public function add_readiness_status($periodIdx, $readinessStatus) {
        if ($readinessStatus < 0 || $readinessStatus > 1) {
            throw new \InvalidArgumentException(
                "readinessStatus should be at range [0;1]. Input was: $readinessStatus");
        }

        $this->readiness[$periodIdx] = $readinessStatus;
    }

    /**
     * Adds schedule status into this schedule object.
     * @param int $dayIdx Day index.
     * @param int $periodIdx Day period index.
     * @param int $checkStatus Schedule status (free, sleep, busy as integer).
     * @throws \InvalidArgumentException Throws the exception if the $checkStatus has incorrect value.
     */
    public function add_schedule_status($dayIdx, $periodIdx, $checkStatus) {
        if (!key_exists($dayIdx, $this->statuses)) {
            $this->statuses[$dayIdx] = array();
        }

        if ($checkStatus != mod_personalschedule_config::statusFree &&
            $checkStatus != mod_personalschedule_config::statusBusy &&
            $checkStatus != mod_personalschedule_config::statusSleep) {
            throw new \InvalidArgumentException(
                "checkStatus is unknown. Input was: $checkStatus");
        }

        $this->statuses[$dayIdx][$periodIdx] = $checkStatus;
    }

    /**
     * Adds schedule status and readiness status into this schedule object.
     * @param int $dayIdx Day index.
     * @param int $periodIdx Day period index.
     * @param int $checkStatus Schedule status (free, sleep, busy as integer).
     * @param float $readinessStatus Readiness status (float [0;1]).
     * @throws \InvalidArgumentException Throws the exception if the $checkStatus or $readinessStatus
     * have incorrect values.
     */
    public function add_status($dayIdx, $periodIdx, $checkStatus, $readinessStatus) {
        $this->add_schedule_status($dayIdx, $periodIdx, $checkStatus);
        $this->add_readiness_status($periodIdx, $readinessStatus);
    }

    /**
     * Automatically sets not filled elements in the schedule array with the specified data.
     * @param int $checkStatus Schedule status (free, sleep, busy as integer).
     * @param float $readinessStatus Readiness status (float [0;1]).
     */
    public function fill_empty_day_periods_with_status($checkStatus, $readinessStatus)
    {
        if ($readinessStatus < 0 || $readinessStatus > 1) {
            throw new \InvalidArgumentException(
                "readinessStatus should be at range [0;1]. Input was: $readinessStatus");
        }

        if ($checkStatus != mod_personalschedule_config::statusFree &&
            $checkStatus != mod_personalschedule_config::statusBusy &&
            $checkStatus != mod_personalschedule_config::statusSleep) {
            throw new \InvalidArgumentException(
                "checkStatus is unknown. Input was: $checkStatus");
        }

        $minDayIdx =  \mod_personalschedule_config::dayIndexMin;
        $maxDayIdx = \mod_personalschedule_config::dayIndexMax;

        $minPeriodIdx =  \mod_personalschedule_config::periodIndexMin;
        $maxPeriodIdx = \mod_personalschedule_config::periodIndexMax;


        for ($dayIdx = $minDayIdx; $dayIdx <= $maxDayIdx; $dayIdx++) {

            if (!key_exists($dayIdx, $this->statuses)) {
                $this->statuses[$dayIdx] = array();
            }

            for ($periodIdx = $minPeriodIdx; $periodIdx <= $maxPeriodIdx; $periodIdx++) {

                if (!key_exists($periodIdx, $this->statuses[$dayIdx])) {
                    $this->statuses[$dayIdx][$periodIdx] = $checkStatus;
                }

                if (!key_exists($periodIdx, $this->readiness)) {
                    $this->readiness[$periodIdx] = $readinessStatus;
                }
            }
        }
    }

    /**
     * Returns array with user's schedule statuses (sleep, busy, free).
     * If schedule doesn't have a status for any day or period, then it will be automatically
     * filled with BUSY status.
     * @return int[][] Array with user's schedule statuses. First key is a dayIdx, second key is a periodIdx.
     */
    public function get_statuses()
    {
        $minDayIdx =  \mod_personalschedule_config::dayIndexMin;
        $maxDayIdx = \mod_personalschedule_config::dayIndexMax;

        $minPeriodIdx =  \mod_personalschedule_config::periodIndexMin;
        $maxPeriodIdx = \mod_personalschedule_config::periodIndexMax;


        for ($dayIdx = $minDayIdx; $dayIdx <= $maxDayIdx; $dayIdx++) {

            if (!key_exists($dayIdx, $this->statuses)) {
                $this->statuses[$dayIdx] = array();
            }

            for ($periodIdx = $minPeriodIdx; $periodIdx <= $maxPeriodIdx; $periodIdx++) {

                if (!key_exists($periodIdx, $this->statuses[$dayIdx])) {
                    $this->statuses[$dayIdx][$periodIdx] = mod_personalschedule_config::statusBusy;
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
        $minPeriodIdx =  \mod_personalschedule_config::periodIndexMin;
        $maxPeriodIdx = \mod_personalschedule_config::periodIndexMax;


        for ($periodIdx = $minPeriodIdx; $periodIdx <= $maxPeriodIdx; $periodIdx++) {

            if (!key_exists($periodIdx, $this->readiness)) {
                $this->readiness[$periodIdx] = 0;
            }
        }

        return $this->readiness;
    }
}