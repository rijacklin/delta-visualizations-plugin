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
 * Base for modeling teacher behaviour patterns
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\teacher_patterns;

use block_delta_visualizations\local\ChartBehaviour;
use stdClass;

defined('MOODLE_INTERNAL') || die();

// represents teacher activity behaviour state
enum ActivityBehaviour
{
  case NotExhibited;
  case Exhibited;
  case NotRequired;

  // converts labels to their string representations
  public function label(): string
  {
    return match ($this) {
      self::NotExhibited => "Not Exhibited",
      self::Exhibited => "Exhibited",
      self::NotRequired => "Not Required",
    };
  }
}

/**
 * Base class for all teacher behaviour patterns, which implement the
 * ChartBehaviour interface.
 */
abstract class TeacherBehaviourPattern implements ChartBehaviour
{
  protected $records = [];
  protected $chart;

  // behaviour that teacher behaviour subclasses must define
  abstract protected function query_behaviour_data(array $params);

  /**
   * Creates a pie chart visualization for a teacher behaviour pattern.
   *
   * @param stdClass $activity_behaviour Mapping of records to activity
   * behaviour states
   * @return void
   */
  protected function create_pie_chart(stdClass $activity_behaviour): void
  {
    // initialize behaviour states
    $exhibited = 0;
    $not_exhibited = 0;
    $not_required = 0;

    // iterate over behaviour states and count number of each based on data records
    foreach ($activity_behaviour as $state) {
      switch ($state) {
        case ActivityBehaviour::Exhibited:
          $exhibited++;
          break;
        case ActivityBehaviour::NotExhibited:
          $not_exhibited++;
          break;
        case ActivityBehaviour::NotRequired:
          $not_required++;
          break;
      }
    }

    // create an instane of a pie chart using Moodle chart API
    $chart = new \core\chart_pie();

    // set the chart labels
    $chart->set_labels([
      ActivityBehaviour::Exhibited->label(),
      ActivityBehaviour::NotExhibited->label(),
      ActivityBehaviour::NotRequired->label(),
    ]);

    // create a chart series object and append it to the chart
    $series_behaviour = new \core\chart_series('Behaviour Exhibited', [
      $exhibited,
      $not_exhibited,
      $not_required,
    ]);
    $chart->add_series($series_behaviour);

    // store the chart on the behaviour pattern instance
    $this->chart = $chart;
  }

  /**
   * Generates a behaviour pattern chart based on the provided parameters.
   *
   * @param array $params Params defining behaviour pattern chart
   * @return \core\chart_base Optionally returns a chart or null
   */
  public function generate_chart(array $params): ?\core\chart_base
  {
    $this->records = [];
    $this->chart = null;

    $behaviour_data = $this->query_behaviour_data($params);
    $this->create_pie_chart($behaviour_data);

    return $this->chart instanceof \core\chart_base ? $this->chart : null;
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
