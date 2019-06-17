<?php

namespace mod_personalschedule\items;

class day_info
{
    /** @var day_free_period_group[] */
    public $freeperiodgroups = array();

    public $daybeginperiodidx = 0;
    public $daybegindayidx = 0;
    public $dayendperiodidx = 0;
    public $dayenddayidx = 0;
    public $weekidx = 0;

    public function __construct($daybegindayidx, $daybeginperiodidx, $dayenddayidx, $dayendperiodidx, $weekidx)
    {
        $this->daybegindayidx = $daybegindayidx;
        $this->daybeginperiodidx = $daybeginperiodidx;

        $this->dayenddayidx = $dayenddayidx;
        $this->dayendperiodidx = $dayendperiodidx;

        $this->weekidx = $weekidx;
    }

    /**
     * @param $groupidx int
     * @return bool
     */
    public function is_group_exists($groupidx)
    {
        return array_key_exists($groupidx, $this->freeperiodgroups);
    }

    /**
     * @param $groupidx int
     * @return bool|day_free_period_group
     */
    public function get_group($groupidx)
    {
        if (!$this->is_group_exists($groupidx)) return false;

        return $this->freeperiodgroups[$groupidx];
    }


    /**
     * @param $groupidx int
     * @param $freeperiodgroup day_free_period_group
     */
    public function add_free_period_group($groupidx, $freeperiodgroup)
    {
        $this->freeperiodgroups[$groupidx] = $freeperiodgroup;
    }
}