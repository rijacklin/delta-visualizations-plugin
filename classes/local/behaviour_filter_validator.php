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
 * Provides validation for behaviour pattern filters.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\local;

defined('MOODLE_INTERNAL') || die();

// validates behaviour pattern filters
final class behaviour_filter_validator
{
  /**
   * Validates filters for behaviour pattern
   *
   * @param string $behaviourid Behaviour pattern identifier.
   * @param array $submitted Submited filter form fields.
   * @return array Validated query parameters.
   */
  public static function validate(string $behaviourid, array $submitted): array
  {
    $definition = behaviour_registry::get($behaviourid);
    $controls = $definition['controls'];
    $defaults = behaviour_registry::filter_defaults($behaviourid);
    $unexpected = array_diff_key($submitted, $controls);

    $validated = [];

    foreach ($controls as $name => $schema) {
      if (!array_key_exists($name, $submitted)) {
        if (!array_key_exists($name, $defaults)) {
          throw new \invalid_parameter_exception("Missing {$name} filter");
        }

        $submitted[$name] = $defaults[$name];
      }

      $value = $submitted[$name];

      if (!is_scalar($value)) {
        throw new \invalid_parameter_exception("Invalid {$name} filter");
      }

      $parameter = $schema['parameter'] ?? $name;
      $validated[$parameter] = self::validate_value($name, (string)$value, $schema);
    }

    if (array_key_exists('starttime', $validated) && array_key_exists('endtime', $validated) && $validated['starttime'] > $validated['endtime']) {
      throw new \invalid_paramter_exception('Start date must not be after end date');
    }

    return $validated;
  }

  /**
   * Validate one value according to its registered control type.
   *
   * @param string $name Form field name.
   * @param string $value Submitted value.
   * @param array $schema Registered control schema.
   * @return int|float|string
   */
  private static function validate_value(string $name, string $value, array $schema)
  {
    switch ($schema['type']) {
      case 'select':
        return self::validate_select($name, $value, $schema);
      case 'date':
        return self::validate_date($name, $value, $schema);
      case 'number':
        return self::validate_number($name, $value, $schema);
      default:
        throw new \coding_exception("Unsupported filter control type for {$name}");
    }
  }

  /**
   * Validate a value against a select's allow-listed options.
   *
   * @param string $name Form field name.
   * @param string $value Submitted value.
   * @param array $schema Registered control schema.
   * @return int|string
   */
  private static function validate_select(string $name, string $value, array $schema)
  {
    foreach ($schema['options'] as $option) {
      if ((string)$option === $value) {
        return ($schema['datatype'] ?? 'string') === 'int' ? (int)$option : (string)$option;
      }
    }
  }

  /**
   * Validate and convert a date in the user's Moodle timezone.
   *
   * @param string $name Form field name.
   * @param string $value Submitted Y-m-d value.
   * @param array $schema Registered control schema.
   * @return int Unix timestamp.
   */
  private static function validate_date(string $name, string $value, array $schema): int
  {
    $date = \DateTimeImmutable::createFromFormat(
      '!Y-m-d',
      $value,
      \core_date::get_user_timezone_object()
    );
    $errors = \DateTimeImmutable::getLastErrors();

    if (
      $date === false ||
      ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
      $date->format('Y-m-d') !== $value
    ) {
      throw new \invalid_parameter_exception("Invalid {$name} filter");
    }

    if (!empty($schema['endofday'])) {
      return $date->modify('+1 day')->getTimestamp() - 1;
    }

    return $date->getTimestamp();
  }

  /**
   * Validate a bounded numeric input.
   *
   * @param string $name Form field name.
   * @param string $value Submitted value.
   * @param array $schema Registered control schema.
   * @return int|float
   */
  private static function validate_number(string $name, string $value, array $schema)
  {
    if (!is_numeric($value)) {
      throw new \invalid_parameter_exception("Invalid {$name} filter");
    }

    $number = ($schema['datatype'] ?? 'float') === 'int' ? (int)$value : (float)$value;

    if (
      (array_key_exists('min', $schema) && $number < $schema['min']) ||
      (array_key_exists('max', $schema) && $number > $schema['max'])
    ) {
      throw new \invalid_parameter_exception("Invalid {$name} filter");
    }

    return $number;
  }
}
