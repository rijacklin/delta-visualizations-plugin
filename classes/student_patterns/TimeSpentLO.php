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
 * Behaviour Pattern Description: Time duration student is viewing learning
 * objects in the course.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

use block_delta_visualizations\local\BehaviourConfig;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the TimeSpentLO student behaviour pattern.
 */
class TimeSpentLO extends StudentBehaviourPattern
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

    $session_cap = BehaviourConfig::get('sessioncap');

    // used for client-side filtering
    $reporting_end = time();
    $reporting_start = $this->get_start_time($params, $reporting_end);

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
      ordered_view_logs AS (
        SELECT
          log.userid,
          log.courseid,
          log.eventname,
          log.timecreated,
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
      -- calculates estimated duration of learning object views
      module_view_duration AS (
        SELECT
          userid,
          courseid,
          LEAST(
            COALESCE(next_event_time - timecreated, 0),
            :sessioncap
          ) AS estimated_seconds_spent
        FROM ordered_view_logs
        WHERE eventname IN (
          '\\mod_resource\\event\\course_module_viewed',
          '\\mod_url\\event\\course_module_viewed',
          '\\mod_page\\event\\course_module_viewed',
          '\\mod_lesson\\event\\course_module_viewed'
        )
      ),
      -- sum the learning object view time durations
      learning_object_totals AS (
        SELECT
          userid,
          courseid,
          SUM(estimated_seconds_spent) AS learning_object_view_time_seconds
        FROM module_view_duration
        GROUP BY userid, courseid
      )
      SELECT
        -- generate unique column id (required for Moodle sql)
        ROW_NUMBER() OVER (ORDER BY students.courseid, students.userid) AS recordid,
        students.userid AS student_id,
        students.courseid AS course_id,
        -- calculate learning object view duration for each student (set to 0 if no logs)
        COALESCE(totals.learning_object_view_time_seconds, 0)
          AS learning_object_view_time_seconds
      FROM course_students students
      LEFT JOIN learning_object_totals totals
        ON totals.userid = students.userid
        AND totals.courseid = students.courseid
      ORDER BY students.courseid, students.userid
    ";

    $records = $DB->get_records_sql($sql, [
      'coursecontextlevel' => CONTEXT_COURSE,
      'reportstart' => $reporting_start,
      'reportend' => $reporting_end,
      'sessioncap' => $session_cap,
    ] + $courseidsparams);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];
    $lo_access_time = [];

    foreach ($data as $student_id => $value) {
      $students[] =  intval($value->student_id);
      // convert learning object view time in seconds to hours, rounded up
      $lo_access_time[] = (int)ceil($value->learning_object_view_time_seconds / HOURSECS);
    }

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
