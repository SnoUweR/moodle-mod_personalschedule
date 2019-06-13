<?php

namespace mod_personalschedule\items;

defined('MOODLE_INTERNAL') || die;

class user_practice_info extends user_view_info
{
    public $isPassed = false;
    public $notRated = false;
    public $attempts = 0;

    /**
     * user_practice_info constructor.
     * @param int $cm Course module ID.
     * @param int $attempts How many times the user tries to pass the practice.
     * @param bool $isPassed If true, then the practice successfully completed.
     * @param bool $notRated If true, then there was an attempt to pass the practice, but this attempt not rated by teacher yet.
     */
    public function __construct($cm, $attempts, $isPassed, $notRated)
    {
        $this->attempts = $attempts;
        $this->isPassed = $isPassed;
        $this->notRated = $notRated;
        parent::__construct($cm, $attempts);
    }
}