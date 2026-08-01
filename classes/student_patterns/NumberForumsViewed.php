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
 * Models student behaviour: Number of Forums Viewed
 *
 * Behaviour Pattern Description:
 *  Number of forum discussions a student has viewed in the course.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the NumberForumsViewed student behaviour pattern.
 */
class NumberForumsViewed extends StudentBehaviourPattern
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
      -- return records of students in selected courses
      WITH course_students AS (
        SELECT DISTINCT
          ra.userid,
          c.id AS courseid
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
      )
      SELECT
        -- generate unique column id (required for Moodle sql)
        ROW_NUMBER() OVER (ORDER BY students.courseid, students.userid) AS recordid,
        students.userid AS student_id,
        students.courseid AS course_id,
        -- count each forum view only once per student and course
        COUNT(DISTINCT log.objectid) AS forums_viewed_count
      FROM course_students students
      LEFT JOIN {logstore_standard_log} log
        ON log.userid = students.userid
        AND log.courseid = students.courseid
        AND log.eventname = '\\mod_forum\\event\\discussion_viewed'
      GROUP BY students.userid, students.courseid
      ORDER BY students.courseid, students.userid
    ";

    $records = $DB->get_records_sql($sql, [
      'coursecontextlevel' => CONTEXT_COURSE,
    ] + $courseidsparams);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $students = [];
    $count = [];

    foreach ($this->records as $value) {
      $students[] = intval($value->student_id);
      $count[] = intval($value->forums_viewed_count);
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Number of Forums Viewed', $count);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Number of Forums Viewed");
    $yaxis->set_min(0);
    $yaxis->set_max(100);
    $yaxis->set_stepsize(10);

    return $chart;
  }
}
