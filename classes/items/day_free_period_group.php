<?php

namespace mod_personalschedule\items;

class day_free_period_group
{
    /** @var day_free_period_group_element[] */
    public $periods = array();

    public $periodidxbegin = 0;

    /** @var int */
    public $totaldurationsec = 0;

    /** @var int */
    public $modifieddurationsec = 0;

    public function __construct($periodidxbegin)
    {
        $this->periodidxbegin = $periodidxbegin;
    }


    /**
     * @param $dayfreeperiodgroupelement day_free_period_group_element
     */
    public function add_period($dayfreeperiodgroupelement)
    {
        $this->periods[] = $dayfreeperiodgroupelement;
        // чтоб не получилось так, что поставив готовность 0, у нас не будет учитываться период в целом
        $this->totaldurationsec += 1 * 60 * 60; // ибо в секундах
        //TODO: вынести в конфиг
        $this->modifieddurationsec += (max(0.2, $dayfreeperiodgroupelement->readinessvalue)) * 60 * 60;
    }

    /**
     * @return float|int
     */
    public function get_modified_duration_in_hours()
    {
        return $this->modifieddurationsec / 60 / 60;
    }

    /**
     * @return float|int
     */
    public function get_total_duration_in_hours()
    {
        return $this->totaldurationsec / 60 / 60;
    }
}