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
  public function query_behaviour_data(array $params)
  {
    global $DB;

    if (empty($params['courseids'])) {
      return [];
    }

    $window_seconds = (int)$params['final_window_weeks'] * WEEKSECS;

    [$courseidssql, $courseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'courseid'
    );

    $sql = "
      WITH course_windows AS (
        SELECT
          c.id,
          c.startdate,
          c.enddate,
          c.enddate - :windowseconds AS cutoffdate
        FROM {course} c
        WHERE c.id $courseidssql
          AND c.enddate > c.startdate
      )
      SELECT
        fp.userid,
        SUM(
          CASE
            WHEN fp.created < cw.cutoffdate
            THEN 1
            ELSE 0
          END
        ) AS posts_before,
        SUM(
          CASE
            WHEN fp.created >= cw.cutoffdate
            THEN 1
            ELSE 0
          END
        ) AS posts_after,
        COUNT(fp.id) AS total_posts
      FROM {forum_posts} fp
      JOIN {forum_discussions} fd
        ON fd.id = fp.discussion
      JOIN course_windows cw
        ON cw.id = fd.course
      WHERE fp.userid IS NOT NULL
        AND fp.created >= cw.startdate
        AND fp.created <= cw.enddate
      GROUP BY fp.userid
      ORDER BY fp.userid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      'windowseconds' => $window_seconds
    ] + $courseidsparams);

    $this->records = $records;
  }

  public function create_line_chart(): \core\chart_line
  {
    $data = $this->records;

    $students = [];
    $posts_before = [];
    $posts_after = [];

    foreach ($data as $student_id => $value) {
      $students[] = intval($student_id);
      $posts_before[] = intval($value->posts_before);
      $posts_after[] = intval($value->posts_after);
    }

    $chart = new \core\chart_line();

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

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];
    $posts_before = [];
    $posts_after = [];

    foreach ($data as $student_id => $value) {
      $students[] = intval($student_id);
      $posts_before[] = intval($value->posts_before);
      $posts_after[] = intval($value->posts_after);
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
