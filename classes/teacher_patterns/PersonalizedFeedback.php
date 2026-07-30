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

    // $sql = "
    //   WITH target_students AS (
    //     select
    //         ag.id,
    //         ag.userid AS studentid,
    //         ag.assignment,
    //         ag.grade,
    //         ag.timemodified AS gradeddate
    //     FROM {assign_grades} ag
    //     JOIN {assign} assign
    //       ON assign.id = ag.assignment
    //     WHERE ag.grade < :gradethreshold
    //       AND assign.course $courseidssql
    //     ORDER BY ag.userid ASC
    //   ),
    //   targeted_feedback AS (
    //     select 
    //       afcom.id as feedback_id,
    //       ts.studentid as student_id,
    //       afcom.commenttext as feedback_text
    //     FROM target_students ts
    //     join {assignfeedback_comments} afcom
    //       on afcom.assignment = ts.assignment AND afcom.grade = ts.id
    //   )
    //   select
    //     feedback_id,
    //     student_id,
    //     feedback_text
    //   FROM targeted_feedback
    //   ORDER BY
    //     feedback_id ASC,
    //     student_id ASC;
    // ";

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
          submission.attemptnumber AS attempt_number
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
          grade.grade AS raw_grade,
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

    // access records from query using moodle DML and store on clas instance
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

    // store the configured feedback goal as percentage
    $feedback_goal = $params['feedbackgoal'] / 100;
    $messages = $records;
    $data = new stdClass();

    // all selected students begin as NotRequired
    foreach ($students as $student) {
      $message_key = $student->student_id . ':' . $student->course_id;
      $data->{$message_key} = ActivityBehaviour::NotRequired;
    }

    // group feedback responses by course and assignment for comparison
    $assignment_messages = [];

    foreach ($messages as $message) {
      // skip students without an assignment submission
      if ($message->submission_id === null) {
        continue;
      }

      // store the assign feedback message
      $assignment_key = $message->course_id . ':' . $message->assignment_id;
      $assignment_messages[$assignment_key][] = $message;
    }

    foreach ($messages as $message) {
      // handle potential bad data by skipping messages not tied to assign submission
      if ($message->submission_id === null) {
        continue;
      }

      $message_key = $message->student_id . ':' . $message->course_id;
      $assignment_key = $message->course_id . ':' . $message->assignment_id;
      $student_key = (string)$message->student_id;
      $feedback_to_score_words = explode(' ', $message->feedback_text);
      $feedback_length = count($feedback_to_score_words);
      $feedback_to_compare_set = [];
      $max_similar_count = 0;

      // compare response with every other response for assignment
      foreach ($assignment_messages[$assignment_key] as $feedback_to_compare) {
        // ignore same message being compared
        if ($feedback_to_compare->record_id === $message->record_id) {
          continue;
        }

        $feedback_to_compare_text = $feedback_to_compare->feedback_text;
        $feedback_to_compare_words = explode(' ', $feedback_to_compare_text);
        $feedback_to_compare_set = array_fill_keys($feedback_to_compare_words, true);
        $similar_count = 0;

        // count each word in the scored feedback that appears in the comparison feedback
        foreach ($feedback_to_score_words as $word_to_score) {
          if (isset($feedback_to_compare_set[$word_to_score])) {
            $similar_count++;
          }
        }

        // keep track of feedback with highest similarity count for score
        if ($similar_count > $max_similar_count) {
          $max_similar_count = $similar_count;
        }
      }

      // calculate overall uniqueness percentage
      $feedback_percent = $feedback_length > 0 ? $max_similar_count / $feedback_length : 0.0;
      $uniqueness_percent = $feedback_length > 0 ? 1.0 - $feedback_percent : 0.0;

      if ($uniqueness_percent < $feedback_goal) {
        // Any insufficiently unique message makes the overall result Not Exhibited.
        $data->{$message_key} = ActivityBehaviour::NotExhibited;
      } elseif ($data->{$message_key} !== ActivityBehaviour::NotExhibited) {
        // A later sufficiently unique message cannot overwrite Not Exhibited.
        $data->{$message_key} = ActivityBehaviour::Exhibited;
      }
    }

    return $data;
  }
}
