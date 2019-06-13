<?php

namespace mod_personalschedule\items;

class category_object
{
    public $categoryIndex = 0;

    /** @var category_learning_object[] */
    public $lectures = array();
    /** @var category_learning_object[] */
    public $practices = array();
    /** @var category_learning_object[] */
    public $leftLectures = array();
    /** @var category_learning_object[] */
    public $leftPractices = array();

    public $shouldBeIgnored = false;

    public $isPassed = false;

    public $attempts = 0;

    public $modifiedDurationSec = 0;
    public $totalDurationSec = 0;


    /**
     * @param $learningObject category_learning_object
     * @param $isLecture bool
     */
    public function add_learning_object($learningObject, $isLecture)
    {
        if ($isLecture) {
            $this->leftLectures[] = $learningObject;
        } else {
            $this->leftPractices[] = $learningObject;
        }

        $this->totalDurationSec += $learningObject->totalDurationSec;
        $this->modifiedDurationSec += $learningObject->modifiedDurationSec;
    }


    /**
     * @return float|int
     */
    public function get_modified_duration_in_hours()
    {
        return $this->modifiedDurationSec / 60 / 60;
    }

    public function __construct($categoryIndex)
    {
        $this->categoryIndex = $categoryIndex;
    }

}