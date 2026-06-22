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
 * Models teacher behaviour: Consistent Use of LMS
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\teacher_patterns;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates a renderer for the block_delta_visualizations
 *
 */
class ConsistentUseLMS extends TeacherBehaviourPattern
{
  use PieChart;

  public function query_behaviour_data(array $params)
  {
    global $DB, $USER;

    $sql = "";

    $response_days_in_seconds = (int)$params['days'] * 86400;

    $records = $DB->get_records_sql($sql, [
      // TODO: replace with something better than array index
      'gradethreshold' => $params['gradethreshold'],
      'teacherid' => $USER->id,
      'courseid' => $params['courseid']
    ]);

    $data = new stdClass();

    // iterate over feedback
    foreach ($records as $feedback) {
      // Grab feedback properties
      $graded_date = $feedback->graded_date;
      $submission_date = $feedback->submission_date;
      $student_id = $feedback->student_id;

      // check to see if feedback sufficiently personalized/unique
      if ($graded_date <= ($submission_date + $response_days_in_seconds)) {
        $data->{$student_id} = ActivityBehaviour::Exhibited;
      } else {
        $data->{$student_id} = ActivityBehaviour::NotExhibited;
      }
    }

    return $data;
  }

  public function create_pie_chart(stdClass $activity_behaviour): \core\chart_pie
  {
    $exhibited = 0;
    $not_exhibited = 0;
    $not_required = 0;

    foreach ($activity_behaviour as $state) {
      switch ($state) {
        case ActivityBehaviour::Exhibited:
          $exhibited++;
          break;

        case ActivityBehaviour::NotExhibited:
          $not_exhibited++;
          break;

        case ActivityBehaviour::NotRequired:
          $not_required++;
          break;
      }
    }

    $chart = new \core\chart_pie();
    $chart->set_labels([
      ActivityBehaviour::Exhibited->label(),
      ActivityBehaviour::NotExhibited->label(),
      ActivityBehaviour::NotRequired->label(),
    ]);

    $series_behaviour = new \core\chart_series('Behaviour Exhibited', [
      $exhibited,
      $not_exhibited,
      $not_required,
    ]);

    $chart->add_series($series_behaviour);

    return $chart;
  }
}
