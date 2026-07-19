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
 * Behaviour Pattern Description: Students who are not achieving sufficient
 * assignment grades should receive feedback from their teachers to encourage
 * and guide them to higher grades. This behaviour is exhibited by teachers who
 * identify students who are performing below grade expectations and writing
 * feedback that identifies where and how the student can improve. Teachers who
 * do not provide feedback containing such language fail to exhibit this
 * behaviour.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\teacher_patterns;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the CoursePerformanceFeedback teacher behaviour pattern.
 */
class CoursePerformanceFeedback extends TeacherBehaviourPattern
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

    // access records from query using moodle DML and store on class instance
    $records = $DB->get_records_sql($sql, [
      'gradethreshold' => $params['gradethreshold'],
    ] + $courseidsparams);
    $this->records = $records;

    $improvement_keywords = ['organization', 'textbook', 'materials', 'effort'];

    $messages = $records;

    $data = new stdClass();

    // iterate over feedback messages
    foreach ($messages as $message) {
      // message properties
      $message_to = $message->student_id;
      $message_text = $message->feedback_text;

      // early exit
      if (empty($message_text)) {
        $data->{$message_to} = ActivityBehaviour::NotExhibited;
        continue;
      }

      // default behaviour state
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
}
