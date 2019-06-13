<?php

namespace mod_personalschedule\items;

defined('MOODLE_INTERNAL') || die;

class day_period_info
{

    /** @var int */
    public $weekIdx;

    /** @var int */
    public $dayIdx;

    /** @var int */
    public $periodIdx;


    public function __construct($weekIdx, $dayIdx, $periodIdx)
    {
        $this->weekIdx = $weekIdx;
        $this->dayIdx = $dayIdx;
        $this->periodIdx = $periodIdx;
    }
}