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
 * Models student behaviour: Learning Object Access Frequency
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the LOAccessFrequency student behaviour pattern.
 */
class LOAccessFrequency extends StudentBehaviourPattern
{
  public function query_behaviour_data(array $params)
  {
    global $DB;

    $now = time();

    if (empty($params['courseids'])) {
      return [];
    }

    [$courseidssql, $courseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'courseid'
    );

    $start_time = $this->get_start_time($params, $now);

    $sql = "
      SELECT
        log.userid,
        COUNT(*)
      FROM {logstore_standard_log} log
      WHERE log.eventname IN (
        '\\mod_forum\\event\\course_module_viewed',
        '\\mod_assign\\event\\course_module_viewed',
        '\\mod_resource\\event\\course_module_viewed',
        '\\mod_url\\event\\course_module_viewed',
        '\\mod_page\\event\\course_module_viewed',
        '\\mod_lesson\\event\\course_module_viewed'
      )
        AND log.userid IS NOT null
        AND log.courseid $courseidssql
        -- Filter by hourly/daily/weekly
        AND log.timecreated >= :starttime
      GROUP BY log.userid
      ORDER BY log.userid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      'starttime' => $start_time
    ] + $courseidsparams);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];
    $count = [];

    foreach ($data as $student_id => $value) {
      $students[] = intval($student_id);
      $count[] = intval($value->count);
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Learning Objects Accessed', $count);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Learning Objects Accessed");
    $yaxis->set_min(0);
    $yaxis->set_max(100);
    $yaxis->set_stepsize(10);

    return $chart;
  }
}
