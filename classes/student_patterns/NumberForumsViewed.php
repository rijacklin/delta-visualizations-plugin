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
class NumberForumsViewed extends StudentBehaviourPattern
{
  use BarChart;

  public function query_behaviour_data()
  {
    global $DB;

    $now = time();

    // TODO: Grab actual courseid from template
    $courseid = 3;

    switch ($this->time_range) {
      case TimeRange::HOURLY:
        $start_time = $now - HOURSECS;
        break;
      case TimeRange::DAILY:
        $start_time = $now - DAYSECS;
        break;
      case TimeRange::WEEKLY:
        $start_time = $now - WEEKSECS;
        break;
      default:
        $start_time = 0;
        break;
    }

    $sql = "
      SELECT
        fr.userid,
        COUNT(fr.postid)
      FROM m_forum_read fr
      JOIN m_forum f
        ON f.id = fr.forumid
      WHERE fr.userid IS NOT null
        AND f.course = :courseid
        -- Filter by hourly/daily/weekly
        AND f.timemodified >= :starttime
      GROUP BY fr.id
      ORDER BY fr.userid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      // TODO: Grab actual courseid from template
      'courseid' => $courseid,
      'starttime' => $start_time
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

    // TODO: REMOVE LATER; TEMP TO SHOW VALUE CONTRAST ON CHART
    $students[] = intval(3);
    $count[] = intval(24);
    // END TODO

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
