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
  // static values for now
  protected $table = 'logstore_standard_log';

  public function query_behaviour_data(array $params)
  {
    global $DB;

    if (empty($params['courseids'])) {
      return;
    }

    [$enrolledcourseidssql, $enrolledcourseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'enrolledcourseid'
    );

    [$accesscourseidssql, $accesscourseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'accesscourseid'
    );

    $sql = "
      WITH course_students AS (
        SELECT DISTINCT ra.userid
        FROM {role_assignments} ra
        JOIN {role} r
          ON r.id = ra.roleid
        JOIN {context} ctx
          ON ctx.id = ra.contextid
        WHERE r.shortname = 'student'
          AND ctx.contextlevel = :coursecontext
          AND ctx.instanceid $enrolledcourseidssql
      ),
      student_logins AS (
        SELECT
          login.id,
          login.userid,
          login.timecreated,
          LEAD(login.timecreated) OVER (
            PARTITION BY login.userid
            ORDER BY login.timecreated, login.id
          ) AS next_login_time
        FROM {logstore_standard_log} login
        JOIN course_students cs
          ON cs.userid = login.userid
        WHERE login.eventname = :loginevent
      ),
      course_access_logins AS (
        SELECT DISTINCT
          login.id,
          login.userid
        FROM student_logins login
        JOIN {logstore_standard_log} accesslog
          ON accesslog.userid = login.userid
          AND accesslog.eventname = :courseaccessevent
          AND accesslog.courseid $accesscourseidssql
          AND accesslog.timecreated >= login.timecreated
          AND (
            login.next_login_time IS NULL
            OR accesslog.timecreated < login.next_login_time
          )
      )
      SELECT
        userid,
        COUNT(id) AS logincount
      FROM course_access_logins
      GROUP BY userid
      ORDER BY userid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      'coursecontext' => CONTEXT_COURSE,
      'loginevent' => '\\core\\event\\user_loggedin',
      'courseaccessevent' => '\\core\\event\\course_viewed',
    ] + $enrolledcourseidsparams + $accesscourseidsparams);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];
    $frequency = [];

    foreach ($data as $student_id => $value) {
      $students[] =  intval($student_id);
      $frequency[] = $value->logincount;
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
