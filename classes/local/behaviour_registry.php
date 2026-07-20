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
 * A central location for defining and constructing instances of behaviour
 * pattern subclasses.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\local;

// teacher-behaviour pattern classes
use block_delta_visualizations\student_patterns\ForumPostingConsistency;
use block_delta_visualizations\teacher_patterns\CoursePerformanceFeedback;
use block_delta_visualizations\teacher_patterns\PersonalizedFeedback;
use block_delta_visualizations\teacher_patterns\TimelyFeedback;
use block_delta_visualizations\teacher_patterns\MonitoringForums;

// student-behaviour pattern classes
use block_delta_visualizations\student_patterns\ForumPostingFrequency;
use block_delta_visualizations\student_patterns\LOAccessFrequency;
use block_delta_visualizations\student_patterns\LoginFrequency;
use block_delta_visualizations\student_patterns\StudentActiveTime;
use block_delta_visualizations\student_patterns\StudentInactiveTime;
use block_delta_visualizations\student_patterns\TimeSpentForums;
use block_delta_visualizations\student_patterns\TimeSpentLO;
use block_delta_visualizations\student_patterns\TimeSpentAssignments;
use block_delta_visualizations\student_patterns\NumberForumsViewed;
use block_delta_visualizations\teacher_patterns\ConsistentUseLMS;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides a factory for constructing behaviour pattern subclasses
 */
final class behaviour_registry
{
  public const GROUP_TEACHER = 'teacher';
  public const GROUP_STUDENT = 'student';

  private const DEFAULT_GRADE_THRESHOLD = 70.0;
  private const DEFAULT_FEEDBACK_GOAL = 60.0;
  private const DEFAULT_ENGAGEMENT_THRESHOLD = 5.0;
  private const MIN_PERCENTAGE = 0.0;
  private const MAX_PERCENTAGE = 100.0;
  private const MIN_ENGAGEMENT_THRESHOLD = 0.0;
  private const MAX_ENGAGEMENT_THRESHOLD = PHP_INT_MAX;

  /**
   * Returns all behaviour definitions
   *
   * @param string|null $group Optional teacher or student group filter
   * @return array
   */
  public static function all(?string $group = null): array
  {
    $definitions = self::definitions();

    return array_filter(
      $definitions,
      static fn(array $definition): bool => $definition['group'] === $group
    );
  }

  /**
   * Returns single behaviour definition
   *
   * @param string $id Behaviour identifier
   * @return array
   */
  public static function get(string $id): array
  {
    $definitions = self::definitions();
    return $definitions[$id];
  }

  /**
   * Instantiate behaviour from registry
   *
   * @param string $id Behaviour identifier
   * @return chart_behaviour
   */
  public static function create(string $id): chart_behaviour
  {
    $definition = self::get($id);

    $classname = $definition['class'];
    $behaviour = new $classname();

    return $behaviour;
  }

  /**
   * Return a fresh copy of a behaviour's default parameters.
   *
   * @param string $id Stable behaviour identifier.
   * @return array
   */
  public static function defaults(string $id): array
  {
    return self::get($id)['defaults'];
  }

  /**
   * Return the initial form value for every registered control.
   *
   * A control normally takes its default from the behaviour parameters. Date controls can
   * instead declare a relative default so their value is recalculated in the user's timezone.
   *
   * @param string $id Stable behaviour identifier.
   * @return array Form field names mapped to initial values.
   */
  public static function filter_defaults(string $id): array
  {
    $definition = self::get($id);
    $values = [];

    foreach ($definition['controls'] as $name => $control) {
      if (array_key_exists($name, $definition['defaults'])) {
        $values[$name] = $definition['defaults'][$name];
        continue;
      }

      if ($control['type'] === 'date' && array_key_exists('defaultrelative', $control)) {
        $date = new \DateTimeImmutable('today', \core_date::get_user_timezone_object());
        if ($control['defaultrelative'] !== 'today') {
          $date = $date->modify($control['defaultrelative']);
        }
        $values[$name] = $date->format('Y-m-d');
        continue;
      }

      throw new \coding_exception("Registered control {$name} for {$id} has no default value");
    }

    return $values;
  }

