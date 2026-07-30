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
      WITH course_students AS (
        SELECT DISTINCT
          ra.userid AS student_id,
          c.id AS course_id,
          c.startdate AS course_start,
          c.enddate AS course_end
        FROM {course} c
        JOIN {context} ctx
          ON ctx.contextlevel = 50
          AND ctx.instanceid = c.id
        JOIN {role_assignments} ra
          ON ra.contextid = ctx.id
        JOIN {role} r
          ON r.id = ra.roleid
        WHERE r.shortname = 'student'
          AND c.id $courseidssql
      ),
      latest_submissions AS (
        SELECT
          submission.id AS submission_id,
          submission.assignment AS assignment_id,
          submission.userid AS student_id,
          assign.course AS course_id,
          students.course_start,
          students.course_end,
          submission.attemptnumber AS attempt_number,
          submission.timemodified AS submission_date
        FROM {assign_submission} submission
        JOIN {assign} assign
          ON assign.id = submission.assignment
        JOIN course_students students
          ON students.student_id = submission.userid
          AND students.course_id = assign.course
        WHERE submission.latest = 1
          AND submission.status = 'submitted'
          AND submission.timecreated < students.course_end
          AND submission.timemodified >= students.course_start
          AND submission.timemodified < students.course_end
      )
      SELECT
        ROW_NUMBER() OVER (
          ORDER BY
            students.course_id,
            students.student_id,
            submission.assignment_id,
            feedback.id
        ) AS record_id,
        students.student_id,
        students.course_id,
        submission.submission_id,
        submission.assignment_id,
        submission.attempt_number,
        submission.submission_date,
        grade.grade AS raw_grade,
        grade.timemodified AS graded_date,
        feedback.id AS feedback_id,
        feedback.commenttext AS feedback_text
      FROM course_students students
      LEFT JOIN latest_submissions submission
        ON submission.student_id = students.student_id
        AND submission.course_id = students.course_id
      LEFT JOIN {assign_grades} grade
        ON grade.assignment = submission.assignment_id
        AND grade.userid = students.student_id
        AND grade.attemptnumber = submission.attempt_number
        AND grade.timemodified < students.course_end
      LEFT JOIN {assignfeedback_comments} feedback
        ON feedback.assignment = submission.assignment_id
        AND feedback.grade = grade.id
      ORDER BY
        students.course_id,
        students.student_id,
        submission.assignment_id
    ";

    // convert number of days for response to seconds
    $response_days_in_seconds = (int)$params['days'] * DAYSECS;

    // access records from query using moodle DML and store on class instance
    $records = $DB->get_records_sql($sql, $courseidsparams);
    $this->records = $records;

    // grab all students from courses to ensure NotRequired being properly applied
    $studentsql = "
      SELECT
        ra.id AS role_assignment_id,
        ra.userid AS student_id,
        c.id AS course_id
      FROM {course} c
      JOIN {context} ctx
        ON ctx.contextlevel = 50
        AND ctx.instanceid = c.id
      JOIN {role_assignments} ra
        ON ra.contextid = ctx.id
      JOIN {role} r
        ON r.id = ra.roleid
      WHERE r.shortname = 'student'
        AND c.id $courseidssql
    ";
    $students = $DB->get_records_sql($studentsql, $courseidsparams);

    $data = new stdClass();

    // all selected students begin as NotRequired
    foreach ($students as $student) {
      $message_key = $student->student_id . ':' . $student->course_id;
      $data->{$message_key} = ActivityBehaviour::NotRequired;
    }

    // iterate over assign feedback
    foreach ($records as $feedback) {
      // flag to track if assign feedback was on time (i.e., within institution time period)
      $feedback_on_time = false;

      // skip students without an assignment submission
      if ($feedback->submission_id === null) {
        continue;
      }

      $message_key = $feedback->student_id . ':' . $feedback->course_id;
      $submission_date = $feedback->submission_date;
      $graded_date = $feedback->graded_date;

      if (!empty($submission_date) && !empty($graded_date)) {
        if ($submission_date <= $graded_date && $graded_date <= ($submission_date + $response_days_in_seconds)) {
          $feedback_on_time = true;
        }
      }

      if (!$feedback_on_time) {
        // any late or missing feedback makes the overall behaviour NotExhibited
        $data->{$message_key} = ActivityBehaviour::NotExhibited;
      } else if ($data->{$message_key} !== ActivityBehaviour::NotExhibited) {
        // ensure a later on-time response cannot overwrite NotExhibited
        $data->{$message_key} = ActivityBehaviour::Exhibited;
      }
    }

    return $data;
  }
}
