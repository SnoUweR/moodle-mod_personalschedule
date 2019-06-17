<?php

namespace mod_personalschedule\items;

defined('MOODLE_INTERNAL') || die;

class user_practice_info extends user_view_info
{
    public $ispassed = false;
    public $notrated = false;
    public $attempts = 0;

    /**
     * user_practice_info constructor.
     * @param int $cm Course module ID.
     * @param int $attempts How many times the user tries to pass the practice.
     * @param bool $ispassed If true, then the practice successfully completed.
     * @param bool $notrated If true, then there was an attempt to pass the practice, but this attempt not rated by teacher yet.
     */
    public function __construct($cm, $attempts, $ispassed, $notrated)
    {
        $this->attempts = $attempts;
        $this->ispassed = $ispassed;
        $this->notrated = $notrated;
        parent::__construct($cm, $attempts);
    }
}