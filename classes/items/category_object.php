<?php

namespace mod_personalschedule\items;

class category_object
{
    public $categoryindex = 0;

    /** @var category_learning_object[] */
    public $lectures = array();
    /** @var category_learning_object[] */
    public $practices = array();
    /** @var category_learning_object[] */
    public $leftlectures = array();
    /** @var category_learning_object[] */
    public $leftpractices = array();

    public $shouldbeignored = false;

    public $ispassed = false;

    public $attempts = 0;

    public $modifieddurationsec = 0;
    public $totaldurationsec = 0;


    /**
     * @param $learningobject category_learning_object
     * @param $islecture bool
     */
    public function add_learning_object($learningobject, $islecture)
    {
        if ($islecture) {
            $this->leftlectures[] = $learningobject;
        } else {
            $this->leftpractices[] = $learningobject;
        }

        $this->totaldurationsec += $learningobject->totaldurationsec;
        $this->modifieddurationsec += $learningobject->modifieddurationsec;
    }


    /**
     * @return float|int
     */
    public function get_modified_duration_in_hours()
    {
        return $this->modifieddurationsec / 60 / 60;
    }

    public function __construct($categoryindex)
    {
        $this->categoryindex = $categoryindex;
    }

}