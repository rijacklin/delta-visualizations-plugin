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
 * Models first student behaviour: Login Frequency
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
class LoginFrequency extends StudentBehaviourPattern
{
  use BarChart;
  use NotRelatedToCourse;

  // static values for now
  protected $table = 'logstore_standard_log';

  public function query_behaviour_data(array $params)
  {
    global $DB;

    $sql = "
      WITH student_role as (
        SELECT m.id as student_role_id
        FROM m_role m
        WHERE m.shortname = 'student'
      )
      SELECT
        log.userid,
        COUNT(*) as logincount
      FROM m_logstore_standard_log log
      JOIN m_role_assignments mra 
        ON mra.userid = log.userid
      join student_role sr
        on mra.roleid = sr.student_role_id  
      WHERE log.eventname LIKE :eventname
      GROUP BY log.userid
      ORDER BY log.userid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      'eventname' => '%user_loggedin%',
    ]);

    $this->records = $records;
  }

  public function create_bar_chart(): \core\chart_bar
  {
    $data = $this->records;

    $students = [];

    foreach ($data as $student_id => $value) {
      $students[] =  intval($student_id);
      $frequency[] = $value->logincount;
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Login Frequency', $frequency);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Login Frequency");
    $yaxis->set_stepsize(5);

    return $chart;
  }
}
