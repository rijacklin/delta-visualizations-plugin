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
 * This behaviour is exhibited when the teacher interacts with the course a given threshold number of times each week.
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

  // public function query_behaviour_data(array $params)
  public function query_behaviour_data(array $params)
  {
    global $DB, $USER;

    // TODO: Replace with dynamic start and end dates
    $course_start = 1778540400;
    $course_end = time();
    $num_weeks = intval(($course_end - $course_start) / WEEKSECS);

    $teacher_id = $USER->id;

    if (empty($params['courseids'])) {
      return [];
    }

    [$courseidssql, $courseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'courseid'
    );

    $sql = "
      WITH teacher_course_logs AS (
        SELECT
          l.id,
          l.userid,
          l.courseid,
          l.component,
          l.action,
          l.target,
          l.crud,
          l.timecreated
        FROM {logstore_standard_log} l
        WHERE l.courseid $courseidssql
          AND l.userid = :userid
          AND l.timecreated >= :starttime
          AND l.timecreated <= :endtime
      )
      SELECT
        l.userid,
        COUNT(l.id) AS total_teacher_interactions,
        COUNT(l.id) / :periodweeks AS interactions_per_week
      FROM teacher_course_logs l 
      GROUP BY l.userid
      ORDER BY total_teacher_interactions DESC;
    ";

    $records = $DB->get_records_sql($sql, [
      // TODO: replace with something better than array index
      'userid' => $teacher_id,
      'courseids' => implode(",", $params['courseids']),
      'periodweeks' => $num_weeks,
      'starttime' => $course_start,
      'endtime' => $course_end
    ] + $courseidsparams);

    $data = new stdClass();

    // iterate over feedback
    foreach ($records as $interactions) {
      // Grab feedback properties
      $interactions_per_unit = $interactions->interactions_per_week;

      // check to see if feedback sufficiently personalized/unique
      if ($interactions_per_unit > $params['engagementthreshold']) {
        $data->{$teacher_id} = ActivityBehaviour::Exhibited;
      } else {
        $data->{$teacher_id} = ActivityBehaviour::NotExhibited;
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

  public function generate_behaviour_pie_chart(array $params)
  {
    // $chart = new \core\chart_pie();
    $chart = "";

    if (!empty($params['courseids'])) {
      $behaviour_data = $this->query_behaviour_data($params);
      $chart = $this->create_pie_chart($behaviour_data);
    }

    return $chart;
  }
}
