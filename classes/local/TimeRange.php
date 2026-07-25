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
 * Represent reporting periods and calculates their starting timestamps.
 */
enum TimeRange: string
{
  case HOURLY = 'hourly';
  case DAILY = 'daily';
  case WEEKLY = 'weekly';
  case MONTHLY = 'monthly';
  case YEARLY = 'yearly';

  /**
   * Calculate the start of this reporting period from an end timestamp.
   *
   * Month and year ranges use calendar arithmetic in the user's timezone,
   * rather than treating a month or year as a fixed number of seconds.
   *
   * @param int $endtime End of the reporting period.
   * @return int Start of the reporting period.
   */
  public function start_time(int $endtime): int
  {
    if ($this === self::MONTHLY || $this === self::YEARLY) {
      $period = $this === self::MONTHLY ? '-1 month' : '-1 year';

      return (new \DateTimeImmutable("@{$endtime}"))->setTimezone(\core_date::get_user_timezone_object())->modify($period)->getTimestamp();
    }

    $seconds = match ($this) {
      self::HOURLY => HOURSECS,
      self::DAILY => DAYSECS,
      self::WEEKLY => WEEKSECS,
    };

    return $endtime - $seconds;
  }
}
