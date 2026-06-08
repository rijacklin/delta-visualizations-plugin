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
 * Models teacher behaviour: Timely Feedback
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
class TimelyFeedback extends TeacherBehaviourPattern
{
  use PieChart;

  public function query_behaviour_data(array $params)
  {
    global $DB, $USER;

    $sql = "
      WITH target_students AS (
        select
            ag.id,
            ag.userid AS student_id,
            ag.assignment as assignment_id,
            ag.grade,
            ag.timemodified AS graded_date
        FROM m_assign_grades ag
        JOIN m_assign assign
          ON assign.id = ag.assignment
        WHERE ag.grade < :gradethreshold
          AND assign.course = :courseid
        ORDER BY ag.userid ASC
      ),
      timley_feedback AS (
        select 
          afcom.id as feedback_id,
          student_id,
          afcom.commenttext as feedback_text,
          asub.timecreated as submission_date,
          graded_date,
          assignment_id
        FROM target_students ts
        JOIN m_assignfeedback_comments afcom
          ON afcom.assignment = assignment_id AND afcom.grade = ts.id
        JOIN m_assign_submission asub
          ON asub.assignment = assignment_id AND asub.userid = student_id
      )
      SELECT
        feedback_id,
        student_id,
        graded_date,
        submission_date,
        feedback_text
      FROM timley_feedback
      ORDER BY
        feedback_id ASC,
        student_id ASC;
    ";

    $response_days_in_seconds = (int)$params[1] * 86400;

    $records = $DB->get_records_sql($sql, [
      // TODO: replace with something better than array index
      'gradethreshold' => $params[0],
      'teacherid' => $USER->id,
      'courseid' => 3
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
