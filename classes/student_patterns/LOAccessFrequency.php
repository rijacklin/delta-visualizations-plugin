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
 * Models student behaviour: Learning Object Access Frequency
 *
 * Behaviour Pattern Description: Number of times student accesses a learning
 * object in the course.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the LOAccessFrequency student behaviour pattern.
 */
class LOAccessFrequency extends StudentBehaviourPattern
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
      )
      SELECT
        -- returns total assignment view duration for each student in selected courses
        ROW_NUMBER() OVER (ORDER BY students.courseid, students.userid) AS recordid,
        students.userid as student_id,
        students.courseid as course_id,
        COUNT(log.id) AS learning_object_access_frequency
      FROM course_students students
      LEFT JOIN {logstore_standard_log} log
        ON log.userid = students.userid
        AND log.courseid = students.courseid
        AND log.eventname IN (
          '\\mod_forum\\event\\course_module_viewed',
          '\\mod_assign\\event\\course_module_viewed',
          '\\mod_resource\\event\\course_module_viewed',
          '\\mod_url\\event\\course_module_viewed',
          '\\mod_page\\event\\course_module_viewed',
          '\\mod_lesson\\event\\course_module_viewed'
        )
        -- handle client-side filtering of reporting periods
        AND log.timecreated >= students.course_start
        AND log.timecreated >= :reportstart
        AND log.timecreated < students.course_end
        AND log.timecreated < :reportend
      GROUP BY student_id, course_id
      ORDER BY student_id, course_id
    ";

    $records = $DB->get_records_sql($sql, [
      'coursecontextlevel' => CONTEXT_COURSE,
      'reportstart' => $reporting_start,
      'reportend' => $reporting_end,
    ] + $courseidsparams);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];
    $count = [];

    foreach ($data as $value) {
      $students[] = intval($value->student_id);
      $count[] = intval($value->learning_object_access_frequency);
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Learning Objects Accessed', $count);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Learning Objects Accessed");
    $yaxis->set_min(0);
    $yaxis->set_max(100);
    $yaxis->set_stepsize(10);

    return $chart;
  }
}
