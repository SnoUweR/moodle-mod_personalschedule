<?php

namespace mod_personalschedule\items;

class day_free_period_group_element {
   public $periodIdx = 0;

   /** @var int|float */
   public $waveValue = 0;

   /** @var int|float */
   public $readinessValue = 0;


    /**
     * dayFreePeriodGroupElement constructor.
     * @param $periodIdx int
     * @param $waveValue int|float
     * @param $readinessValue int|float
     */
    public function __construct($periodIdx, $waveValue, $readinessValue)
   {
       $this->periodIdx = $periodIdx;
       $this->waveValue = $waveValue;
       $this->readinessValue = $readinessValue;
   }
}