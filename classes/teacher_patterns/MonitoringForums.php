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
 * Models teacher behaviour: Monitoring Forums
 *
 * Behaviour Pattern Description: Students often first look to the course
 * discussion forums when they need further guidance in the course. Teachers can
 * help clear confusion and avoid repeated messages by ensuring that issues
 * posted to the discussion forums have been resolved. This behaviour is being
 * exhibited if teachers are responding to such questions posted by students and
 * the students are following up with language that shows their issue has been
 * resolved. Teachers who fail to respond to such discussions, or fail to
 * sufficiently resolve a student's issue, are not exhibiting the behaviour.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\teacher_patterns;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the MonitoringForums teacher behaviour pattern.
 */
class MonitoringForums extends TeacherBehaviourPattern
{
  protected function query_behaviour_data(array $params)
  {
    global $DB;

    // build parameterized SQL IN condition for selected courseids (required for Moodle DML API)
    [$courseidssql, $courseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'courseid'
    );

    $sql = "
      WITH course_participants AS (
        SELECT
          ra.userid AS author_id,
          c.id AS course_id,
          c.startdate AS course_start,
          c.enddate AS course_end,
          MAX(
            CASE
              WHEN r.shortname = 'student' THEN 1
              ELSE 0
            END
          ) AS author_is_student,
          MAX(
            CASE
              WHEN r.shortname IN ('editingteacher', 'teacher') THEN 1
              ELSE 0
            END
          ) AS author_is_teacher
        FROM {course} c
        JOIN {context} ctx
          ON ctx.contextlevel = 50
          AND ctx.instanceid = c.id
        JOIN {role_assignments} ra
          ON ra.contextid = ctx.id
        JOIN {role} r
          ON r.id = ra.roleid
        WHERE r.shortname IN ('student', 'editingteacher', 'teacher')
          AND c.id $courseidssql
        GROUP BY
          ra.userid,
          c.id,
          c.startdate,
          c.enddate
      )
      SELECT
        post.id AS post_id,
        discussion.course AS course_id,
        post.parent AS parent_post_id,
        post.userid AS author_id,
        participant.author_is_student,
        participant.author_is_teacher,
        post.message AS post_message
      FROM {forum_posts} post
      JOIN {forum_discussions} discussion
        ON discussion.id = post.discussion
      JOIN course_participants participant
        ON participant.author_id = post.userid
        AND participant.course_id = discussion.course
      WHERE post.created >= participant.course_start
        AND post.created < participant.course_end
      ORDER BY
        discussion.course,
        discussion.id,
        post.created,
        post.id
    ";

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

    $response_keywords = [
      'thanks',
      'thank you',
      'appreciate',
      'appreciated',
      'helpful',
      'that worked',
      'works now',
      'fixed it',
      'problem solved',
      'issue resolved',
      'makes sense',
      'clears it up',
      'answers my question',
      'all good now',
      'all set'
    ];

    $data = new stdClass();

    // all selected students begin as Not Required, including those without a forum post.
    foreach ($students as $student) {
      $message_key = $student->student_id . ':' . $student->course_id;
      $data->{$message_key} = ActivityBehaviour::NotRequired;
    }

    // Map each student post to its author and require monitoring for students who post.
    $student_post_authors = [];
    foreach ($records as $post) {
      if ((int)$post->author_is_student !== 1) {
        continue;
      }

      $post_key = $post->course_id . ':' . $post->post_id;
      $message_key = $post->author_id . ':' . $post->course_id;
      $student_post_authors[$post_key] = $post->author_id;
      $data->{$message_key} = ActivityBehaviour::NotExhibited;
    }

    // Map teacher replies to the student whose post they directly answer.
    $teacher_response_students = [];
    foreach ($records as $response) {
      if ((int)$response->author_is_teacher !== 1) {
        continue;
      }

      $parent_key = $response->course_id . ':' . $response->parent_post_id;
      if (!isset($student_post_authors[$parent_key])) {
        continue;
      }

      $response_key = $response->course_id . ':' . $response->post_id;
      $teacher_response_students[$response_key] = $student_post_authors[$parent_key];
    }

    // Look for a resolution follow-up from the same student after a teacher reply.
    foreach ($records as $followup) {
      if ((int)$followup->author_is_student !== 1) {
        continue;
      }

      $parent_key = $followup->course_id . ':' . $followup->parent_post_id;
      if (!isset($teacher_response_students[$parent_key])) {
        continue;
      }

      $student_id = $teacher_response_students[$parent_key];
      if ((int)$followup->author_id !== (int)$student_id) {
        continue;
      }

      $followup_text = $followup->post_message ?? '';
      foreach ($response_keywords as $keyword) {
        if ($followup_text !== '' && stripos($followup_text, $keyword) !== false) {
          $message_key = $student_id . ':' . $followup->course_id;
          $data->{$message_key} = ActivityBehaviour::Exhibited;
          break;
        }
      }
    }

    return $data;
  }
}