  /**
   * Build template data for a behaviour's registered controls.
   *
   * @param string $id Stable behaviour identifier.
   * @param array $values Values that override the registered defaults.
   * @return array
   */
  public static function controls_for_template(string $id, array $values = []): array
  {
    $definition = self::get($id);
    $values = array_replace(self::filter_defaults($id), $values);
    $controls = [];

    foreach ($definition['controls'] as $name => $control) {
      $templatedata = [
        'name' => $name,
        'label' => get_string($control['label'], 'block_delta_visualizations'),
        'value' => $values[$name] ?? '',
        'isselect' => $control['type'] === 'select',
        'isdate' => $control['type'] === 'date',
        'isnumber' => $control['type'] === 'number',
      ];

      if ($templatedata['isselect']) {
        $templatedata['options'] = array_map(
          static function ($value) use ($control, $values, $name): array {
            $optionlabel = is_array($control['optionlabel'])
              ? $control['optionlabel'][(string)$value]
              : $control['optionlabel'];
            $optionlabelparam = is_array($control['optionlabel']) ? null : $value;

            return [
              'value' => $value,
              'label' => get_string($optionlabel, 'block_delta_visualizations', $optionlabelparam),
              'selected' => (string)$value === (string)($values[$name] ?? ''),
            ];
          },
          $control['options']
        );
      }

      if ($templatedata['isnumber']) {
        $templatedata['hasmin'] = array_key_exists('min', $control);
        $templatedata['hasmax'] = array_key_exists('max', $control);
        $templatedata['min'] = $control['min'] ?? null;
        $templatedata['max'] = $control['max'] ?? null;
      }

      $controls[] = $templatedata;
    }

    return $controls;
  }

