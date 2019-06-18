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

    const STATUSSLEEP = -1;
    const STATUSBUSY = 0;
    const STATUSFREE = 1;

    const PREFIXHIDDENINPUT = 'pers';
    const SEPARATORHIDDENINPUT = ';';
    const KEYPREFIXREADINESS = '-2';

    const CMPROPKEYDURATION = 'cm_duration';
    const CMPROPKEYCATEGORY = 'cm_category';
    const CMPROPKEYWEIGHT = 'cm_weight';
    const CMPROPKEYISIGNORED = 'cm_is_ignored';

    const DAYINDEXMIN = 1;
    const DAYINDEXMAX = 7;

    const AGEMIN = 5;
    const AGEMAX = 105;

    const PERIODINDEXMIN = 0;
    const PERIODINDEXMAX = 23;

    const IGNOREDMODNAMES = array('personalschedule', 'forum', 'label', 'workshop', 'chat');

    const PERSONALSCHEDULEMODNAME = "personalschedule";

    const LECTURESMODNAMES = array(
        'resource', 'data', 'wiki', 'lti', 'glossary', 'lesson', 'scorm', 'url', 'book', 'folder', 'page', 'resource');

    const PRACTICEMODNAMES = array(
        'quiz', 'survey', 'assign', 'feedback', 'choice');

    const MAXATTEMPTSTOIGNORECATEGORY = 5;

    const MINIMUMRELAXTIMEINMINUTES = 10;

    const MINPERCENTSTOPASS = 25;

    const SKIPCATEGORIESWITHOUTPRACTICE = false;
    const SKIPCOMPLETEDCATEGORIES = true;

    const DAYSTOSENDSCHEDULENOTIFY = 2;
}
