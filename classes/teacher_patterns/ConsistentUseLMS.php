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
 * Models teacher behaviour: Consistent Use of LMS
 *
 * Behaviour Pattern Description: An instructor is actively engaged in their
 * course when they are actively participating with the online courses they
 * teach. Teachers who consistently log in and contribute to their courses
 * exhibit this behaviour.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\teacher_patterns;

use block_delta_visualizations\local\TimeRange;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the ConsistentUseLMS teacher behaviour pattern.
 */
class ConsistentUseLMS extends TeacherBehaviourPattern
{
  public function query_behaviour_data(array $params)
  {
    global $DB, $USER;

    // use current timestamp in query to account for courses that have no end date (i.e., active courses)
    $query_time = time();

    // build parameterized SQL IN condition for selected courseids (required for Moodle DML API)
    [$courseidssql, $courseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'courseid'
    );

    $sql = "
      WITH course_teachers AS (
        SELECT DISTINCT
          ra.userid AS teacher_id,
          c.startdate AS course_start,
          CASE
            WHEN c.enddate = 0 THEN :querytime
            ELSE c.enddate
          END AS course_end,
          c.id AS course_id
        FROM {course} c
        JOIN {context} ctx
          ON ctx.contextlevel = 50
          AND ctx.instanceid = c.id
        JOIN {role_assignments} ra
          ON ra.contextid = ctx.id
        JOIN {role} r
          ON r.id = ra.roleid
        WHERE r.shortname IN ('editingteacher', 'teacher')
          AND c.id $courseidssql
      )
      SELECT
        ROW_NUMBER() OVER (
          ORDER BY
            teacher.course_id,
            teacher.teacher_id,
            log.timecreated,
            log.id
        ) AS record_id,
        teacher.teacher_id,
        teacher.course_id,
        teacher.course_start,
        teacher.course_end,
        log.id AS log_event_id,
        log.eventname AS event_name,
        log.component,
        log.action,
        log.target,
        log.crud,
        log.contextinstanceid AS context_instance_id,
        log.objectid AS object_id,
        log.timecreated AS event_time
      FROM course_teachers teacher
      LEFT JOIN {logstore_standard_log} log
        ON log.userid = teacher.teacher_id
        AND log.courseid = teacher.course_id
        AND log.timecreated >= teacher.course_start
        AND log.timecreated < teacher.course_end
      ORDER BY
        teacher.course_id,
        teacher.teacher_id,
        log.timecreated,
        log.id
    ";

    // access records from query using moodle DML and store on class instance
    $records = $DB->get_records_sql($sql, [
      'querytime' => $query_time,
    ] + $courseidsparams);
    $this->records = $records;

    $data = new stdClass();

    $teacher_course_logs = [];

    // group log events by teacher and course, including teachers without events.
    foreach ($records as $record) {
      $teacher_course_key = $record->teacher_id . ':' . $record->course_id;

      if (!isset($teacher_course_logs[$teacher_course_key])) {
        $course_start = (int)$record->course_start;
        $course_end = (int)$record->course_end;
        $course_duration = max(0, $course_end - $course_start);

        // use integer division to round up number of weeks in course; accouts for course errors by imposing minimum of at least 1 weekly period
        $weekly_period_count = max(
          1,
          intdiv($course_duration + WEEKSECS - 1, WEEKSECS)
        );

        // store teacher-course interaction logs
        $teacher_course_logs[$teacher_course_key] = [
          'course_id' => (int)$record->course_id,
          'course_start' => $course_start,
          'weekly_interactions' => array_fill(0, $weekly_period_count, 0),
        ];
      }

      // account for possible data errors by skipping events with no timestamp
      if ($record->event_time === null) {
        continue;
      }

      // associate events with week period
      $course_start = $teacher_course_logs[$teacher_course_key]['course_start'];
      $week_number = intdiv((int)$record->event_time - $course_start, WEEKSECS);
      $weekly_interactions = &$teacher_course_logs[$teacher_course_key]['weekly_interactions'];
      if ($week_number >= 0 && $week_number < count($weekly_interactions)) {
        // increment course interactions for week
        $weekly_interactions[$week_number]++;
      }

      // break reference to nested array to prevent mem leaks
      unset($weekly_interactions);
    }

    $teacher_course_behaviours = [];

    // iterate over teacher interaction logs
    foreach ($teacher_course_logs as $teacher_course_key => $course_logs) {
      $behaviour = ActivityBehaviour::Exhibited;

      foreach ($course_logs['weekly_interactions'] as $interaction_count) {
        // mark behaviour as NotExhibited if teacher doesn't sufficiently interact with course in a given week
        if ($interaction_count < $params['interactionthreshold']) {
          $behaviour = ActivityBehaviour::NotExhibited;
          break;
        }
      }

      // store teacher-course interaction behaviour status
      $teacher_course_behaviours[$teacher_course_key] = [
        'course_id' => $course_logs['course_id'],
        'behaviour' => $behaviour,
      ];
    }

    // map teacher's behaviour status to each course
    $course_behaviours = [];
    foreach ($teacher_course_behaviours as $teacher_course_behaviour) {
      $course_behaviours[$teacher_course_behaviour['course_id']] = $teacher_course_behaviour['behaviour'];
    }

    // grab all students from courses so that behaviour from teacher can be mapped to each
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

    // every selected student receives the same teacher behaviour status for their course.
    foreach ($students as $student) {
      $message_key = $student->student_id . ':' . $student->course_id;
      $data->{$message_key} = $course_behaviours[$student->course_id] ?? ActivityBehaviour::NotExhibited;
    }

    return $data;
  }
}
