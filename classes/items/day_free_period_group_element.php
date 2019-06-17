<?php

namespace mod_personalschedule\items;

class day_free_period_group_element {
   public $periodidx = 0;

   /** @var int|float */
   public $wavevalue = 0;

   /** @var int|float */
   public $readinessvalue = 0;


    /**
     * dayfreeperiodgroupelement constructor.
     * @param $periodidx int
     * @param $wavevalue int|float
     * @param $readinessvalue int|float
     */
    public function __construct($periodidx, $wavevalue, $readinessvalue)
   {
       $this->periodidx = $periodidx;
       $this->wavevalue = $wavevalue;
       $this->readinessvalue = $readinessvalue;
   }
}