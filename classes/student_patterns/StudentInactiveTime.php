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
 * Models student behaviour: Student Inactive Time (time since last login)
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
class StudentInactiveTime extends StudentBehaviourPattern
{
  use BarChart;
  use NotRelatedToCourse;

  public function query_behaviour_data(array $params)
  {
    global $DB;

    $end_time = time();

    // TODO: HANDLE THIS PROPERLY FROM TEMPLATE
    $this->time_range = TimeRange::WEEKLY;

    switch ($this->time_range) {
      case TimeRange::HOURLY:
        $start_time = $end_time - HOURSECS;
        break;
      case TimeRange::DAILY:
        $start_time = $end_time - DAYSECS;
        break;
      case TimeRange::WEEKLY:
        $start_time = $end_time - WEEKSECS;
        break;
      default:
        $start_time = 0;
        break;
    }

    $sql = "
    WITH users_in_scope AS (
        SELECT DISTINCT l.userid
        FROM {logstore_standard_log} l
        WHERE l.userid IS NOT NULL
          AND l.userid <> 0
          AND l.eventname IN ('\\core\\event\\user_loggedin')
          AND l.timecreated <= :endtimeusers
    ),
    login_events AS (
        SELECT
            l.id,
            l.userid,
            l.timecreated AS login_time,
            LEAD(l.timecreated) OVER (
                PARTITION BY l.userid
                ORDER BY l.timecreated, l.id
            ) AS next_login_time
        FROM {logstore_standard_log} l
        WHERE l.userid IS NOT NULL
          AND l.userid <> 0
          AND l.eventname IN ('\\core\\event\\user_loggedin')
          AND l.timecreated <= :endtimelogins
    ),
    session_bounds AS (
        SELECT
            le.userid,
            le.login_time,
            le.next_login_time,
            (
                SELECT MIN(lo.timecreated)
                FROM {logstore_standard_log} lo
                WHERE lo.userid = le.userid
                  AND lo.eventname IN ('\\core\\event\\user_loggedout')
                  AND lo.timecreated > le.login_time
                  AND (
                      le.next_login_time IS NULL
                      OR lo.timecreated < le.next_login_time
                  )
            ) AS explicit_logout_time,
            (
                SELECT MAX(a.timecreated)
                FROM {logstore_standard_log} a
                WHERE a.userid = le.userid
                  AND a.timecreated >= le.login_time
                  AND a.timecreated <= :endtimeactivity
                  AND (
                      le.next_login_time IS NULL
                      OR a.timecreated < le.next_login_time
                  )
            ) AS last_activity_time
        FROM login_events le
    ),
    estimated_sessions AS (
        SELECT
            userid,
            login_time,
            LEAST(
                COALESCE(explicit_logout_time, :endtimenologout),
                COALESCE(last_activity_time + :idlethreshold1, login_time + :idlethreshold2),
                COALESCE(next_login_time, :endtimenonextlogin),
                :endtimesessioncap
            ) AS estimated_logout_time
        FROM session_bounds
    ),
    clipped_logged_in_sessions AS (
        SELECT
            userid,
            GREATEST(login_time, :starttimeclipstart) AS interval_start,
            LEAST(estimated_logout_time, :endtimeclipend) AS interval_end
        FROM estimated_sessions
        WHERE login_time < :endtimesessionfilter 
          AND estimated_logout_time > :starttimesessionfilter
    ),
    logged_in_totals AS (
        SELECT
            userid,
            SUM(
                CASE
                    WHEN interval_end > interval_start
                    THEN interval_end - interval_start
                    ELSE 0
                END
            ) AS estimated_logged_in_seconds
        FROM clipped_logged_in_sessions
        GROUP BY userid
        HAVING SUM(
          CASE
              WHEN interval_end > interval_start
              THEN interval_end - interval_start
              ELSE 0
          END
        ) > 0
    )
    SELECT
        u.userid,
        COALESCE(li.estimated_logged_in_seconds, 0) AS estimated_logged_in_seconds,
        :rangeseconds - COALESCE(li.estimated_logged_in_seconds, 0) AS estimated_logged_out_seconds
    FROM users_in_scope u
    LEFT JOIN logged_in_totals li
      ON li.userid = u.userid
    ORDER BY u.userid ASC
  ";

    $range = $end_time - $start_time;

    $records = $DB->get_records_sql($sql, [
      'starttimeclipstart' => $start_time,
      'starttimesessionfilter' => $start_time,
      'starttimefinal' => $start_time,

      'endtimeusers' => $end_time,
      'endtimelogins' => $end_time,
      'endtimeactivity' => $end_time,
      'endtimenologout' => $end_time,
      'endtimenonextlogin' => $end_time,
      'endtimesessioncap' => $end_time,
      'endtimeclipend' => $end_time,
      'endtimesessionfilter' => $end_time,
      'rangeseconds' => $range,

      'idlethreshold1' => 1800,
      'idlethreshold2' => 1800,
    ]);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];
    $inactive_time = [];

    foreach ($data as $student_id => $value) {
      $students[] =  intval($student_id);
      $inactive_time[] = (int)ceil($value->estimated_logged_out_seconds * 0.0002777777777778);
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Hours Inactive', $inactive_time);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Hours Inactive");
    $yaxis->set_stepsize(10);

    return $chart;
  }
}
