<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
namespace mod_personalschedule\items;

defined('MOODLE_INTERNAL') || die;

abstract class proposed_object {
    /** @var int */
    public $modifieddurationsec = 0;

    /** @var day_period_info Описывает пользовательские сутки (не обычные), к которым принадлежит
     * данный предложенный учебный элемент */
    public $dayperiodinfo;

    /** @var float|int */
    public $periodidxbegin = 0;
    /** @var float|int */
    public $periodidxend = 0;

    /**
     * proposed_object constructor.
     * @param $modifieddurationsec int
     * @param $periodidxbegin float|int
     * @param $daybegindayidx int
     * @param $daybeginperiodidx int
     * @param $weekidx int
     */
    public function __construct(
        $modifieddurationsec,
        $periodidxbegin,
        $daybegindayidx,
        $daybeginperiodidx,
        $weekidx
    ) {
        $this->modifieddurationsec = (int)ceil($modifieddurationsec);
        $this->periodidxbegin = $periodidxbegin;
        $this->periodidxend = $periodidxbegin + ($modifieddurationsec / 60 / 60);

        $this->dayperiodinfo = new day_period_info($weekidx, $daybegindayidx, $daybeginperiodidx);
    }
}

