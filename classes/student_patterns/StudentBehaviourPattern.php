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
 * Base for modeling student behaviour patterns
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\student_patterns;

defined('MOODLE_INTERNAL') || die();

// represents teacher activity behaviour state
enum TimeRange: Int
{
  case HOURLY = 1;
  case DAILY = 2;
  case WEEKLY = 3;

  public function label(): string
  {
    return match ($this) {
      self::HOURLY => "Hourly",
      self::DAILY => "Daily",
      self::WEEKLY => "Weekly",
    };
  }
}

trait NotRelatedToCourse
{
  public function generate_chart(array $params)
  {
    $behaviour_data = $this->query_behaviour_data($params);
    return $this->create_bar_chart($behaviour_data);
  }
}

trait BarChart
{
  abstract protected function create_bar_chart();
}

trait PieChart
{
  abstract protected function create_pie_chart();
}

/**
 * Creates a renderer for the block_delta_visualizations
 *
 */
abstract class StudentBehaviourPattern
{
  protected $table;

  // TODO: Don't hardcode this
  protected $time_range = TimeRange::WEEKLY;
  protected $records = [];

  abstract protected function query_behaviour_data(array $params);

  public function generate_behaviour_chart(array $params)
  {
    $chart = "";

    $behaviour_data = $this->query_behaviour_data($params);

    if (!empty($params['courseids']) && $params['chart_type']) {
      switch ($params['chart_type']) {
        case "\core\chart_bar":
          $chart = $this->create_bar_chart($behaviour_data);
          break;
        case "\core\chart_line":
          $chart = $this->create_line_chart($behaviour_data);
          break;
      }
    }

    return $chart;
  }

  public function get_records()
  {
    return $this->records;
  }

  public function get_time_range()
  {
    return $this->time_range;
  }
}
