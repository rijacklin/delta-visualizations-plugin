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
 * Models a behaviour pattern
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates a renderer for the block_delta_visualizations
 *
 */
class BehaviourPattern
{
  private $name;
  private $courseid;
  private $table;
  private $params;
  private $records;
  private $chart;

  public function __construct(string $name)
  {
    $this->name = $name;
  }

  public function set_table(string $table)
  {
    $this->table = $table;
  }

  public function add_params(array $params)
  {
    $this->params = $params;
  }

  public function query_grades()
  {
    global $DB;

    $records = $DB->get_records_select(
      'assign_grades',
      'grade' < ':maxgrade',
      ['maxgrade' => 70],
      'userid ASC',
      'id, userid, grader, timecreated, grade'
    );

    $this->records = $records;
  }

  public function get_records()
  {
    return $this->records;
  }
}
