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
 * Provides the plugin strings.
 *
 * @package     block_delta_visualizations
 * @category    string
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Delta Visualizations';
$string['delta_visualizations:myaddinstance'] = 'Adds a Delta Visualizations block to dashboard';
$string['delta_visualizations:viewteacher'] = 'View teacher visualizations dashboard';
$string['courseselecttitle'] = 'Select courses:';
$string['nodata'] = 'No data found.';
$string['filterresponsetime'] = 'Response time:';
$string['filterdaysoption'] = '{$a} days';
$string['filterstartdate'] = 'Start:';
$string['filterenddate'] = 'End:';
$string['filtertimerange'] = 'Reporting period:';
$string['filterrangehourly'] = 'Last hour';
$string['filterrangedaily'] = 'Last 24 hours';
$string['filterrangeweekly'] = 'Last 7 days';
$string['filtercourseendwindow'] = 'Course-end window:';
$string['filterfinalweek'] = 'Final week';
$string['filterfinaltwoweeks'] = 'Final 2 weeks';
$string['filterfinalfourweeks'] = 'Final 4 weeks';
$string['settingsbehaviourheading'] = 'Behaviour definitions';
$string['settingsbehaviourheading_desc'] = 'These settings define how behaviours are classified. They are intentionally not chart filters.';
$string['settingsgradethreshold'] = 'Struggling-student grade cutoff';
$string['settingsgradethreshold_desc'] = 'Grades below this value identify students who may require targeted feedback. Valid values are from 0 to 100.';
$string['settingsfeedbackgoal'] = 'Personalized-feedback similarity limit';
$string['settingsfeedbackgoal_desc'] = 'The maximum acceptable similarity percentage between feedback messages. Valid values are from 0 to 100.';
$string['settingsengagementthreshold'] = 'Weekly engagement threshold';
$string['settingsengagementthreshold_desc'] = 'The minimum average number of teacher interactions per week used by Consistent Use of LMS.';
$string['settingssessioncap'] = 'Estimated session cap';
$string['settingssessioncap_desc'] = 'The maximum duration attributed to an activity when an explicit end or logout event is unavailable.';
