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

use block_delta_visualizations\local\chart_behaviour;
use block_delta_visualizations\local\TimeRange;

defined('MOODLE_INTERNAL') || die();

// #TOOO: Investigate what behaviour patterns don't require a course
trait NotRelatedToCourse
{
  protected function requires_course(): bool
  {
    return false;
  }
}

/**
 * Creates a renderer for the block_delta_visualizations
 *
 */
abstract class StudentBehaviourPattern implements chart_behaviour
{
  protected $records = [];

  abstract protected function query_behaviour_data(array $params);

  abstract protected function create_bar_chart(): \core\chart_bar;

  // #TOOO: Investigate what behaviour patterns don't require a course
  protected function requires_course(): bool
  {
    return true;
  }

  /**
   * Calculate the inclusive reporting-window start from a validated time range.
   *
   * @param array $params Behaviour parameters.
   * @param int|null $endtime End of the reporting window, defaulting to now.
   * @return int Unix timestamp.
   */
  protected function get_start_time(array $params, ?int $endtime = null): int
  {
    $timerange = TimeRange::tryFrom((string)($params['time_range'] ?? TimeRange::WEEKLY->value));

    if ($timerange === null) {
      throw new \invalid_parameter_exception('Unsupported time range');
    }

    return ($endtime ?? time()) - $timerange->seconds();
  }

  /**
   * Query the behaviour data and generate the requested chart type.
   *
   * @param array $params Behaviour and chart parameters.
   * @return \core\chart_base|null
   */
  public function generate_chart(array $params): ?\core\chart_base
  {
    $this->records = [];

    if ($this->requires_course() && empty($params['courseids'])) {
      return null;
    }

    $this->query_behaviour_data($params);

    switch ($params['chart_type'] ?? '\core\chart_bar') {
      case '\core\chart_bar':
        return $this->create_bar_chart();

      case '\core\chart_line':
        return $this->create_line_chart();

      default:
        throw new \invalid_parameter_exception('Unsupported chart type');
    }
  }

  /**
   * Provides public class access to behaviour pattern's records
   *
   * @return array Database records associated with behaviour pattern instance
   */
  public function get_records(): array
  {
    return $this->records;
  }
}
