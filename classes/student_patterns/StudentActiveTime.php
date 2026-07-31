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
 * Models student behaviour: Student Active Time
 *
 * Behaviour Pattern Description: Time duration student accesses a course.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

use block_delta_visualizations\local\BehaviourConfig;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the StudentActiveTime student behaviour pattern.
 */
class StudentActiveTime extends StudentBehaviourPattern
{
  public function query_behaviour_data(array $params)
  {
    global $DB;

    if (empty($params['courseids'])) {
      return [];
    }

    [$courseidssql, $courseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'courseid'
    );

    // used for client-side filtering
    $reporting_end = time();
    $reporting_start = $this->get_start_time($params, $reporting_end);

    // grab site-defined session cap
    $session_cap = BehaviourConfig::get('sessioncap');

    $sql = "
      -- return records of students in selected courses
      WITH course_students AS (
        SELECT DISTINCT
          ra.userid,
          c.id AS courseid,
          c.startdate AS course_start,
          c.enddate AS course_end
        FROM {course} c
        JOIN {context} ctx
          ON ctx.contextlevel = :coursecontextlevel
          AND ctx.instanceid = c.id
        JOIN {role_assignments} ra
          ON ra.contextid = ctx.id
        JOIN {role} r
          ON r.id = ra.roleid
        WHERE r.shortname = 'student'
          AND c.id $courseidssql
      ),
      -- return all student events from moodle logs
      ordered_course_events AS (
        SELECT
          log.id AS eventid,
          log.userid,
          log.courseid,
          log.eventname,
          log.component,
          log.action,
          log.timecreated,
          -- grab subsequent logs to estimate duration
          LEAD(log.timecreated) OVER (
            PARTITION BY log.userid, log.courseid
            ORDER BY log.timecreated, log.id
          ) AS next_event_time
        FROM {logstore_standard_log} log
        JOIN course_students students
          ON students.userid = log.userid
          AND students.courseid = log.courseid
        -- handle client-side filtering of reporting periods
        WHERE log.timecreated >= students.course_start
          AND log.timecreated >= :reportstart
          AND log.timecreated <= students.course_end
          AND log.timecreated <= :reportend
      ),
      -- returns duraton of student events
      active_event_durations AS (
        SELECT
        userid,
        courseid,
        CASE
          WHEN (
            component LIKE 'mod_%'
            OR eventname = '\\core\\event\\course_viewed'
          ) THEN
            CASE
              -- cap long duration gaps where the student likely stopped using moodle
              WHEN next_event_time - timecreated > :sessioncaplimit
                THEN :sessioncapvalue
              ELSE next_event_time - timecreated
            END
          -- catch--all for other events, these contribute no time to overall duration
          ELSE 0
        END AS active_seconds
        FROM ordered_course_events
      ),
      -- sum the active time durations
      active_time_totals AS (
        SELECT
          userid,
          courseid,
          SUM(active_seconds) AS active_time_seconds
        FROM active_event_durations
        GROUP BY userid, courseid
      )
      -- returns total duration student is active in course
      SELECT
        -- generate unique column id (required for Moodle sql)
        ROW_NUMBER() OVER (ORDER BY students.courseid, students.userid) AS recordid,
        students.userid,
        students.courseid,
        COALESCE(totals.active_time_seconds, 0) AS active_time_seconds
      FROM course_students students
      -- append students without logins to query results
      LEFT JOIN active_time_totals totals
        ON totals.userid = students.userid
        AND totals.courseid = students.courseid
      ORDER BY students.courseid, students.userid
    ";

    $records = $DB->get_records_sql($sql, [
      'coursecontextlevel' => CONTEXT_COURSE,
      'reportstart' => $reporting_start,
      'reportend' => $reporting_end,
      'sessioncaplimit' => $session_cap,
      'sessioncapvalue' => $session_cap,
    ] + $courseidsparams);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];
    $active_time = [];

    foreach ($data as $student_id => $value) {
      $students[] =  intval($student_id);
      // convert active time in seconds to hours, rounded up
      $active_time[] = (int)ceil($value->active_time_seconds / HOURSECS);
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Hours Active', $active_time);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Hours Active");
    $yaxis->set_min(0);
    $yaxis->set_max(24);
    $yaxis->set_stepsize(1);

    return $chart;
  }
}
