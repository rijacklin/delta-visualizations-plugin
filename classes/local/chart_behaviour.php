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
 * Abstracts behaviour pattern chart behaviour to an Interface
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Defines a contract for behaviour pattern subclasses that expose their data records and generate visualization charts.
 */
interface chart_behaviour
{
  /**
   * Generate a chart for a behaviour pattern based on parameters
   *
   * @param array $params Query, classification, and chart parameters.
   * @return \core\chart_base|null The chart (or null if chart cannot be generated).
   */
  public function generate_chart(array $params): ?\core\chart_base;

  /**
   * Return records used to generate behaviour chart.
   *
   * @return array
   */
  public function get_records(): array;
}
