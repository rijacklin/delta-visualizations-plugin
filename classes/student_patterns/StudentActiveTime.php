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
 * Models student behaviour: Student Active Time
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
class StudentActiveTime extends StudentBehaviourPattern
{
  use BarChart;

  public function query_behaviour_data()
  {
    global $DB;

    $now = time();

    // TODO: Grab actual courseid from template
    $courseid = 3;

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
      WITH course_events AS (
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
        WHERE log.userid IS NOT null
          and log.courseid = :courseid
          -- Filter by hourly/daily/weekly
          and log.timecreated >= :starttime
          -- Filter both module-level actions (course modules) and core course view events
          and (
            (
            log.component like 'mod_%'
            and log.action in (
              'viewed',
              'submitted',
              'uploaded',
              'answered',
              'attempted',
              'started',
              'completed',
              'created',
              'updated',
              'commented',
              'searched',
              'downloaded'
            )
          )
          OR log.eventname in (
            '\core\event\course_viewed',
            '\core\event\mycourses_viewed',
            '\core\event\course_category_viewed'
          )
        )
      ), student_active_time as (
        select
          userid,
          courseid,
          contextinstanceid as coursemoduleid,
          component,
          eventname,
          next_event_time,
          timecreated,
          case
            when next_event_time is null then 0
            when next_event_time - timecreated <= 0 then 0
            -- threshold value to cap events where student didn't log out 
            when next_event_time - timecreated > :threshold1 then :threshold2
            else next_event_time - timecreated
          end as active_seconds,
          cast(to_timestamp(timecreated) as date) as date_stamp
        from course_events
      )
      select
        userid,
        courseid,
        SUM(active_seconds) as active_time_seconds
      FROM student_active_time
      GROUP by userid, courseid
      ORDER BY userid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      // TODO: Grab actual courseid from template
      'courseid' => $courseid,
      // 30 minutes
      'threshold1' => 1800,
      'threshold2' => 1800,
      'starttime' => $start_time
    ]);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];
    $active_time = [];

    foreach ($data as $student_id => $value) {
      $students[] =  intval($student_id);
      $active_time[] = (int)ceil($value->active_time_seconds * 0.0002777777777778);
    }

    // TODO: REMOVE LATER; TEMP TO SHOW VALUE CONTRAST ON CHART
    $students[] = intval(3);
    $active_time[] = intval(24);
    // END TODO

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Hours Active', $active_time);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Hours Active");
    $yaxis->set_min(0);
    $yaxis->set_max(24);
    $yaxis->set_stepsize(1);

    return $chart;
  }
}
