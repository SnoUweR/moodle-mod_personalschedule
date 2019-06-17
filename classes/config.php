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

    const statussleep = -1;
    const statusbusy = 0;
    const statusfree = 1;

    const prefixhiddeninput = 'pers';
    const separatorhiddeninput = ';';
    const keyprefixreadiness = '-2';

    const cmpropkeyduration = 'cm_duration';
    const cmpropkeycategory = 'cm_category';
    const cmpropkeyweight = 'cm_weight';
    const cmpropkeyisignored = 'cm_is_ignored';

    const dayindexmin = 1;
    const dayindexmax = 7;

    const agemin = 5;
    const agemax = 105;

    const periodindexmin = 0;
    const periodindexmax = 23;

    const ignoredmodnames = array('personalschedule', 'forum', 'label', 'workshop', 'chat');

    const personalschedulemodname = "personalschedule";

    const lecturesmodnames = array(
        'resource', 'data', 'wiki', 'lti', 'glossary', 'lesson', 'scorm', 'url', 'book', 'folder', 'page', 'resource');

    const practicemodnames = array(
        'quiz', 'survey', 'assign', 'feedback', 'choice');

    const maxattemptstoignorecategory = 5;

    const minimumrelaxtimeinminutes = 10;

    const minpercenttopass = 25;

    const skipcategorieswithoutpractice = false;
    const skipcompletedcategories = true;

    const daystosendschedulenotify = 2;
}
