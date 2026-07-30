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
 * A
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides defaults and bounded values for numeric plugin settings.
 */
final class behaviour_config
{
  private const RULES = [
    'gradethreshold' => ['default' => 70.0, 'min' => 0.0, 'max' => 100.0],
    'feedbackgoal' => ['default' => 60.0, 'min' => 0.0, 'max' => 100.0],
    'interactionthreshold' => ['default' => 5.0, 'min' => 0.0, 'max' => PHP_INT_MAX],
    'timelyfeedbackdays' => ['default' => 8, 'min' => 0, 'max' => 31],
    'sessioncap' => ['default' => 30 * MINSECS, 'min' => MINSECS, 'max' => DAYSECS],
  ];

  /**
   * Return a setting's configured value or its safe default.
   *
   * @param string $name Setting name.
   * @return int|float
   */
  public static function get(string $name): int|float
  {
    $rule = self::RULES[$name] ?? throw new \coding_exception("Unknown behaviour setting: {$name}");
    $value = get_config('block_delta_visualizations', $name);

    if ($value === false || !is_numeric($value)) {
      return $rule['default'];
    }

    $value = is_int($rule['default']) ? (int)$value : (float)$value;
    return $value >= $rule['min'] && $value <= $rule['max'] ? $value : $rule['default'];
  }

  /**
   * Return a setting's default value.
   *
   * @param string $name Setting name.
   * @return int|float
   */
  public static function default(string $name): int|float
  {
    return self::RULES[$name]['default'] ?? throw new \coding_exception("Unknown behaviour setting: {$name}");
  }
}
