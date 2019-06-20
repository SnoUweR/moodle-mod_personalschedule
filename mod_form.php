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

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_personalschedule_mod_form extends moodleform_mod {

    /** @var cm_info[] $cachedcoursemodules */
    private $cachedcoursemodules = null;

    public function definition() {
        global $CFG, $COURSE;

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        $this->standard_intro_elements(get_string('customintro', 'personalschedule'));

        $mform->addElement('header', 'connection_elements',
            get_string('mod_form_header_connection_elements', 'personalschedule'));

        if ($this->cachedcoursemodules == null) {
            $this->cachedcoursemodules = personalschedule_get_course_activities($COURSE);
        }

        foreach ($this->cachedcoursemodules as $coursemodule) {
            $icon = html_writer::empty_tag('img', array('src' => $coursemodule->get_icon_url()));
            $modulenamestring = sprintf("<a href=\"$CFG->wwwroot/mod/%s/view.php?id=%s\">%s %s (%s)</a>",
                $coursemodule->modname, $coursemodule->id, $icon, $coursemodule->name, $coursemodule->modfullname);
            $mform->addelement('static', 'description', "", $modulenamestring);

            // Course's activity properties.

            // Duration.
            $durationelementname =
                mod_personalschedule_config::CMPROPKEYDURATION . mod_personalschedule_config::SEPARATORHIDDENINPUT .
                $coursemodule->id;
            $mform->addElement('duration', $durationelementname,
                get_string('mod_form_header_connection_elements_duration', 'personalschedule'));
            $mform->addHelpButton($durationelementname,
                'mod_form_header_connection_elements_category', 'personalschedule');

            // Category.
            $categoryelementname =
                mod_personalschedule_config::CMPROPKEYCATEGORY . mod_personalschedule_config::SEPARATORHIDDENINPUT .
                $coursemodule->id;
            $mform->addElement('text', $categoryelementname,
                get_string('mod_form_header_connection_elements_category', 'personalschedule'));
            $mform->addHelpButton($categoryelementname,
                'mod_form_header_connection_elements_category', 'personalschedule');
            $mform->setType($categoryelementname, PARAM_INT);

            // Weight.
            $weightelementname =
                mod_personalschedule_config::CMPROPKEYWEIGHT . mod_personalschedule_config::SEPARATORHIDDENINPUT .
                $coursemodule->id;
            $mform->addElement('text', $weightelementname,
                get_string('mod_form_header_connection_elements_weight', 'personalschedule'));
            $mform->addHelpButton($weightelementname,
                'mod_form_header_connection_elements_weight', 'personalschedule');
            $mform->setType($weightelementname, PARAM_INT);

            // Should ignored flag.
            $isignoredelementname =
                mod_personalschedule_config::CMPROPKEYISIGNORED . mod_personalschedule_config::SEPARATORHIDDENINPUT .
                $coursemodule->id;
            $mform->addElement('advcheckbox', $isignoredelementname,
                get_string('mod_form_header_connection_elements_is_ignored', 'personalschedule'),
                '', array('group' => 1), array(0, 1));
            $mform->addHelpButton($isignoredelementname,
                'mod_form_header_connection_elements_is_ignored', 'personalschedule');

            // Validation settings.
            $mform->addRule($durationelementname, null, 'required', null, 'client');
            $mform->addRule($categoryelementname, null, 'required', null, 'client');
            $mform->addRule($weightelementname, null, 'required', null, 'client');

            $mform->addElement('html', "<hr>");
        }

        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    public function data_preprocessing(&$toform) {
        global $COURSE;
        if ($this->cachedcoursemodules == null) {
            $this->cachedcoursemodules = personalschedule_get_course_activities($COURSE);
        }

        /** @var int[] $sectiontocategory */
        $sectiontocategory = array();

        $totalcategories = 1;

        foreach ($this->cachedcoursemodules as $coursemodule) {

            if (!key_exists($coursemodule->section, $sectiontocategory)) {
                $sectiontocategory[$coursemodule->section] = $totalcategories++;
            }

            $category = $sectiontocategory[$coursemodule->section];

            $toform[mod_personalschedule_config::CMPROPKEYCATEGORY . mod_personalschedule_config::SEPARATORHIDDENINPUT .
            $coursemodule->id] = $category;
            $toform[mod_personalschedule_config::CMPROPKEYWEIGHT . mod_personalschedule_config::SEPARATORHIDDENINPUT .
            $coursemodule->id] = 1;
            $toform[mod_personalschedule_config::CMPROPKEYDURATION . mod_personalschedule_config::SEPARATORHIDDENINPUT .
            $coursemodule->id] = 60 * 60; // 1 hour.
            $toform[mod_personalschedule_config::CMPROPKEYISIGNORED . mod_personalschedule_config::SEPARATORHIDDENINPUT .
            $coursemodule->id] = 0;
        }

        $results = personalschedule_get_course_modules_props($toform["id"]);
        foreach ($results as $coursemoduleprops) {
            $toform[mod_personalschedule_config::CMPROPKEYCATEGORY . mod_personalschedule_config::SEPARATORHIDDENINPUT .
            $coursemoduleprops->cm] =
                $coursemoduleprops->category;
            $toform[mod_personalschedule_config::CMPROPKEYWEIGHT . mod_personalschedule_config::SEPARATORHIDDENINPUT .
            $coursemoduleprops->cm] =
                $coursemoduleprops->weight;
            $toform[mod_personalschedule_config::CMPROPKEYDURATION . mod_personalschedule_config::SEPARATORHIDDENINPUT .
            $coursemoduleprops->cm] =
                $coursemoduleprops->duration;
            $toform[mod_personalschedule_config::CMPROPKEYISIGNORED . mod_personalschedule_config::SEPARATORHIDDENINPUT .
            $coursemoduleprops->cm] =
                $coursemoduleprops->is_ignored;
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

