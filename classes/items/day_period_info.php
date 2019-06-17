<?php

namespace mod_personalschedule\items;

defined('MOODLE_INTERNAL') || die;

class day_period_info
{

    /** @var int */
    public $weekidx;

    /** @var int */
    public $dayidx;

    /** @var int */
    public $periodidx;


    public function __construct($weekidx, $dayidx, $periodidx)
    {
        $this->weekidx = $weekidx;
        $this->dayidx = $dayidx;
        $this->periodidx = $periodidx;
    }
}