<?php

namespace mod_personalschedule\items;

class day_free_period_group
{
    /** @var day_free_period_group_element[] */
    public $periods = array();

    public $periodIdxBegin = 0;

    /** @var int */
    public $totalDurationSec = 0;

    /** @var int */
    public $modifiedDurationSec = 0;

    public function __construct($periodIdxBegin)
    {
        $this->periodIdxBegin = $periodIdxBegin;
    }


    /**
     * @param $dayFreePeriodGroupElement day_free_period_group_element
     */
    public function add_period($dayFreePeriodGroupElement)
    {
        $this->periods[] = $dayFreePeriodGroupElement;
        // чтоб не получилось так, что поставив готовность 0, у нас не будет учитываться период в целом
        $this->totalDurationSec += 1 * 60 * 60; // ибо в секундах
        //TODO: вынести в конфиг
        $this->modifiedDurationSec += (max(0.2, $dayFreePeriodGroupElement->readinessValue)) * 60 * 60;
    }

    /**
     * @return float|int
     */
    public function get_modified_duration_in_hours()
    {
        return $this->modifiedDurationSec / 60 / 60;
    }

    /**
     * @return float|int
     */
    public function get_total_duration_in_hours()
    {
        return $this->totalDurationSec / 60 / 60;
    }
}