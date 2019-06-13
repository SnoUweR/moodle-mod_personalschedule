<?php

namespace mod_personalschedule\items;

class day_info
{
    /** @var day_free_period_group[] */
    public $freePeriodGroups = array();

    public $dayBeginPeriodIdx = 0;
    public $dayBeginDayIdx = 0;
    public $dayEndPeriodIdx = 0;
    public $dayEndDayIdx = 0;
    public $weekIdx = 0;

    public function __construct($dayBeginDayIdx, $dayBeginPeriodIdx, $dayEndDayIdx, $dayEndPeriodIdx, $weekIdx)
    {
        $this->dayBeginDayIdx = $dayBeginDayIdx;
        $this->dayBeginPeriodIdx = $dayBeginPeriodIdx;

        $this->dayEndDayIdx = $dayEndDayIdx;
        $this->dayEndPeriodIdx = $dayEndPeriodIdx;

        $this->weekIdx = $weekIdx;
    }

    /**
     * @param $groupIdx int
     * @return bool
     */
    public function is_group_exists($groupIdx)
    {
        return array_key_exists($groupIdx, $this->freePeriodGroups);
    }

    /**
     * @param $groupIdx int
     * @return bool|day_free_period_group
     */
    public function get_group($groupIdx)
    {
        if (!$this->is_group_exists($groupIdx)) return false;

        return $this->freePeriodGroups[$groupIdx];
    }


    /**
     * @param $groupIdx int
     * @param $freePeriodGroup day_free_period_group
     */
    public function add_free_period_group($groupIdx, $freePeriodGroup)
    {
        $this->freePeriodGroups[$groupIdx] = $freePeriodGroup;
    }
}