  /**
   * Define all behaviour pattern params in single location.
   *
   * @return array
   */
  private static function definitions(): array
  {
    // provides fallback values for institution-wide values (configured in admin settings)
    $gradethreshold = self::numeric_config(
      'gradethreshold',
      self::DEFAULT_GRADE_THRESHOLD,
      self::MIN_PERCENTAGE,
      self::MAX_PERCENTAGE
    );
    $feedbackgoal = self::numeric_config(
      'feedbackgoal',
      self::DEFAULT_FEEDBACK_GOAL,
      self::MIN_PERCENTAGE,
      self::MAX_PERCENTAGE
    );
    $engagementthreshold = self::numeric_config(
      'engagementthreshold',
      self::DEFAULT_ENGAGEMENT_THRESHOLD,
      self::MIN_ENGAGEMENT_THRESHOLD,
      self::MAX_ENGAGEMENT_THRESHOLD
    );

    return [
      'course_performance_feedback' => [
        'class' => CoursePerformanceFeedback::class,
        'name' => 'Messaging Struggling Students (Low Grades)',
        'group' => self::GROUP_TEACHER,
        'studentsuccess' => true,
        'defaults' => ['gradethreshold' => $gradethreshold],
        'controls' => [],
      ],
      'personalized_feedback' => [
        'class' => PersonalizedFeedback::class,
        'name' => 'Personalized Feedback',
        'group' => self::GROUP_TEACHER,
        'studentsuccess' => true,
        'defaults' => [
          'gradethreshold' => $gradethreshold,
          'feedbackgoal' => $feedbackgoal,
        ],
        'controls' => [],
      ],
      'timely_feedback' => [
        'class' => TimelyFeedback::class,
        'name' => 'Timely Feedback',
        'group' => self::GROUP_TEACHER,
        'studentsuccess' => false,
        'defaults' => ['gradethreshold' => $gradethreshold, 'days' => 8],
        'controls' => [
          'days' => [
            'type' => 'select',
            'datatype' => 'int',
            'label' => 'filterresponsetime',
            'optionlabel' => 'filterdaysoption',
            'options' => range(0, 31),
          ],
        ],
      ],
      'monitoring_forums' => [
        'class' => MonitoringForums::class,
        'name' => 'Monitoring Forums',
        'group' => self::GROUP_TEACHER,
        'studentsuccess' => false,
        'defaults' => [],
        'controls' => [],
      ],
      'consistent-use-lms' => [
        'class' => ConsistentUseLMS::class,
        'name' => 'Consistent Use of LMS',
        'group' => self::GROUP_TEACHER,
        'studentsuccess' => false,
        'defaults' => ['engagementthreshold' => $engagementthreshold],
        'controls' => [
          'startdate' => [
            'type' => 'date',
            'label' => 'filterstartdate',
            'parameter' => 'starttime',
            'defaultrelative' => '-4 weeks',
          ],
          'enddate' => [
            'type' => 'date',
            'label' => 'filterenddate',
            'parameter' => 'endtime',
            'endofday' => true,
            'defaultrelative' => 'today',
          ],
        ],
      ],
      'login_frequency' => [
        'class' => LoginFrequency::class,
        'name' => 'Login frequency',
        'group' => self::GROUP_STUDENT,
        'studentsuccess' => false,
        'defaults' => ['chart_type' => '\\core\\chart_bar'],
        'controls' => [],
      ],
      'student_active_time' => [
        'class' => StudentActiveTime::class,
        'name' => 'Student active time',
        'group' => self::GROUP_STUDENT,
        'studentsuccess' => true,
        'defaults' => self::student_time_range_defaults(true),
        'controls' => self::time_range_controls(),
      ],
      'student_inactive_time' => [
        'class' => StudentInactiveTime::class,
        'name' => 'Student inactive time',
        'group' => self::GROUP_STUDENT,
        'studentsuccess' => true,
        'defaults' => self::student_time_range_defaults(true),
        'controls' => self::time_range_controls(),
      ],
      'time_spent_forums' => [
        'class' => TimeSpentForums::class,
        'name' => 'Time spent viewing forums',
        'group' => self::GROUP_STUDENT,
        'studentsuccess' => true,
        'defaults' => self::student_time_range_defaults(true),
        'controls' => self::time_range_controls(),
      ],
      'time_spent_assignments' => [
        'class' => TimeSpentAssignments::class,
        'name' => 'Time spent on assignments',
        'group' => self::GROUP_STUDENT,
        'studentsuccess' => false,
        'defaults' => self::student_time_range_defaults(),
        'controls' => self::time_range_controls(),
      ],
      'lo_access_frequency' => [
        'class' => LOAccessFrequency::class,
        'name' => 'Learning object access frequency',
        'group' => self::GROUP_STUDENT,
        'studentsuccess' => false,
        'defaults' => self::student_time_range_defaults(),
        'controls' => self::time_range_controls(),
      ],
      'forum_posting_frequency' => [
        'class' => ForumPostingFrequency::class,
        'name' => 'Forum posting frequency',
        'group' => self::GROUP_STUDENT,
        'studentsuccess' => true,
        'defaults' => self::student_time_range_defaults(),
        'controls' => self::time_range_controls(),
      ],
      'time_spent_lo' => [
        'class' => TimeSpentLO::class,
        'name' => 'Time spent accessing learning objects',
        'group' => self::GROUP_STUDENT,
        'studentsuccess' => true,
        'defaults' => self::student_time_range_defaults(true),
        'controls' => self::time_range_controls(),
      ],
      'number_forums_viewed' => [
        'class' => NumberForumsViewed::class,
        'name' => 'Number of forums viewed',
        'group' => self::GROUP_STUDENT,
        'studentsuccess' => true,
        'defaults' => self::student_time_range_defaults(),
        'controls' => self::time_range_controls(),
      ],
      'forum_posting_consistency' => [
        'class' => ForumPostingConsistency::class,
        'name' => 'Forum posting consistency',
        'group' => self::GROUP_STUDENT,
        'studentsuccess' => true,
        'defaults' => [
          'chart_type' => '\\core\\chart_bar',
          'final_window_weeks' => 2,
        ],
        'controls' => [
          'final_window_weeks' => [
            'type' => 'select',
            'datatype' => 'int',
            'label' => 'filtercourseendwindow',
            'options' => [1, 2, 4],
            'optionlabel' => [
              1 => 'filterfinalweek',
              2 => 'filterfinaltwoweeks',
              4 => 'filterfinalfourweeks',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Return the shared defaults for a time-windowed student chart.
   *
   * @return array
   */
  private static function student_time_range_defaults(bool $usesessioncap = false): array
  {
    $defaults = [
      'chart_type' => '\\core\\chart_bar',
      'time_range' => TimeRange::WEEKLY->value,
    ];

    if ($usesessioncap) {
      $defaults['sessioncap'] = (int)self::numeric_config(
        'sessioncap',
        30 * MINSECS,
        MINSECS,
        DAYSECS
      );
    }

    return $defaults;
  }

  /**
   * Return a bounded numeric plugin setting or its safe default.
   *
   * @param string $name Config setting name.
   * @param int|float $default Default value.
   * @param int|float $min Minimum accepted value.
   * @param int|float $max Maximum accepted value.
   * @return int|float
   */
  private static function numeric_config(string $name, $default, $min, $max)
  {
    $value = get_config('block_delta_visualizations', $name);

    if ($value === false || !is_numeric($value)) {
      return $default;
    }

    $value = is_int($default) ? (int)$value : (float)$value;

    return $value >= $min && $value <= $max ? $value : $default;
  }

  /**
   * Return the shared Hourly/Daily/Weekly control schema.
   *
   * @return array
   */
  private static function time_range_controls(): array
  {
    return [
      'time_range' => [
        'type' => 'select',
        'label' => 'filtertimerange',
        'options' => [
          TimeRange::HOURLY->value,
          TimeRange::DAILY->value,
          TimeRange::WEEKLY->value,
        ],
        'optionlabel' => [
          TimeRange::HOURLY->value => 'filterrangehourly',
          TimeRange::DAILY->value => 'filterrangedaily',
          TimeRange::WEEKLY->value => 'filterrangeweekly',
        ],
      ],
    ];
  }
}
