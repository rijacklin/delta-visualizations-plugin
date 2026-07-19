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
 * Models teacher behaviour: Personalized Feedback
 *
 * Behaviour Pattern Description: All students should receive assignment feedback
 * that is personalized. This behaviour compares all assignment feedback from a
 * teacher and calculates a uniqueness score. This behaviour is exhibited if the
 * teacher feedback contains language that is sufficiently unique.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\teacher_patterns;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the PersonalizedFeedback teacher behaviour pattern.
 */
class PersonalizedFeedback extends TeacherBehaviourPattern
{
  public function query_behaviour_data(array $params)
  {
    global $DB;

    // early exit if no selected courses
    if (empty($params['courseids'])) {
      return [];
    }

    // build parameterized SQL IN condition for selected courseids (required for Moodle DML API)
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
          afcom.commenttext as feedback_text
        FROM target_students ts
        join {assignfeedback_comments} afcom
          on afcom.assignment = ts.assignment AND afcom.grade = ts.id
      )
      select
        feedback_id,
        student_id,
        feedback_text
      FROM targeted_feedback
      ORDER BY
        feedback_id ASC,
        student_id ASC;
    ";

    // access records from query using moodle DML and store on clas instance
    $records = $DB->get_records_sql($sql, [
      'gradethreshold' => $params['gradethreshold'],
    ] + $courseidsparams);
    $this->records = $records;

    // store the configured feedback goal
    $feedback_goal = $params['feedbackgoal'];

    $data = new stdClass();

    if (!empty($records)) {
      // store an aray of the words of the first feedback message to compare the others to
      $feedback_to_compare_text = explode(' ', $records[array_key_first($records)]->feedback_text);
    }

    // iterate over feedback
    foreach ($records as $feedback_to_score) {
      // message properties
      $feedback_to = $feedback_to_score->student_id;

      // early exit
      if (empty($feedback_to_score->feedback_text)) {
        $data->{$feedback_to} = ActivityBehaviour::NotExhibited;

        // skip to next iteration
        continue;
      }

      // store text as array of words to iterate over
      $feedback_to_score_text = explode(' ', $feedback_to_score->feedback_text);

      // calculate how unique feedback is to student
      foreach ($feedback_to_score_text as $word_to_score) {
        foreach ($feedback_to_compare_text as $word_to_compare) {
          $similar_count = 0;
          $max_similar_count = 0;
          $feedback_length = 0;

          $feedback_length++;

          if ($word_to_compare == $word_to_score) {
            $similar_count++;
          }
        }

        // check to see if this feedback message has higher similar count than feedback already compared
        if ($similar_count > $max_similar_count) {
          $max_similar_count = $similar_count;
        }
      }

      // calcluate percentage of feedback
      $feedback_percent = $max_similar_count / $feedback_length;

      // check to see if feedback sufficiently personalized/unique
      if ($feedback_percent >= ($feedback_goal / 100)) {
        $data->{$feedback_to} = ActivityBehaviour::NotExhibited;
      } else {
        $data->{$feedback_to} = ActivityBehaviour::Exhibited;
      }
    }

    return $data;
  }
}
