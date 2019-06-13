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
 * @package   mod_personalschedule
 * @copyright 2019 onwards Vladislav Kovalev  snouwer@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_personalschedule_mod_form extends moodleform_mod {

    /** @var cm_info[] */
    private $cachedCourseModules = null;

    function definition() {
        global $CFG, $COURSE;

        $mform =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        $this->standard_intro_elements(get_string('customintro', 'personalschedule'));

        $mform->addElement('header', 'connection_elements',
            get_string('mod_form_header_connection_elements', 'personalschedule'));


        if ($this->cachedCourseModules == null) {
            $this->cachedCourseModules = personalschedule_get_course_activities($COURSE);
        }

        foreach ($this->cachedCourseModules as $courseModule) {
            $icon = html_writer::empty_tag('img', array('src' => $courseModule->get_icon_url()));
            $moduleNameString = sprintf("<a href=\"$CFG->wwwroot/mod/%s/view.php?id=%s\">%s %s (%s)</a>",
                $courseModule->modname,  $courseModule->id, $icon, $courseModule->name, $courseModule->modfullname);
            $mform->addElement('static', 'description', "", $moduleNameString);

            // Course's activity properties.

            // Duration.
            $durationElementName =
                mod_personalschedule_config::cmPropKeyDuration.mod_personalschedule_config::separatorHiddenInput.$courseModule->id;
            $mform->addElement('duration', $durationElementName,
                get_string('mod_form_header_connection_elements_duration', 'personalschedule'));
            $mform->addHelpButton($durationElementName,
                'mod_form_header_connection_elements_category', 'personalschedule');

            // Category.
            $categoryElementName =
                mod_personalschedule_config::cmPropKeyCategory.mod_personalschedule_config::separatorHiddenInput.$courseModule->id;
            $mform->addElement('text', $categoryElementName,
                get_string('mod_form_header_connection_elements_category', 'personalschedule'));
            $mform->addHelpButton($categoryElementName,
                'mod_form_header_connection_elements_category', 'personalschedule');
            $mform->setType($categoryElementName, PARAM_INT);

            // Weight.
            $weightElementName =
                mod_personalschedule_config::cmPropKeyWeight.mod_personalschedule_config::separatorHiddenInput.$courseModule->id;
            $mform->addElement('text', $weightElementName,
                get_string('mod_form_header_connection_elements_weight', 'personalschedule'));
            $mform->addHelpButton($weightElementName,
                'mod_form_header_connection_elements_weight', 'personalschedule');
            $mform->setType($weightElementName, PARAM_INT);

            // "Should ignored" flag.
            $isIgnoredElementName =
                mod_personalschedule_config::cmPropKeyIsIgnored.mod_personalschedule_config::separatorHiddenInput.$courseModule->id;
            $mform->addElement('advcheckbox', $isIgnoredElementName,
                get_string('mod_form_header_connection_elements_is_ignored', 'personalschedule'),
                '', array('group' => 1), array(0, 1));
            $mform->addHelpButton($isIgnoredElementName,
                'mod_form_header_connection_elements_is_ignored', 'personalschedule');


            // Validation settings.
            $mform->addRule($durationElementName, null, 'required', null, 'client');
            $mform->addRule($categoryElementName, null, 'required', null, 'client');
            $mform->addRule($weightElementName, null, 'required', null, 'client');
            $mform->addRule($isIgnoredElementName, null, 'required', null, 'client');

            $mform->addElement('html', "<hr>");
        }

        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_coursemodule_elements();

//-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$toform) {
        global $COURSE;
        if ($this->cachedCourseModules == null) {
            $this->cachedCourseModules = personalschedule_get_course_activities($COURSE);
        }

        foreach ($this->cachedCourseModules as $courseModule)
        {
            $toform[
                mod_personalschedule_config::cmPropKeyCategory.mod_personalschedule_config::separatorHiddenInput.$courseModule->id] =
                $courseModule->section;
            $toform[
                mod_personalschedule_config::cmPropKeyWeight.mod_personalschedule_config::separatorHiddenInput.$courseModule->id] =
                1;
            $toform[
                mod_personalschedule_config::cmPropKeyDuration.mod_personalschedule_config::separatorHiddenInput.$courseModule->id] =
                60*60; // 1 hour.
            $toform[
                mod_personalschedule_config::cmPropKeyIsIgnored.mod_personalschedule_config::separatorHiddenInput.$courseModule->id] =
                0;
        }


        $results = personalschedule_get_course_modules_props($toform["id"]);
        foreach ($results as $courseModuleProps)
        {
            $toform[mod_personalschedule_config::cmPropKeyCategory.mod_personalschedule_config::separatorHiddenInput.
            $courseModuleProps->cm] =
                $courseModuleProps->category;
            $toform[mod_personalschedule_config::cmPropKeyWeight.mod_personalschedule_config::separatorHiddenInput.
            $courseModuleProps->cm] =
                $courseModuleProps->weight;
            $toform[mod_personalschedule_config::cmPropKeyDuration.mod_personalschedule_config::separatorHiddenInput.
            $courseModuleProps->cm] =
                $courseModuleProps->duration;
            $toform[mod_personalschedule_config::cmPropKeyIsIgnored.mod_personalschedule_config::separatorHiddenInput.
            $courseModuleProps->cm] =
                $courseModuleProps->is_ignored;
        }
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            // Turn off completion settings if the checkboxes aren't ticked.
            $autocompletion = !empty($data->completion) &&
                $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (!$autocompletion || empty($data->completionsubmit)) {
                $data->completionsubmit = 0;
            }
        }
    }

    /**
     * Add completion rules to form.
     * @return array
     * @throws coding_exception
     */
    public function add_completion_rules() {
        $mform =& $this->_form;
        $mform->addElement('checkbox', 'completionsubmit', '',
            get_string('completionsubmit', 'personalschedule'));
        // Enable this completion rule by default.
        $mform->setDefault('completionsubmit', 1);
        return array('completionsubmit');
    }

    /**
     * Enable completion rules.
     * @param stdClass $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }
}

