<?php

namespace mod_personalschedule\items;

use cm_info;

class category_learning_object
{
    /** @var cm_info Moodle activity object for this learning object. */
    public $activity = null;

    /** @var int Number of user's interactions with this learning object (activity).
     * Can be number of test attempts, number of views, etc. Depends on the activity module type.
     * See get_mod_***_user_view_info(...) functions from external.php.
     */
    public $actions = 0;

    /** @var int Approximate duration (in seconds) of this learning object. Equals to duration
     * property from the activity properties, that are set by course's admin in the mod_personalschedule settings.
     */
    public $totalDurationSec = 0;

    /** @var int Modified duration (in seconds) of this learning object. Calculated from $totalDurationSec with
     * some corrections that are based on user's age and number of interactions ($actions).
     */
    public $modifiedDurationSec = 0;

    /**
     * @return float|int Just returns modified duration, but in hours, not seconds.
     */
    public function get_modified_duration_in_hours()
    {
        return $this->modifiedDurationSec / 60 / 60;
    }


    /**
     * category_learning_object constructor.
     * @param cm_info $activity
     */
    public function __construct($activity)
    {
        $this->activity = $activity;
    }
}