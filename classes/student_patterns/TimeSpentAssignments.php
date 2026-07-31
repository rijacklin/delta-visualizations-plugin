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
 * Models student behaviour: Time Spent on Assignments
 *
 * Behaviour Pattern Description: Time duration of student first accessing the
 * assignment learning object and submitting the assignment.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the TimeSpentAssignments student behaviour pattern.
 */
class TimeSpentAssignments extends StudentBehaviourPattern
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
          ra.userid as student_id,
          c.id AS course_id,
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
      -- returns each student's first view of every assignment during the course.
      first_assignment_views AS (
        SELECT
          student_id,
          course_id,
          students.course_start,
          students.course_end,
          log.contextinstanceid AS coursemoduleid,
          MIN(log.timecreated) AS first_view_time
        FROM course_students students
        JOIN {logstore_standard_log} log
          ON log.userid = students.student_id 
          AND log.courseid = students.course_id
        WHERE log.eventname = '\\mod_assign\\event\\course_module_viewed'
          -- handle client-side filtering of reporting periods
          AND log.timecreated >= students.course_start
          AND log.timecreated >= :reportstart
          AND log.timecreated <= students.course_end
          AND log.timecreated <= :reportendview
        GROUP BY
          student_id,
          course_id,
          students.course_start,
          students.course_end,
          log.contextinstanceid
      ),
      -- returns measured duration from assignment's first view to submission.
      assignment_durations AS (
        SELECT
          views.student_id,
          views.course_id,
          views.coursemoduleid,
          COALESCE(
            MIN(submission.timecreated) - views.first_view_time,
            0
          ) AS assignment_time_seconds
        FROM first_assignment_views views
        LEFT JOIN {logstore_standard_log} submission
          ON submission.userid = views.student_id
          AND submission.courseid = views.course_id
          AND submission.contextinstanceid = views.coursemoduleid
          AND submission.eventname = '\\mod_assign\\event\\assessable_submitted'
          -- handle client-side filtering of reporting periods
          AND submission.timecreated >= views.first_view_time
          AND submission.timecreated <= views.course_end
          AND submission.timecreated <= :reportendsubmission
        GROUP BY
          views.student_id,
          views.course_id,
          views.coursemoduleid,
          views.first_view_time
      ),
      -- returns combined assignment durations for each student and course.
      assignment_totals AS (
        SELECT
          student_id,
          course_id,
          SUM(assignment_time_seconds) AS assignment_time_seconds
        FROM assignment_durations
        GROUP BY student_id, course_id
      )
      SELECT
        -- returns total assignment view duration for each student in selected courses
        ROW_NUMBER() OVER (ORDER BY students.student_id, students.course_id) AS recordid,
        students.student_id,
        students.course_id,
        COALESCE(totals.assignment_time_seconds, 0) AS assignment_time_seconds
      FROM course_students students
      LEFT JOIN assignment_totals totals
        ON totals.student_id = students.student_id
        AND totals.course_id = students.course_id
      ORDER BY students.student_id, students.course_id
    ";

    $records = $DB->get_records_sql($sql, [
      'coursecontextlevel' => CONTEXT_COURSE,
      'reportstart' => $reporting_start,
      'reportendview' => $reporting_end,
      'reportendsubmission' => $reporting_end,
    ] + $courseidsparams);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $students = [];
    $assign_view_time = [];

    foreach ($this->records as $value) {
      $students[] = intval($value->student_id);
      // convert active time in seconds to hours, rounded up
      $assign_view_time[] = (int)ceil($value->assignment_time_seconds / HOURSECS);
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Hours Spent On Assignments', $assign_view_time);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Hours Spent On Assignments");
    $yaxis->set_stepsize(10);

    return $chart;
  }
}
