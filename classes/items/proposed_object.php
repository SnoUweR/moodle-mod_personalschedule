<?php

namespace mod_personalschedule\items;

defined('MOODLE_INTERNAL') || die;

abstract class proposed_object {
    /** @var int */
    public $modifiedDurationSec = 0;

    /** @var day_period_info Описывает пользовательские сутки (не обычные), к которым принадлежит
    данный предложенный учебный элемент */
    public $dayPeriodInfo;

    /** @var float|int */
    public $periodIdxBegin = 0;
    /** @var float|int */
    public $periodIdxEnd = 0;

    /**
     * proposed_object constructor.
     * @param $modifiedDurationSec int
     * @param $periodIdxBegin float|int
     * @param $dayBeginDayIdx int
     * @param $dayBeginPeriodIdx int
     * @param $weekIdx int
     */
    public function __construct($modifiedDurationSec, $periodIdxBegin,
        $dayBeginDayIdx, $dayBeginPeriodIdx, $weekIdx)
    {
        $this->modifiedDurationSec = (int)ceil($modifiedDurationSec);
        $this->periodIdxBegin = $periodIdxBegin;
        $this->periodIdxEnd = $periodIdxBegin + ($modifiedDurationSec / 60 / 60);

        $this->dayPeriodInfo = new day_period_info($weekIdx, $dayBeginDayIdx, $dayBeginPeriodIdx);
    }
}

