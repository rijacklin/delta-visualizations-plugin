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
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates a renderer for the block_delta_visualizations
 *
 */
class TimeSpentAssignments extends StudentBehaviourPattern
{
  use BarChart;

  public function query_behaviour_data()
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

    switch ($this->time_range) {
      case TimeRange::HOURLY:
        $start_time = $now - HOURSECS;
        break;
      case TimeRange::DAILY:
        $start_time = $now - DAYSECS;
        break;
      case TimeRange::WEEKLY:
        $start_time = $now - WEEKSECS;
        break;
      default:
        $start_time = 0;
        break;
    }

    $sql = "
      WITH student_role as (
        SELECT m.id as student_role_id
        FROM m_role m
        WHERE m.shortname = 'student'
      ),
      ordered_view_logs AS (
        SELECT
          log.id,
          log.userid,
          log.courseid,
          log.contextinstanceid,
          log.eventname,
          log.component,
          log.action,
          log.timecreated,
          LEAD(log.timecreated) OVER (
            PARTITION BY log.userid, log.courseid
            ORDER BY log.timecreated, log.id
          ) AS next_event_time
        FROM {logstore_standard_log} log
        JOIN m_role_assignments mra
        	ON mra.userid = log.userid
        JOIN student_role sr
        	ON mra.roleid = sr.student_role_id
        WHERE log.userid IS NOT NULL
          AND log.courseid $courseidssql
      ),
      assignment_views AS (
        SELECT
          ovl.userid,
          ovl.courseid,
          ovl.contextinstanceid AS coursemoduleid,
          cm.instance AS assignmentid,
          ovl.timecreated AS view_time
        FROM ordered_view_logs ovl
        JOIN {course_modules} cm
          ON cm.id = ovl.contextinstanceid
        JOIN {modules} m
          ON m.id = cm.module
        AND m.name = 'assign'
        WHERE ovl.component = 'mod_assign'
          AND ovl.action = 'viewed'
      ),
      first_assignment_view AS (
        SELECT
          userid,
          courseid,
          coursemoduleid,
          assignmentid,
          MIN(view_time) AS first_view_time
        FROM assignment_views
        GROUP BY userid, courseid, coursemoduleid, assignmentid
      ),
      duration_per_assignment AS (
        SELECT
          fav.userid as studentid,
          fav.assignmentid as assignmentid,
          fav.coursemoduleid as moduleid,
          MIN(sub.timemodified) - fav.first_view_time AS time_spent_on_assignment
        FROM first_assignment_view fav
        JOIN {assign_submission} sub
          ON sub.userid = fav.userid
          AND sub.assignment = fav.assignmentid
          AND sub.timemodified >= fav.first_view_time
        WHERE sub.latest = 1
        GROUP BY fav.userid, fav.assignmentid, fav.coursemoduleid, fav.first_view_time
      )
      SELECT
        studentid,
        SUM(time_spent_on_assignment) as total_time_spent
      FROM duration_per_assignment
      GROUP BY studentid
      ORDER BY studentid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      'starttime' => $start_time
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
      $assign_view_time[] = (int)ceil($value->total_time_spent * 0.0002777777777778);
    }

    // TODO: REMOVE LATER; TEMP TO SHOW VALUE CONTRAST ON CHART
    $students[] = intval(3);
    $assign_view_time[] = intval(12);
    // END TODO

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
