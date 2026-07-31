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
 * Models first student behaviour: Login Frequency
 *
 * Behaviour Pattern Description: Number of times each student both logs into the
 * LMS and accesses the course.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the LoginFrequency student behaviour pattern.
 */
class LoginFrequency extends StudentBehaviourPattern
{
  protected function query_behaviour_data(array $params)
  {
    global $DB;

    [$courseidssql, $courseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'courseid'
    );

    $sql = "
      -- returns rows mapping distinct student to course for each selected course
      WITH course_students AS (
        SELECT DISTINCT
          ra.userid,
          c.id AS course_id,
          c.startdate AS course_start,
          -- account for active courses without end date
          CASE
            WHEN c.enddate = 0 THEN :querytime
            ELSE c.enddate
          END AS course_end
        FROM {course} c
        JOIN {context} ctx
          ON ctx.contextlevel = :coursecontext
          AND ctx.instanceid = c.id
        JOIN {role_assignments} ra
          ON ra.contextid = ctx.id
        JOIN {role} r
          ON r.id = ra.roleid
        WHERE r.shortname = 'student'
          AND c.id $courseidssql
      ),
      -- returns flattened student-course rows across selected courses so login event is only queried once, not per course
      students AS (
        SELECT DISTINCT userid
        FROM course_students
      ),
      -- returns login event for each student in in selected courses
      student_logins AS (
        SELECT
          login.id AS login_id,
          login.userid,
          login.timecreated AS login_time,
          -- grab subsequent login to estimate login event duration (as moodle doesn't always catch log outs)
          LEAD(login.timecreated) OVER (
            PARTITION BY login.userid
            ORDER BY login.timecreated, login.id
          ) AS next_login_time
        FROM {logstore_standard_log} login
        JOIN students
          ON students.userid = login.userid
        WHERE login.eventname = '\\core\\event\\user_loggedin'
      ),
      -- returns the required login and course view for students
      course_access_logins AS (
        SELECT DISTINCT
          logins.userid,
          logins.login_id
        FROM student_logins logins
        JOIN course_students students
          ON students.userid = logins.userid
          AND logins.login_time >= students.course_start
          AND logins.login_time <= students.course_end
        -- check to ensure student accesses course before next login_event
        WHERE EXISTS (
          SELECT 1
          FROM {logstore_standard_log} accesslog
          WHERE accesslog.userid = logins.userid
            AND accesslog.courseid = students.course_id
            AND accesslog.eventname = '\\core\\event\\course_viewed'
            AND accesslog.timecreated >= logins.login_time
            AND (
              logins.next_login_time IS NULL
              OR accesslog.timecreated < logins.next_login_time
            )
          )
      )
      -- returns frequency total for each student in selected courses (sum of frequency when same student is enrolled in and acccess multiple selected courses during a login event)
      SELECT
        students.userid AS student_id,
        COUNT(accesses.login_id) AS login_frequency
      FROM students
      LEFT JOIN course_access_logins accesses
        ON accesses.userid = students.userid
      GROUP BY students.userid
      ORDER BY students.userid
    ";

    $records = $DB->get_records_sql($sql, [
      'querytime' => time(),
      'coursecontext' => CONTEXT_COURSE,
    ] + $courseidsparams);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $students = [];
    $frequency = [];

    foreach ($this->records as $value) {
      $students[] = intval($value->student_id);
      $frequency[] = $value->login_frequency;
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Login Frequency', $frequency);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Login Frequency");
    $yaxis->set_stepsize(5);

    return $chart;
  }
}
