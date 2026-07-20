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
 * Models student behaviour: Time Spent on Forums
 *
 * Behaviour Pattern Description: Time duration student views forums in the
 * course.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the TimeSpentForums student behaviour pattern.
 */
class TimeSpentForums extends StudentBehaviourPattern
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
      WITH ordered_view_logs AS (
        SELECT
          log.id,
          log.userid,
          log.courseid,
          log.contextinstanceid,
          log.eventname,
          log.component,
          log.action,
          log.timecreated,
          LEAD(log.timecreated) OVER (
            PARTITION BY log.userid, log.courseid
            ORDER BY log.timecreated, log.id
          ) AS next_event_time
        FROM {logstore_standard_log} log
        WHERE log.userid IS NOT NULL
          and log.courseid $courseidssql
          -- Filter by hourly/daily/weekly
          and log.timecreated >= :starttime
      ),
      forum_view_duration AS (
        SELECT
          userid,
          courseid,
          contextinstanceid as coursemoduleid,
          component,
          action,
          LEAST(
            -- Cap estimated sessions at the configured duration
            COALESCE(next_event_time - timecreated, 0), :threshold
          ) AS estimated_seconds_spent
          FROM ordered_view_logs
          WHERE component = 'mod_forum' AND action = 'viewed'
      )
      SELECT
        userid,
        SUM(estimated_seconds_spent) AS total_seconds_spent
      FROM forum_view_duration
      GROUP BY userid
      ORDER BY userid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      'threshold' => $params['sessioncap'],
      'starttime' => $start_time
    ] + $courseidsparams);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];
    $forum_view_time = [];

    foreach ($data as $student_id => $value) {
      $students[] =  intval($student_id);
      $forum_view_time[] = (int)ceil($value->total_seconds_spent * 0.0002777777777778);
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Hours Spent Viewing Forums', $forum_view_time);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Hours Spent Viewing Forums");
    $yaxis->set_min(0);
    $yaxis->set_max(24);
    $yaxis->set_stepsize(1);

    return $chart;
  }
}
