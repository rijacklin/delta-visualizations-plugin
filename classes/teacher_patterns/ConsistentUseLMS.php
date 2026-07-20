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
 * Behaviour Pattern Description: #TODO
 *
 * This behaviour is exhibited when the teacher interacts with the course a given threshold number of times each week.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\teacher_patterns;

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

    $course_start = (int)$params['starttime'];
    $course_end = (int)$params['endtime'];
    $num_weeks = max(1 / WEEKSECS, ($course_end - $course_start + 1) / WEEKSECS);

    $teacher_id = $USER->id;

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
      WITH teacher_course_logs AS (
        SELECT
          l.id,
          l.userid,
          l.courseid,
          l.component,
          l.action,
          l.target,
          l.crud,
          l.timecreated
        FROM {logstore_standard_log} l
        WHERE l.courseid $courseidssql
          AND l.userid = :userid
          AND l.timecreated >= :starttime
          AND l.timecreated <= :endtime
      )
      SELECT
        l.userid,
        COUNT(l.id) AS total_teacher_interactions
      FROM teacher_course_logs l 
      GROUP BY l.userid
      ORDER BY total_teacher_interactions DESC;
    ";

    // access records from query using moodle DML and store on class instance
    $records = $DB->get_records_sql($sql, [
      'userid' => $teacher_id,
      'starttime' => $course_start,
      'endtime' => $course_end
    ] + $courseidsparams);
    $this->records = $records;

    $data = new stdClass();

    // iterate over feedback
    foreach ($records as $interactions) {
      // Grab feedback properties
      $interactions_per_unit = $interactions->total_teacher_interactions / $num_weeks;

      // check to see if feedback sufficiently personalized/unique
      if ($interactions_per_unit > $params['engagementthreshold']) {
        $data->{$teacher_id} = ActivityBehaviour::Exhibited;
      } else {
        $data->{$teacher_id} = ActivityBehaviour::NotExhibited;
      }
    }

    return $data;
  }
}
