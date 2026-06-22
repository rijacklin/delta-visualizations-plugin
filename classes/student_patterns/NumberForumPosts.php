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
 * Models student behaviour: Number of Forum Posts
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
class NumberForumPosts extends StudentBehaviourPattern
{
  use BarChart;

  public function query_behaviour_data()
  {
    global $DB;

    // TODO: Grab actual courseid from template
    $courseid = 3;

    $sql = "
      SELECT
        fp.userid,
        COUNT(fp.id)
      FROM {forum_posts} fp
      JOIN {forum_discussions} fd
        ON fd.id = fp.discussion
      WHERE fp.userid IS NOT null
        AND fd.course = :courseid
      GROUP BY fp.userid
      ORDER BY fp.userid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      // TODO: Grab actual courseid from template
      'courseid' => $courseid,
    ]);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];
    $count = [];

    foreach ($data as $student_id => $value) {
      $students[] = intval($student_id);
      $count[] = intval($value->count);
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Number of Forum Posts', $count);
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
