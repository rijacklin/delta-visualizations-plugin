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
 * Models student behaviour: Time Spent on Assignments
 *
 * Behaviour Pattern Description: Time duration of student first accessing the
 * assignment learning object and submitting the assignment.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

/**
 * Models an instance of the TimeSpentAssignments student behaviour pattern.
 */
class TimeSpentAssignments extends StudentBehaviourPattern
{
  public function query_behaviour_data(array $params)
  {
    global $DB;

    $now = time();

    if (empty($params['courseids'])) {
      return [];
    }

    [$courseidssql, $courseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'courseid'
    );

    $start_time = $this->get_start_time($params, $now);
    $sessioncap = $params['sessioncap'] ?? get_config(
      'block_delta_visualizations',
      'sessioncap'
    );
    if (!is_numeric($sessioncap)) {
      $sessioncap = 30 * MINSECS;
    }
    $sessioncap = max(MINSECS, min(DAYSECS, (int)$sessioncap));

    $sql = "
      WITH ordered_course_logs AS (
        SELECT
          log.id,
          log.userid,
          log.courseid,
          log.component,
          log.action,
          log.timecreated,
          LEAD(log.timecreated) OVER (
            PARTITION BY log.userid, log.courseid
            ORDER BY log.timecreated, log.id
          ) AS next_event_time
        FROM {logstore_standard_log} log
        JOIN {role_assignments} ra
          ON ra.userid = log.userid
        JOIN {role} r
          ON r.id = ra.roleid
          AND r.shortname = 'student'
        JOIN {context} ctx
          ON ctx.id = ra.contextid
          AND ctx.contextlevel = :coursecontext
          AND ctx.instanceid = log.courseid
        WHERE log.userid IS NOT NULL
          AND log.courseid $courseidssql
          AND log.timecreated >= :starttime
      ),
      assignment_view_durations AS (
        SELECT
          userid AS studentid,
          CASE
            WHEN next_event_time IS NULL THEN 0
            WHEN next_event_time <= timecreated THEN 0
            WHEN next_event_time - timecreated > :sessioncaplimit THEN :sessioncapvalue
            ELSE next_event_time - timecreated
          END AS time_spent_on_assignment
        FROM ordered_course_logs
        WHERE component = 'mod_assign'
          AND action = 'viewed'
      )
      SELECT
        studentid,
        SUM(time_spent_on_assignment) AS total_time_spent
      FROM assignment_view_durations
      GROUP BY studentid
      ORDER BY studentid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      'coursecontext' => CONTEXT_COURSE,
      'sessioncaplimit' => $sessioncap,
      'sessioncapvalue' => $sessioncap,
      'starttime' => $start_time,
    ] + $courseidsparams);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];
    $assign_view_time = [];

    foreach ($data as $student_id => $value) {
      $students[] =  intval($student_id);
      $assign_view_time[] = (int)ceil($value->total_time_spent / HOURSECS);
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Hours Spent On Assignments', $assign_view_time);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Hours Spent On Assignments");
    $yaxis->set_stepsize(10);

    return $chart;
  }
}
