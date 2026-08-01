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
 * Admin settings page. These are institution-wide settings that apply to all
 * teachers and courses in a given Moodle installation.
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
    \block_delta_visualizations\local\BehaviourConfig::default('gradethreshold'),
    PARAM_FLOAT
  ));

  // sets an institution-wide feedback uniqueness goal.
  $settings->add(new admin_setting_configtext(
    'block_delta_visualizations/feedbackgoal',
    get_string('settingsfeedbackgoal', 'block_delta_visualizations'),
    get_string('settingsfeedbackgoal_desc', 'block_delta_visualizations'),
    \block_delta_visualizations\local\BehaviourConfig::default('feedbackgoal'),
    PARAM_FLOAT
  ));

  // sets a minimum number of times teacher should interact with their courses each week.
  $settings->add(new admin_setting_configtext(
    'block_delta_visualizations/interactionthreshold',
    get_string('settingsinteractionthreshold', 'block_delta_visualizations'),
    get_string('settingsinteractionthreshold_desc', 'block_delta_visualizations'),
    \block_delta_visualizations\local\BehaviourConfig::default('interactionthreshold'),
    PARAM_FLOAT
  ));

  // sets the insitution's feedback policy in days.
  $settings->add(new admin_setting_configtext(
    'block_delta_visualizations/timelyfeedbackdays',
    get_string('settingstimelyfeedbackdays', 'block_delta_visualizations'),
    get_string('settingstimelyfeedbackdays_desc', 'block_delta_visualizations'),
    \block_delta_visualizations\local\BehaviourConfig::default('timelyfeedbackdays'),
    PARAM_INT
  ));

  // sets a limit on the amount of time (in seconds) between consecutive events.
  $sessioncapsetting = new admin_setting_configduration(
    'block_delta_visualizations/sessioncap',
    get_string('settingssessioncap', 'block_delta_visualizations'),
    get_string('settingssessioncap_desc', 'block_delta_visualizations'),
    \block_delta_visualizations\local\BehaviourConfig::default('sessioncap'),
    MINSECS
  );
  // the minimum cap on duration between two events is 1 minute
  $sessioncapsetting->set_min_duration(MINSECS);

  // the maximum cap on duration between two events is 1 day
  $sessioncapsetting->set_max_duration(DAYSECS);
  $settings->add($sessioncapsetting);
}
