<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Admin settings page for DELTA visualizations plugin. These are general,
 * institution-wide settings.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
  $settings->add(new admin_setting_heading(
    'block_delta_visualizations/behavioursettings',
    get_string('settingsbehaviourheading', 'block_delta_visualizations'),
    get_string('settingsbehaviourheading_desc', 'block_delta_visualizations')
  ));

  // sets an institution-wide grade threshold value.
  $settings->add(new admin_setting_configtext(
    'block_delta_visualizations/gradethreshold',
    get_string('settingsgradethreshold', 'block_delta_visualizations'),
    get_string('settingsgradethreshold_desc', 'block_delta_visualizations'),
    70,
    PARAM_FLOAT
  ));

  // sets an institution-wide feedback personalization goal.
  $settings->add(new admin_setting_configtext(
    'block_delta_visualizations/feedbackgoal',
    get_string('settingsfeedbackgoal', 'block_delta_visualizations'),
    get_string('settingsfeedbackgoal_desc', 'block_delta_visualizations'),
    60,
    PARAM_FLOAT
  ));

  // sets a minimum amount of engagement teachers should have in their courses each week.
  $settings->add(new admin_setting_configtext(
    'block_delta_visualizations/engagementthreshold',
    get_string('settingsengagementthreshold', 'block_delta_visualizations'),
    get_string('settingsengagementthreshold_desc', 'block_delta_visualizations'),
    5,
    PARAM_FLOAT
  ));

  // sets a limit on the amount of time (in seconds) between consecutive events. This is needed for proper event duration tracking as many behaviours in Moodle do not have an explicit end event to record in the database (e.g., exiting the Moodle site without logging out will cause issus with proper duration tracking)
  $sessioncapsetting = new admin_setting_configduration(
    'block_delta_visualizations/sessioncap',
    get_string('settingssessioncap', 'block_delta_visualizations'),
    get_string('settingssessioncap_desc', 'block_delta_visualizations'),
    30 * MINSECS,
    MINSECS
  );
  // the minimum cap on duration between two events is 1 minute
  $sessioncapsetting->set_min_duration(MINSECS);

  // the maximum cap on duration between two events is 1 day
  $sessioncapsetting->set_max_duration(DAYSECS);

  // add to settings page after setting min/max values
  $settings->add($sessioncapsetting);
}
