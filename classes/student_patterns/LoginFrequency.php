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
  // static values for now
  protected $table = 'logstore_standard_log';

  public function query_behaviour_data()
  {
    global $DB;

    $sql = "
      SELECT
        log.userid,
        COUNT(*) as logincount
      FROM {logstore_standard_log} log
      WHERE log.eventname LIKE :eventname
      GROUP BY log.userid
      ORDER BY log.userid ASC
    ";

    $records = $DB->get_records_sql($sql, [
      'eventname' => '%user_loggedin%',
    ]);

    $this->records = $records;
  }

  public function create_chart()
  {
    // $chart = new \core\chart_bar();
    // $chart->set_labels(['userid', 'grader', 'timecreated', 'grade']);
    //
    // $series = new \core\chart_series('Grades', [400, 460, 1120, 540]);
    // $chart->add_series($series);
  }
}
