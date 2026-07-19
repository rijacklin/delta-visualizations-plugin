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
 * Behaviour Pattern Description: It is important for students to receive
 * assignment feedback quickly so that they can make any necessary adjustments
 * to their learning process in a course. Institutions typically codify a set
 * number of days for teachers to provide feedback on submitted assignments.
 * This behaviour is exhibited if teachers provide feedback on an assignment
 * that is within this time frame, calculated as the submission date plus the
 * number of days in the institution's policy. Teachers who do not provide
 * feedback within this time frame are failing to exhibit the learning behaviour.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\teacher_patterns;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the TimelyFeedback teacher behaviour pattern.
 */
class TimelyFeedback extends TeacherBehaviourPattern
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
            ag.userid AS student_id,
            ag.assignment as assignment_id,
            ag.grade,
            ag.timemodified AS graded_date
        FROM {assign_grades} ag
        JOIN {assign} assign
          ON assign.id = ag.assignment
        WHERE ag.grade < :gradethreshold
          AND assign.course $courseidssql
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
        JOIN {assignfeedback_comments} afcom
          ON afcom.assignment = assignment_id AND afcom.grade = ts.id
        JOIN {assign_submission} asub
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

    // convert number of days for response to seconds
    $response_days_in_seconds = (int)$params['days'] * $DAYSECS;

    // access records from query using moodle DML and store on class instance
    $records = $DB->get_records_sql($sql, [
      'gradethreshold' => $params['gradethreshold'],
    ] + $courseidsparams);
    $this->records = $records;

    $data = new stdClass();

    // iterate over feedback
    foreach ($records as $feedback) {
      // grab feedback properties
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
}
