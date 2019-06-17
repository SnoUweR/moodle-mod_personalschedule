<?php

namespace mod_personalschedule\items;

defined('MOODLE_INTERNAL') || die;

abstract class proposed_object {
    /** @var int */
    public $modifieddurationsec = 0;

    /** @var day_period_info Описывает пользовательские сутки (не обычные), к которым принадлежит
    данный предложенный учебный элемент */
    public $dayperiodinfo;

    /** @var float|int */
    public $periodidxbegin = 0;
    /** @var float|int */
    public $periodidxend = 0;

    /**
     * proposed_object constructor.
     * @param $modifieddurationsec int
     * @param $periodidxbegin float|int
     * @param $daybegindayidx int
     * @param $daybeginperiodidx int
     * @param $weekidx int
     */
    public function __construct($modifieddurationsec, $periodidxbegin,
        $daybegindayidx, $daybeginperiodidx, $weekidx)
    {
        $this->modifieddurationsec = (int)ceil($modifieddurationsec);
        $this->periodidxbegin = $periodidxbegin;
        $this->periodidxend = $periodidxbegin + ($modifieddurationsec / 60 / 60);

        $this->dayperiodinfo = new day_period_info($weekidx, $daybegindayidx, $daybeginperiodidx);
    }
}

