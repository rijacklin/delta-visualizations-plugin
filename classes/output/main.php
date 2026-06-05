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
 * Defines renderable visualizations dashboard.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\output;

defined('MOODLE_INTERNAL') || die();

use block_delta_visualizations\teacher_patterns\CoursePerformanceFeedback;
use block_delta_visualizations\teacher_patterns\PersonalizedFeedback;

use block_delta_visualizations\student_patterns\ForumPostingFrequency;
use block_delta_visualizations\student_patterns\LOAccessFrequency;
use block_delta_visualizations\student_patterns\LoginFrequency;
use block_delta_visualizations\student_patterns\StudentActiveTime;
use block_delta_visualizations\student_patterns\StudentInactiveTime;
use block_delta_visualizations\student_patterns\TimeSpentForums;
use block_delta_visualizations\student_patterns\TimeSpentLO;
use block_delta_visualizations\student_patterns\TimeSpentAssignments;
use block_delta_visualizations\student_patterns\NumberForumsViewed;

use renderable;
use renderer_base;
use templatable;
use stdClass;

class main implements renderable, templatable
{
  private \context $context;
  private \stdClass $course;

  /**
   * Constructor.
   *
   * @param: $filter (patternset_filter) - the filter object being used to trim the patternset data
   * @return: None
   */
  public function __construct(\context $context, \stdClass $course)
  {
    $this->context = $context;
    $this->course = $course;
  }

  /**
   * Returns the output data ready for the mustache page
   *
   * @param $output: renderer_base object
   * @return $data: (stdClass) object that holds the data
   */
  public function export_for_template(renderer_base $output): stdClass
  {
    global $USER;

    $courses = \enrol_get_users_courses($USER->id, true);

    // check if teacher
    $isTeacher = $this->check_if_teacher($courses);

    $data = new stdClass();

    // build template data
    $data->isteacher = $isTeacher;
    $data->userid = $USER->id;
    $data->fullname = fullname($USER);
    $data->courseid = $this->course->id ?? 0;

    // structure courses data
    foreach ($courses as $course) {
      $data->courses[] = [
        'id' => $course->id,
        'name' => $course->shortname
      ];
    }

    // dates
    $data->startdate = date('Y-m-d');
    $data->enddate = date('Y-m-d');

    // structure tabs data
    $data->tabs = [
      ["id" => "tab1", "name" => "Instructor Behaviour", "content" => "Instructor behaviours"],
      ["id" => "tab2", "name" => "Instructor View of Student Behaviour", "content" => "Instructor-Student behaviours"]
    ];

    $behaviour_grades = new CoursePerformanceFeedback();
    $activity_behaviour = $behaviour_grades->query_behaviour_data([70, 30]);
    // $behaviour_grades_chart = $output->render($behaviour_grades->create_bar_chart($activity_behaviour));
    $behaviour_grades_chart = $output->render($behaviour_grades->create_pie_chart($activity_behaviour));

    $personalized_feedback = new PersonalizedFeedback();
    $activity_behaviour_personalized_feedback = $personalized_feedback->query_behaviour_data([70, 0.60]);
    $personalized_feedback_chart = $output->render($behaviour_grades->create_pie_chart($activity_behaviour_personalized_feedback));

    // structure teacher behaviours data
    $data->teacher_behaviours = [
      [
        "name" => "Messaging struggling students - low grades",
        "chart" => $behaviour_grades_chart,
      ],
      [
        "name" => "Personalized Feedback",
        "chart" => $personalized_feedback_chart,
      ],
    ];

    $login_frequency = new LoginFrequency();
    $login_frequency->query_behaviour_data();
    $login_frequency_chart = $output->render($login_frequency->create_bar_chart());

    $student_active_time = new StudentActiveTime();
    // TODO: GET ACTUAL FILTER VALUE FROM TEMPLATE
    $student_active_time->query_behaviour_data();
    $student_active_time_chart = $output->render($student_active_time->create_bar_chart());

    $student_inactive_time = new StudentInactiveTime();
    // TODO: GET ACTUAL FILTER VALUE FROM TEMPLATE
    $student_inactive_time->query_behaviour_data();
    $student_inactive_time_chart = $output->render($student_inactive_time->create_bar_chart());

    $time_spent_forums = new TimeSpentForums();
    // TODO: GET ACTUAL FILTER VALUE FROM TEMPLATE
    $time_spent_forums->query_behaviour_data();
    $time_spent_forums_chart = $output->render($time_spent_forums->create_bar_chart());

    $time_spent_assign = new TimeSpentAssignments();
    // TODO: GET ACTUAL FILTER VALUE FROM TEMPLATE
    $time_spent_assign->query_behaviour_data();
    $time_spent_assign_chart = $output->render($time_spent_assign->create_bar_chart());

    $lo_acccess_frequency = new LOAccessFrequency();
    // TODO: GET ACTUAL FILTER VALUE FROM TEMPLATE
    $lo_acccess_frequency->query_behaviour_data();
    $lo_acccess_frequency_chart = $output->render($lo_acccess_frequency->create_bar_chart());

    $forum_posting_frequency = new ForumPostingFrequency();
    // TODO: GET ACTUAL FILTER VALUE FROM TEMPLATE
    $forum_posting_frequency->query_behaviour_data();
    $forum_posting_frequency_chart = $output->render($forum_posting_frequency->create_bar_chart());

    $time_spent_lo = new TimeSpentLO();
    // TODO: GET ACTUAL FILTER VALUE FROM TEMPLATE
    $time_spent_lo->query_behaviour_data();
    $time_spent_lo_chart = $output->render($time_spent_lo->create_bar_chart());

    $num_forums_viewed = new NumberForumsViewed();
    // TODO: GET ACTUAL FILTER VALUE FROM TEMPLATE
    $num_forums_viewed->query_behaviour_data();
    $num_forums_viewed_chart = $output->render($num_forums_viewed->create_bar_chart());

    // structure teacher view of student behaviours data
    $data->student_behaviours = [
      [
        "name" => "Login frequency",
        "chart" => $login_frequency_chart,
      ],
      [
        "name" => "Student active time",
        "chart" => $student_active_time_chart,
      ],
      [
        "name" => "Student inactive time",
        "chart" => $student_inactive_time_chart,
      ],
      [
        "name" => "Time spent viewing forums",
        "chart" => $time_spent_forums_chart,
      ],
      [
        "name" => "Time spent on assignments",
        "chart" => $time_spent_assign_chart,
      ],
      [
        "name" => "Learning object access frequency",
        "chart" => $lo_acccess_frequency_chart,
      ],
      [
        "name" => "Forum posting frequency",
        "chart" => $forum_posting_frequency_chart,
      ],
      [
        "name" => "Time spent accessing learning objects",
        "chart" => $time_spent_lo_chart,
      ],
      [
        "name" => "Number of forums viewed",
        "chart" => $num_forums_viewed_chart,
      ],
      [
        "name" => "Number of messages sent",
        "chart" => null,
      ],
      [
        "name" => "Forum posting consistency",
        "chart" => null,
      ],
      [
        "name" => "Quiz consistency",
        "chart" => null,
      ]
    ];

    // echo "<pre>";
    // var_dump($data->behaviours);
    // echo "</pre>";
    // die();

    return $data;
  }

  private function check_if_teacher($courses): bool
  {
    global $USER;

    $isTeacher = false;

    foreach ($courses as $course) {
      $course_context = \context_course::instance($course->id);
      if (has_capability('block/delta_visualizations:viewteacher', $course_context, $USER)) {
        $isTeacher = true;
      }
    }

    return $isTeacher;
  }
}
