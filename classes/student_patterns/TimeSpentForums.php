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

use block_delta_visualizations\local\BehaviourConfig;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the TimeSpentForums student behaviour pattern.
 */
class TimeSpentForums extends StudentBehaviourPattern
{
  protected function query_behaviour_data(array $params)
  {
    global $DB;

    [$courseidssql, $courseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'courseid'
    );

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
          log.userid AS student_id,
          log.courseid AS course_id,
          students.course_start,
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
      -- sum the forum view time durations
      forum_time_totals AS (
        SELECT
          student_id,
          course_id,
          SUM(
            CASE
              -- account for events with no timestamp (error handling)
              WHEN next_event_time IS NULL THEN 0
              -- default to sessioncap for events that exceed it
              WHEN next_event_time - timecreated > :sessioncaplimit
                THEN :sessioncapvalue
              -- otherwise, calculate forum view duration
              ELSE next_event_time - timecreated
            END
          ) AS total_seconds_spent
        FROM ordered_view_logs
        WHERE component = 'mod_forum'
          AND action = 'viewed'
        GROUP BY student_id, course_id
      )
      -- returns total forum view duration for each student in selected courses
      SELECT
        -- generate unique column id (required for Moodle sql)
        ROW_NUMBER() OVER (ORDER BY student_id, course_id) AS recordid,
        student_id,
        course_id,
        total_seconds_spent
      FROM forum_time_totals
      ORDER BY student_id, course_id
    ";

    $records = $DB->get_records_sql($sql, [
      'coursecontextlevel' => CONTEXT_COURSE,
      'reportstart' => $reporting_start,
      'reportend' => $reporting_end,
      'sessioncaplimit' => $params['sessioncap'],
      'sessioncapvalue' => $params['sessioncap'],
    ] + $courseidsparams);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $students = [];
    $forum_view_time = [];

    foreach ($this->records as $value) {
      $students[] =  intval($value->student_id);
      // convert active time in seconds to hours, rounded up
      $forum_view_time[] = (int)ceil($value->total_seconds_spent / HOURSECS);
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
