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
  /**
   * Returns all behaviour definitions
   *
   * @param BehaviourGroup|null $group Optional teacher or student group filter
   * @return array
   */
  public static function all(?BehaviourGroup $group = null): array
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
   * Validate the selected filter option.
   *
   * @param string $id Behaviour identifier.
   * @param string $value Submitted filter option value.
   * @return array Form field names mapped to initial values.
   */
  public static function filter_param(string $id, string $value): array
  {
    $definition = self::get($id);
    $control = $definition['control'];

    foreach ($control['options'] as $option) {
      if ((string)$option === $value) {
        $definition['defaults'][$control['name']] = $option;
        return $definition['defaults'];
      }
    }

    throw new \invalid_parameter_exception('Invalid filter value');
  }

  /**
   * Build template data for a behaviour filter.
   *
   * @param array $definition Behaviour registry definition.
   * @return array|null
   */
  public static function control_for_template(array $definition): ?array
  {
    $control = $definition['control'];

    if ($control === null) {
      return null;
    }

    $selected = $definition['defaults'][$control['name']];
    $control['label'] = get_string($control['label'], 'block_delta_visualizations');
    $control['options'] = array_map(
      static fn($value): array => [
        'value' => $value,
        'label' => get_string(
          $control['optionlabel'][(string)$value],
          'block_delta_visualizations'
        ),
        'selected' => (string)$value === (string)$selected,
      ],
      $control['options']
    );

    return $control;
  }

  /**
   * Define all behaviours in one allow-listed location.
   *
   * @return array
   */
  private static function definitions(): array
  {
    $gradethreshold = behaviour_config::get('gradethreshold');
    $feedbackgoal = behaviour_config::get('feedbackgoal');
    $engagementthreshold = behaviour_config::get('engagementthreshold');
    $timelyfeedbackdays = behaviour_config::get('timelyfeedbackdays');

    return [
      'course_performance_feedback' => [
        'class' => CoursePerformanceFeedback::class,
        'name' => 'Messaging Struggling Students (Low Grades)',
        'group' => BehaviourGroup::TEACHER,
        'studentsuccess' => true,
        'defaults' => ['gradethreshold' => $gradethreshold],
        'control' => null,
      ],
      'personalized_feedback' => [
        'class' => PersonalizedFeedback::class,
        'name' => 'Personalized Feedback',
        'group' => BehaviourGroup::TEACHER,
        'studentsuccess' => true,
        'defaults' => [
          'gradethreshold' => $gradethreshold,
          'feedbackgoal' => $feedbackgoal,
        ],
        'control' => null,
      ],
      'timely_feedback' => [
        'class' => TimelyFeedback::class,
        'name' => 'Timely Feedback',
        'group' => BehaviourGroup::TEACHER,
        'studentsuccess' => false,
        'defaults' => [
          'gradethreshold' => $gradethreshold,
          'days' => $timelyfeedbackdays,
        ],
        'control' => null,
      ],
      'monitoring_forums' => [
        'class' => MonitoringForums::class,
        'name' => 'Monitoring Forums',
        'group' => BehaviourGroup::TEACHER,
        'studentsuccess' => false,
        'defaults' => [],
        'control' => null,
      ],
      'consistent-use-lms' => [
        'class' => ConsistentUseLMS::class,
        'name' => 'Consistent Use of LMS',
        'group' => BehaviourGroup::TEACHER,
        'studentsuccess' => false,
        'defaults' => [
          'engagementthreshold' => $engagementthreshold,
          'time_range' => TimeRange::WEEKLY->value,
        ],
        'control' => self::time_range_control(),
      ],
      'login_frequency' => [
        'class' => LoginFrequency::class,
        'name' => 'Login frequency',
        'group' => BehaviourGroup::STUDENT,
        'studentsuccess' => false,
        'defaults' => ['chart_type' => '\\core\\chart_bar'],
        'control' => null,
      ],
      'student_active_time' => [
        'class' => StudentActiveTime::class,
        'name' => 'Student active time',
        'group' => BehaviourGroup::STUDENT,
        'studentsuccess' => true,
        'defaults' => self::student_time_range_defaults(true),
        'control' => self::time_range_control(),
      ],
      'student_inactive_time' => [
        'class' => StudentInactiveTime::class,
        'name' => 'Student inactive time',
        'group' => BehaviourGroup::STUDENT,
        'studentsuccess' => true,
        'defaults' => self::student_time_range_defaults(true),
        'control' => self::time_range_control(),
      ],
      'time_spent_forums' => [
        'class' => TimeSpentForums::class,
        'name' => 'Time spent viewing forums',
        'group' => BehaviourGroup::STUDENT,
        'studentsuccess' => true,
        'defaults' => self::student_time_range_defaults(true),
        'control' => self::time_range_control(),
      ],
      'time_spent_assignments' => [
        'class' => TimeSpentAssignments::class,
        'name' => 'Time spent on assignments',
        'group' => BehaviourGroup::STUDENT,
        'studentsuccess' => false,
        'defaults' => self::student_time_range_defaults(true),
        'control' => self::time_range_control(),
      ],
      'lo_access_frequency' => [
        'class' => LOAccessFrequency::class,
        'name' => 'Learning object access frequency',
        'group' => BehaviourGroup::STUDENT,
        'studentsuccess' => false,
        'defaults' => self::student_time_range_defaults(),
        'control' => self::time_range_control(),
      ],
      'forum_posting_frequency' => [
        'class' => ForumPostingFrequency::class,
        'name' => 'Forum posting frequency',
        'group' => BehaviourGroup::STUDENT,
        'studentsuccess' => true,
        'defaults' => self::student_time_range_defaults(),
        'control' => self::time_range_control(),
      ],
      'time_spent_lo' => [
        'class' => TimeSpentLO::class,
        'name' => 'Time spent accessing learning objects',
        'group' => BehaviourGroup::STUDENT,
        'studentsuccess' => true,
        'defaults' => self::student_time_range_defaults(true),
        'control' => self::time_range_control(),
      ],
      'number_forums_viewed' => [
        'class' => NumberForumsViewed::class,
        'name' => 'Number of forums viewed',
        'group' => BehaviourGroup::STUDENT,
        'studentsuccess' => true,
        'defaults' => self::student_time_range_defaults(),
        'control' => self::time_range_control(),
      ],
      'forum_posting_consistency' => [
        'class' => ForumPostingConsistency::class,
        'name' => 'Forum posting consistency',
        'group' => BehaviourGroup::STUDENT,
        'studentsuccess' => true,
        'defaults' => [
          'chart_type' => '\\core\\chart_bar',
          'final_window_weeks' => 2,
        ],
        'control' => null,
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
      $defaults['sessioncap'] = behaviour_config::get('sessioncap');
    }

    return $defaults;
  }

  /**
   * Return the shared reporting-period control schema.
   *
   * @return array
   */
  private static function time_range_control(): array
  {
    return [
      'name' => 'time_range',
      'type' => 'select',
      'label' => 'filtertimerange',
      'options' => [
        TimeRange::HOURLY->value,
        TimeRange::DAILY->value,
        TimeRange::WEEKLY->value,
        TimeRange::MONTHLY->value,
        TimeRange::YEARLY->value,
      ],
      'optionlabel' => [
        TimeRange::HOURLY->value => 'filterrangehourly',
        TimeRange::DAILY->value => 'filterrangedaily',
        TimeRange::WEEKLY->value => 'filterrangeweekly',
        TimeRange::MONTHLY->value => 'filterrangemonthly',
        TimeRange::YEARLY->value => 'filterrangeyearly',
      ],
    ];
  }
}
