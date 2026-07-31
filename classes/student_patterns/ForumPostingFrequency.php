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
 * Models student behaviour: Forum Posting Frequency
 *
 * Behaviour Pattern Description: Number of forum posts by a student in a course.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the ForumPostingFrequency student behaviour pattern.
 */
class ForumPostingFrequency extends StudentBehaviourPattern
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
      ),
      -- return each student's forum posts from selected courses
      post_counts AS (
        SELECT
          fp.userid,
          fd.course AS courseid,
          COUNT(fp.id) AS forum_post_frequency
        FROM {forum_posts} fp
        JOIN {forum_discussions} fd
          ON fd.id = fp.discussion
        JOIN course_students students
          ON students.userid = fp.userid
          AND students.courseid = fd.course
        GROUP BY fp.userid, fd.course
      )
      SELECT
        -- generate unique column id (required for Moodle sql)
        ROW_NUMBER() OVER (ORDER BY students.courseid, students.userid) AS recordid,
        students.userid AS student_id,
        students.courseid AS course_id,
        -- count each forum post only once per student and course
        COALESCE(counts.forum_post_frequency, 0) AS forum_post_frequency
      FROM course_students students
      LEFT JOIN post_counts counts
        ON counts.userid = students.userid
        AND counts.courseid = students.courseid
      ORDER BY students.courseid, students.userid
    ";

    $records = $DB->get_records_sql($sql, [
      'coursecontextlevel' => CONTEXT_COURSE,
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
      $count[] = intval($value->forum_post_frequency);
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Number of Forum Postings', $count);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Number of Forum Postings");
    $yaxis->set_min(0);
    $yaxis->set_max(100);
    $yaxis->set_stepsize(10);

    return $chart;
  }
}
