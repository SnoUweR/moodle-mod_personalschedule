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
 * Version information.
 *
 * @package    mod_personalschedule
 * @copyright  2019 onwards Vladislav Kovalev  snouwer@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2019062001; // The current module version.
$plugin->requires  = 2017051500; // This is the release version for Moodle 3.3+
$plugin->component = 'mod_personalschedule'; // Full name of the plugin (used for diagnostics).
$plugin->cron      = 0;
$plugin->maturity  = MATURITY_BETA;