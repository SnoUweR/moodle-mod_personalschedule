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

/**
 * Personalization config.
 *
 * @package    mod_personalschedule
 * @copyright  2019 onwards Vladislav Kovalev snouwer@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

abstract class mod_personalschedule_config {

    const statusSleep = -1;
    const statusBusy = 0;
    const statusFree = 1;

    const prefixHiddenInput = 'pers';
    const separatorHiddenInput = ';';
    const keyPrefixReadiness = '-2';

    const cmPropKeyDuration = 'cm_duration';
    const cmPropKeyCategory = 'cm_category';
    const cmPropKeyWeight = 'cm_weight';
    const cmPropKeyIsIgnored = 'cm_is_ignored';

    const dayIndexMin = 1;
    const dayIndexMax = 7;

    const ageMin = 5;
    const ageMax = 105;

    const periodIndexMin = 0;
    const periodIndexMax = 23;

    const ignoredModnames = array('personalschedule', 'forum', 'label', 'workshop', 'chat');

    const personalscheduleModname = "personalschedule";

    const lecturesModNames = array(
        'resource', 'data', 'wiki', 'lti', 'glossary', 'lesson', 'scorm', 'url', 'book', 'folder', 'page', 'resource');

    const practiceModNames = array(
        'quiz', 'survey', 'assign', 'feedback', 'choice');

    const maxAttemptsToIgnoreCategory = 5;

    const minimumRelaxTimeInMinutes = 10;

    const minPercentToPass = 25;

    const skipCategoriesWithoutPractice = false;
    const skipCompletedCategories = true;
}
