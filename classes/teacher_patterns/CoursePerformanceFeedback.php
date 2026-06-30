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
 * Models teacher behaviour: Course Performance Feedback
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
class CoursePerformanceFeedback extends TeacherBehaviourPattern
{
  // use BarChart;
  use PieChart;

  public function query_behaviour_data(array $params)
  {
    global $DB, $USER;

    if (empty($params['courseids'])) {
      return [];
    }

    [$courseidssql, $courseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'courseid'
    );

    $sql = "
      WITH target_students AS (
        select
            ag.id,
            ag.userid AS studentid,
            ag.assignment,
            ag.grade,
            assign.course as courseid,
            ag.timemodified AS gradeddate
        FROM {assign_grades} ag
        JOIN {assign} assign
          ON assign.id = ag.assignment
        WHERE ag.grade < :gradethreshold
          AND assign.course $courseidssql
        ORDER BY ag.userid ASC
      ),
      targeted_feedback AS (
        select 
          afcom.id as feedback_id,
          ts.studentid as student_id,
          afcom.commenttext as feedback_text,
          ts.courseid as courseid
        FROM target_students ts
        join {assignfeedback_comments} afcom
          on afcom.assignment = ts.assignment AND afcom.grade = ts.id
      )
      select
        feedback_id,
        student_id,
        courseid,
        feedback_text
      FROM targeted_feedback
      ORDER BY
        feedback_id ASC,
        student_id ASC,
        courseid ASC;
    ";

    $records = $DB->get_records_sql($sql, [
      'gradethreshold' => $params['gradethreshold'],
    ] + $courseidsparams);

    // store records
    $this->records = $records;

    $improvement_keywords = ['organization', 'textbook', 'materials', 'effort'];

    $messages = $records;

    $data = new stdClass();

    foreach ($messages as $message) {
      // message properties
      $message_to = $message->student_id;
      $message_text = $message->feedback_text;

      // early exit
      if (empty($message_text)) {
        $data->{$message_to} = ActivityBehaviour::NotExhibited;
        continue;
      }

      // default
      $behaviour = ActivityBehaviour::NotExhibited;

      // check for time_commitment_keywords in message
      foreach ($improvement_keywords as $keyword) {
        if (!empty($keyword) && stripos($message_text, $keyword) !== false) {
          // teacher is exhibiting the behaviour
          $behaviour = ActivityBehaviour::Exhibited;
          break;
        }
      }

      $data->{$message_to} = $behaviour;
    }

    return $data;
  }

  public function create_pie_chart(stdClass $activity_behaviour): void
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

    $this->chart = $chart;
  }

  public function generate_behaviour_pie_chart(array $params): void
  {
    if (!empty($params['courseids'])) {
      $behaviour_data = $this->query_behaviour_data($params);
      $this->create_pie_chart($behaviour_data);
    }
  }
}
