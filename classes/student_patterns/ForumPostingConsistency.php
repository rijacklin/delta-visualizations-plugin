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
 * Models student behaviour: Forum Posting Consistency
 *
 * Behaviour Pattern Description: Compares the number of student forum posts
 * before and after a cut-off date, reflecting how consistently students
 * contribute to discussions in the course. 
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the ForumPostingConsistency student behaviour pattern.
 */
class ForumPostingConsistency extends StudentBehaviourPattern
{
  protected function query_behaviour_data(array $params)
  {
    global $DB;

    $cutoff_window_seconds = (int)$params['final_window_weeks'] * WEEKSECS;

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
          c.id AS courseid,
          c.startdate AS course_start,
          c.enddate AS course_end,
          c.enddate - :cutoffwindow AS course_cutoff
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
          AND c.enddate > c.startdate + :minimumduration
      ),
      -- return posts from before and during the final two weeks of each course
      post_counts AS (
        SELECT
          fp.userid,
          fd.course AS courseid,
          SUM(
            CASE
              WHEN fp.created < students.course_cutoff THEN 1
              ELSE 0
            END
          ) AS forum_posts_before,
          SUM(
            CASE
              WHEN fp.created >= students.course_cutoff THEN 1
              ELSE 0
            END
          ) AS forum_posts_after
        FROM {forum_posts} fp
        JOIN {forum_discussions} fd
          ON fd.id = fp.discussion
        JOIN course_students students
          ON students.userid = fp.userid
          AND students.courseid = fd.course
        WHERE fp.created >= students.course_start
          AND fp.created < students.course_end
        GROUP BY fp.userid, fd.course
      )
      SELECT
        -- generate unique column id (required for Moodle sql)
        ROW_NUMBER() OVER (ORDER BY students.courseid, students.userid) AS recordid,
        students.userid AS student_id,
        students.courseid AS course_id,
        COALESCE(counts.forum_posts_before, 0) AS forum_posts_before,
        COALESCE(counts.forum_posts_after, 0) AS forum_posts_after
      FROM course_students students
      LEFT JOIN post_counts counts
        ON counts.userid = students.userid
        AND counts.courseid = students.courseid
      ORDER BY students.courseid, students.userid
    ";

    $records = $DB->get_records_sql($sql, [
      'coursecontextlevel' => CONTEXT_COURSE,
      'cutoffwindow' => $cutoff_window_seconds,
      'minimumduration' => $cutoff_window_seconds,
    ] + $courseidsparams);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $students = [];
    $posts_before = [];
    $posts_after = [];

    foreach ($this->records as $value) {
      $students[] = intval($value->student_id);
      $posts_before[] = intval($value->forum_posts_before);
      $posts_after[] = intval($value->forum_posts_after);
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $before_series = new \core\chart_series('Posts Before Final Window', $posts_before);
    $chart->add_series($before_series);

    $after_series = new \core\chart_series('Posts During Final Window', $posts_after);
    $chart->add_series($after_series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Posts Before/During Final Window");
    $yaxis->set_min(0);
    $yaxis->set_max(100);
    $yaxis->set_stepsize(10);

    return $chart;
  }
}
