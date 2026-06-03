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
 * Models student behaviour: Time Spent Accessing Learning Objects
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates a renderer for the block_delta_visualizations
 *
 */
class TimeSpentLO extends StudentBehaviourPattern
{
  use BarChart;

  public function query_behaviour_data()
  {
    global $DB;

    $now = time();

    // TODO: Grab actual courseid from template
    $courseid = 3;

    switch ($this->time_range) {
      case TimeRange::HOURLY:
        $start_time = $now - HOURSECS;
        break;
      case TimeRange::DAILY:
        $start_time = $now - DAYSECS;
        break;
      case TimeRange::WEEKLY:
        $start_time = $now - WEEKSECS;
        break;
      default:
        $start_time = 0;
        break;
    }

    $sql = "
      WITH ordered_view_logs AS (
        SELECT
          log.id,
          log.userid,
          log.courseid,
          log.contextinstanceid,
          log.eventname,
          log.component,
          log.timecreated,
          LEAD(log.timecreated) OVER (
            PARTITION BY log.userid, log.courseid
            ORDER BY log.timecreated, log.id
          ) AS next_event_time
        FROM {logstore_standard_log} log
        WHERE log.userid IS NOT NULL
          and log.courseid = :courseid
          -- Filter by hourly/daily/weekly
          and log.timecreated >= :starttime
          -- Filter both module-level actions (course modules) and core course view events
      ),
      module_view_duration AS (
        SELECT
          userid,
          courseid,
          contextinstanceid as coursemoduleid,
          component,
          LEAST(
            -- THRESHOLD: 30 mins (1800 seconds)
            COALESCE(next_event_time - timecreated, 0), :threshold
          ) AS estimated_seconds_spent
          FROM ordered_view_logs
          WHERE eventname IN (
            '\\mod_forum\\event\\course_module_viewed',
            '\\mod_assign\\event\\course_module_viewed',
            '\\mod_resource\\event\\course_module_viewed',
            '\\mod_url\\event\\course_module_viewed',
            '\\mod_page\\event\\course_module_viewed',
            '\\mod_lesson\\event\\course_module_viewed'
          )
      )
      SELECT
        userid,
        SUM(estimated_seconds_spent) AS total_seconds_spent
      FROM module_view_duration
      GROUP BY userid
      ORDER BY userid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      // TODO: Grab actual courseid from template
      'courseid' => $courseid,
      // 30 minutes
      'threshold' => 1800,
      'starttime' => $start_time
    ]);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];
    $lo_access_time = [];

    foreach ($data as $student_id => $value) {
      $students[] =  intval($student_id);
      $lo_access_time[] = (int)ceil($value->total_seconds_spent * 0.0002777777777778);
    }

    // TODO: REMOVE LATER; TEMP TO SHOW VALUE CONTRAST ON CHART
    $students[] = intval(3);
    $lo_access_time[] = intval(24);
    // END TODO

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Hours Spent Accessing Learning Objects', $lo_access_time);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Hours Spent Accessing Learning Objects");
    $yaxis->set_min(0);
    $yaxis->set_max(24);
    $yaxis->set_stepsize(1);

    return $chart;
  }
}
