<?php

namespace mod_personalschedule\items;

defined('MOODLE_INTERNAL') || die;

class user_view_info
{
    /** @var int */
    public $cm;

    public $actions = 0;

    /**
     * user_view_info constructor.
     * @param int $cm Course module ID.
     * @param int|float $actions Will be casted into int, even if float passed.
     */
    public function __construct($cm, $actions)
    {
        $this->cm = $cm;
        $this->actions = (int)$actions;
    }
}