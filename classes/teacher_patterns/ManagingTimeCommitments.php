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
 * Models first teacher behaviour: Managing Time and Commitments
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
class ManagingTimeCommitments extends TeacherBehaviourPattern
{
  public function query_activity_one(int $threshold_grade, int $response_period)
  {
    global $DB, $USER;

    $sql = "
        SELECT
            m.id,
            m.useridfrom,
            m.conversationid,
            m.timecreated,
            m.fullmessage,
            ag.userid AS studentid,
            ag.grade,
            ag.timemodified AS gradeddate
          FROM {assign_grades} ag
          JOIN {message_conversation_members} mcm
            ON mcm.userid = ag.userid
          JOIN {messages} m
            ON m.conversationid = mcm.conversationid
        WHERE ag.grade < :gradethreshold
          AND m.useridfrom = :teacherid
          AND m.timecreated >= ag.timemodified
      ORDER BY ag.userid ASC, m.timecreated ASC
    ";

    $records = $DB->get_records_sql($sql, [
      'gradethreshold' => $threshold_grade,
      'teacherid' => $USER->id,
      // 'response_period' => $response_period
    ]);

    $this->records = $records;
  }

  public function time_commitment_messaging_low_grades(int $threshold_grade, int $response_period)
  {
    $data = new stdClass();

    $time_commiment_keywords = ['time management', 'organization'];

    $this->query_activity_one($threshold_grade, $response_period);

    $messages = $this->records;

    foreach ($messages as $message) {
      // Message properties
      $message_to = $message->studentid;
      $message_text = $message->fullmessage;

      $data->{$message_to} = $activity_behaviour = ActivityBehaviour::NotExhibited;

      // check for time_commitment_keywords in message
      foreach ($time_commiment_keywords as $keyword) {
        if ($keyword !== '' && stripos($message_text, $keyword) !== false) {
          // teacher is exhibiting the behaviour
          $data->{$message_to} = ActivityBehaviour::Exhibited;
          break;
        }
      }
    }

    // echo "<pre>";
    // var_dump($data);
    // echo "</pre>";
    // die();

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
          $exhibited++;
          break;

        case ActivityBehaviour::NotRequired:
          $exhibited++;
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

  public function create_line_chart(stdClass $activity_behaviour): \core\chart_line
  {
    $students = [];
    $values = [];

    foreach ($activity_behaviour as $student_id => $state) {
      if ($state === ActivityBehaviour::NotRequired) {
        continue;
      }

      $students[] = (string) $student_id;

      $values[] = match ($state) {
        ActivityBehaviour::Exhibited => 1,
        ActivityBehaviour::NotExhibited => 0,
      };
    }

    $chart = new \core\chart_line();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Behaviour Exhibited', $values);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Behaviour Exhibited");
    $yaxis->set_min(0);
    $yaxis->set_max(1);
    $yaxis->set_stepsize(1);

    return $chart;
  }
}
