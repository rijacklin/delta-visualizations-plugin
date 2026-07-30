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
    [$studentcourseidssql, $studentcourseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'studentcourseid'
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
          AND c.id $studentcourseidssql
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
        COALESCE(feedback.id, 0 - submission.submission_id) AS record_id,
        submission.submission_id,
        submission.student_id,
        submission.course_id,
        submission.assignment_id,
        submission.attempt_number,
        grade.grade AS raw_grade,
        feedback.id AS feedback_id,
        feedback.commenttext AS feedback_text
      FROM latest_submissions submission
      LEFT JOIN {assign_grades} grade
        ON grade.assignment = submission.assignment_id
        AND grade.userid = submission.student_id
        AND grade.attemptnumber = submission.attempt_number
        AND grade.timemodified < submission.course_end
      LEFT JOIN {assignfeedback_comments} feedback
        ON feedback.assignment = submission.assignment_id
        AND feedback.grade = grade.id
      ORDER BY
        submission.course_id,
        submission.student_id,
        submission.assignment_id
    ";

    // access records from query using moodle DML and store on class instance
    $records = $DB->get_records_sql($sql, $studentcourseidsparams);
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
        AND c.id $studentcourseidssql
    ";
    $students = $DB->get_records_sql($studentsql, $studentcourseidsparams);

    $improvement_keywords = [
      'improve',
      'work on',
      'focus',
      'strengthen',
      'revise',
      'organization',
      'learning objects',
      'textbook',
      'materials',
      'review',
      'understanding',
      'explain',
      'detail',
      'specific',
      'example',
      'evidence',
      'analysis',
      'accuracy',
      'correct',
      'incorrect',
      'error',
      'mistake',
      'missing',
      'incomplete',
      'complete',
      'requirements',
      'instructions',
      'rubric',
      'criteria',
      'grammar',
      'spelling',
      'punctuation',
      'sentence structure',
      'word choice',
      'proofread',
      'edit',
      'citation',
      'reference',
      'formatting',
      'effort',
      'practice',
      'study',
      'prepare',
      'participate',
      'participation',
      'time management',
      'ask for help',
      'seek assistance',
      'next time',
      'next step',
      'try to',
      'you should',
      'consider',
      'recommend',
      'suggest',
      'make sure',
      'remember to',
      'continue working',
      'keep practicing',
    ];

    $messages = $records;

    $data = new stdClass();

    // all selected students begin as Not Required, including those without a submission
    foreach ($students as $student) {
      $messagekey = $student->student_id . ':' . $student->course_id;
      $data->{$messagekey} = ActivityBehaviour::NotRequired;
    }

    foreach ($messages as $message) {
      $messagekey = $message->student_id . ':' . $message->course_id;

      // skip feedback for assignments above low grade threshold
      if ($message->raw_grade === null || $message->raw_grade >= $params['gradethreshold']) {
        continue;
      }

      // skip exhibited result from one assignment so that later assignment feedback doesn't overwrite behaviour result
      if ($data->{$messagekey} === ActivityBehaviour::Exhibited) {
        continue;
      }

      // default behaviour
      $behaviour = ActivityBehaviour::NotExhibited;

      // iterate over words in feedback message
      foreach ($improvement_keywords as $keyword) {
        if (!empty($message->feedback_text) && stripos($message->feedback_text, $keyword) !== false) {
          // behaviour exhibited if improvement words present in feedback
          $behaviour = ActivityBehaviour::Exhibited;
          break;
        }
      }

      $data->{$messagekey} = $behaviour;
    }

    return $data;
  }
}
