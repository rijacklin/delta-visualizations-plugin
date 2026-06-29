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
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates a renderer for the block_delta_visualizations
 *
 */
class ForumPostingConsistency extends StudentBehaviourPattern
{
  use BarChart;

  public function query_behaviour_data(array $params)
  {
    global $DB;

    // get course end date for student (#TODO: how to do this dynamically?)
    $course_end_date = 1782144060;  // Monday June 22, 12:01 AM

    // two-week cutoff (#TODO: pass  user-defined cut-off?)
    $cutoff_date = $course_end_date - (2 * WEEKSECS);

    if (empty($params['courseids'])) {
      return [];
    }

    [$courseidssql, $courseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'courseid'
    );

    $sql = "
      SELECT
        fp.userid,
        SUM(
          CASE
            WHEN fp.created >= c.startdate
              AND fp.created < :cutoffdate1
            THEN 1
            ELSE 0
          END
        ) AS posts_before,
        SUM(
          CASE
            WHEN fp.created >= :cutoffdate2
              AND fp.created <= :courseenddate1
            THEN 1
            ELSE 0
          END
        ) AS posts_after,
        COUNT(fp.id) AS total_posts
      FROM {forum_posts} fp
      JOIN {forum_discussions} fd
        ON fd.id = fp.discussion
      JOIN {course} c
        ON c.id = fd.course
      WHERE fp.userid IS NOT NULL
        AND fd.course $courseidssql
        AND fp.created >= c.startdate
        AND fp.created <= :courseenddate2
      GROUP BY fp.userid
      ORDER BY fp.userid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      // TODO: Modify so that 1 and 2 aren't being used to differentiate repeated variables
      'courseenddate1' => $course_end_date,
      'courseenddate2' => $course_end_date,
      'cutoffdate1' => $cutoff_date,
      'cutoffdate2' => $cutoff_date
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
    $before_series = new \core\chart_series('Posts Before Cutoff', $posts_before);
    $chart->add_series($before_series);

    $after_series = new \core\chart_series('Posts After Cutoff', $posts_after);
    $chart->add_series($after_series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Posts Before/After Cutoff");
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
    $before_series = new \core\chart_series('Posts Before Cutoff', $posts_before);
    $chart->add_series($before_series);

    $after_series = new \core\chart_series('Posts After Cutoff', $posts_after);
    $chart->add_series($after_series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Posts Before/After Cutoff");
    $yaxis->set_min(0);
    $yaxis->set_max(100);
    $yaxis->set_stepsize(10);

    return $chart;
  }
}
