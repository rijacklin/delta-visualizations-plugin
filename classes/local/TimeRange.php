<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Enumerates hourly/daily/weekly reporting periods for visualization charts.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Represent time range periods and converts to millisecond representations.
 */
enum TimeRange: string
{
  case HOURLY = 'hourly';
  case DAILY = 'daily';
  case WEEKLY = 'weekly';

  public function seconds(): int
  {
    return match ($this) {
      self::HOURLY => HOURSECS,
      self::DAILY => DAYSECS,
      self::WEEKLY => WEEKSECS,
    };
  }
}
