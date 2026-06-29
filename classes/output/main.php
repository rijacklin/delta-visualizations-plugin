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
use block_delta_visualizations\student_patterns\NumberForumPosts;
use block_delta_visualizations\student_patterns\StudentActiveTime;
use block_delta_visualizations\student_patterns\StudentInactiveTime;
use block_delta_visualizations\student_patterns\TimeSpentForums;
use block_delta_visualizations\student_patterns\TimeSpentLO;
use block_delta_visualizations\student_patterns\TimeSpentAssignments;
use block_delta_visualizations\student_patterns\NumberForumsViewed;
use block_delta_visualizations\teacher_patterns\ConsistentUseLMS;

use renderable;
use renderer_base;
use templatable;
use stdClass;

class main implements renderable, templatable
{
  private \context $context;
  private array $courseids;

  /**
   * Constructor.
   *
   * @param: $filter (patternset_filter) - the filter object being used to trim the patternset data
   * @return: None
   */
  public function __construct(array $courseids, \context $context)
  {
    $this->courseids = array_values(array_map('intval', $courseids));
    $this->context = $context;
  }

  /**
   * Returns the output data ready for the mustache page
   *
   * @param $output: renderer_base object
   * @return $data: (stdClass) object that holds the data
   */
  public function export_for_template(renderer_base $output): stdClass
  {
    global $USER, $DB;

    $default_date = date('Y-m-d');

    $data = new stdClass();

    // build template data
    $data->userid = $USER->id;
    $data->fullname = fullname($USER);
    $data->courses = $this->get_courses_for_selector($USER->id);
    $data->courseids = array_column($data->courses, 'id');
    $data->isteacher = $this->check_if_teacher($data->courses);
    $data->selected_courseids = $this->get_selected_courseids($data->courses);

    // dates
    $data->startdate = date('Y-m-d');
    $data->enddate = date('Y-m-d');

    // structure tabs data
    $data->tabs = [
      ["id" => "tab1", "name" => "Instructor Behaviour", "content" => "Instructor behaviours"],
      ["id" => "tab2", "name" => "Instructor View of Student Behaviour", "content" => "Instructor-Student behaviours"]
    ];

    /**
     * TEACHER BEHAVIOURS
     */

    $data->teacher_behaviours = [];

    $behaviour_grades = new CoursePerformanceFeedback();
    $behaviour_grades_chart = $behaviour_grades->generate_behaviour_pie_chart([
      'gradethreshold' => 70,
      'courseids' => $data->selected_courseids
    ]);
    if ($behaviour_grades_chart instanceof \core\chart_base) {
      $data->teacher_behaviours[] = [
        'name' => "Messaging struggling students - low grades",
        'chart' => $output->render($behaviour_grades_chart)
      ];
    }

    $personalized_feedback = new PersonalizedFeedback();
    $personalized_feedback_chart = $personalized_feedback->generate_behaviour_pie_chart([
      'gradethreshold' => 70,
      'courseids' => $data->selected_courseids,
      'feedbackgoal' => 60
    ]);
    if ($personalized_feedback_chart instanceof \core\chart_base) {
      $data->teacher_behaviours[] = [
        'name' => "Personalized Feedback",
        'chart' => $output->render($personalized_feedback_chart)
      ];
    }

    $timely_feedback = new TimelyFeedback();
    $timely_feedback_chart = $timely_feedback->generate_behaviour_pie_chart([
      'gradethreshold' => 70,
      'days' => 8,
      'courseids' => $data->selected_courseids
    ]);
    if ($timely_feedback_chart instanceof \core\chart_base) {
      $data->teacher_behaviours[] = [
        'name' => "Timely Feedback",
        'chart' => $output->render($timely_feedback_chart)
      ];
    }

    $monitoring_forums = new MonitoringForums();
    $monitoring_forums_chart = $monitoring_forums->generate_behaviour_pie_chart([
      'courseids' => $data->selected_courseids
    ]);
    if ($monitoring_forums_chart instanceof \core\chart_base) {
      $data->teacher_behaviours[] = [
        'name' => "Monitoring Forums",
        'chart' => $output->render($monitoring_forums_chart)
      ];
    }

    $consistent_use_lms = new ConsistentUseLMS($output);
    $consistent_use_lms_chart = $consistent_use_lms->generate_behaviour_pie_chart([
      'courseids' => $data->selected_courseids,
      'engagementthreshold' => 5
    ]);
    if ($consistent_use_lms_chart instanceof \core\chart_base) {
      $data->teacher_behaviours[] = [
        'name' => "Consistent Use of LMS",
        'chart' => $output->render($consistent_use_lms_chart)
      ];
    }

    /**
     * STUDENT BEHAVIOURS
     */

    $login_frequency = new LoginFrequency();
    $login_frequency_chart = $login_frequency->generate_chart([
      "chart_type" => '\core\chart_bar'
    ]);
    $data->student_behaviours[] = [
      'name' => "Login frequency",
      'chart' => !empty($login_frequency->get_records()) ? $output->render($login_frequency_chart) : "NO DATA"
    ];

    $student_active_time = new StudentActiveTime();
    $student_active_time_chart = $student_active_time->generate_behaviour_chart([
      'courseids' => $data->selected_courseids,
      'chart_type' => '\core\chart_bar'
    ]);
    if ($student_active_time_chart instanceof \core\chart_base) {
      $data->student_behaviours[] = [
        'name' => "Student active time",
        'chart' => !empty($student_active_time->get_records()) ? $output->render($student_active_time_chart) : "NO DATA"
      ];
    }

    // TODO: Why is this not tied to course?
    $student_inactive_time = new StudentInactiveTime();
    $student_inactive_time_chart = $student_inactive_time->generate_chart([
      "chart_type" => '\core\chart_bar'
    ]);
    $data->student_behaviours[] = [
      'name' => "Student inactive time",
      'chart' => !empty($student_inactive_time->get_records()) ? $output->render($student_inactive_time_chart) : "NO DATA"
    ];

    $time_spent_forums = new TimeSpentForums();
    $time_spent_forums_chart = $time_spent_forums->generate_behaviour_chart([
      'courseids' => $data->selected_courseids,
      "chart_type" => '\core\chart_bar'
    ]);
    if ($time_spent_forums_chart instanceof \core\chart_base) {
      $data->student_behaviours[] = [
        'name' => "Time spent viewing forums",
        'chart' => !empty($time_spent_forums->get_records()) ? $output->render($time_spent_forums_chart) : "NO DATA"
      ];
    }

    $time_spent_assignments = new TimeSpentForums();
    $time_spent_assignments_chart = $time_spent_assignments->generate_behaviour_chart([
      'courseids' => $data->selected_courseids,
      "chart_type" => '\core\chart_bar'
    ]);
    if ($time_spent_assignments_chart instanceof \core\chart_base) {
      $data->student_behaviours[] = [
        'name' => "Time spent on assignments",
        'chart' => !empty($time_spent_assignments->get_records()) ? $output->render($time_spent_assignments_chart) : "NO DATA"
      ];
    }

    $lo_access_frequency = new LOAccessFrequency();
    $lo_access_frequency_chart = $lo_access_frequency->generate_behaviour_chart([
      'courseids' => $data->selected_courseids,
      "chart_type" => '\core\chart_bar'
    ]);
    if ($lo_access_frequency_chart instanceof \core\chart_base) {
      $data->student_behaviours[] = [
        'name' => "Learning object access frequency",
        'chart' => !empty($lo_access_frequency->get_records()) ? $output->render($lo_access_frequency_chart) : "NO DATA"
      ];
    }

    $forum_posting_frequency = new ForumPostingFrequency();
    $forum_posting_frequency_chart = $forum_posting_frequency->generate_behaviour_chart([
      'courseids' => $data->selected_courseids,
      "chart_type" => '\core\chart_bar'
    ]);
    if ($forum_posting_frequency_chart instanceof \core\chart_base) {
      $data->student_behaviours[] = [
        'name' => "Forum posting frequency",
        'chart' => !empty($forum_posting_frequency->get_records()) ? $output->render($forum_posting_frequency_chart) : "NO DATA"
      ];
    }

    $time_spent_lo = new TimeSpentLO();
    $time_spent_lo_chart = $time_spent_lo->generate_behaviour_chart([
      'courseids' => $data->selected_courseids,
      "chart_type" => '\core\chart_bar'
    ]);
    if ($time_spent_lo_chart instanceof \core\chart_base) {
      $data->student_behaviours[] = [
        'name' => "Time spent accessing learning objects",
        'chart' => !empty($time_spent_lo->get_records()) ? $output->render($time_spent_lo_chart) : "NO DATA"
      ];
    }

    $number_forums_viewed = new NumberForumsViewed();
    $number_forums_viewed_chart = $number_forums_viewed->generate_behaviour_chart([
      'courseids' => $data->selected_courseids,
      "chart_type" => '\core\chart_bar'
    ]);
    if ($number_forums_viewed_chart instanceof \core\chart_base) {
      $data->student_behaviours[] = [
        'name' => "Number of forums viewed",
        'chart' => !empty($number_forums_viewed->get_records()) ? $output->render($number_forums_viewed_chart) : "NO DATA"
      ];
    }

    $number_forum_posts = new NumberForumPosts();
    $number_forum_posts_chart = $number_forum_posts->generate_behaviour_chart([
      'courseids' => $data->selected_courseids,
      "chart_type" => '\core\chart_bar'
    ]);
    if ($number_forum_posts_chart instanceof \core\chart_base) {
      $data->student_behaviours[] = [
        'name' => "Number of forum posts",
        'chart' => !empty($number_forum_posts->get_records()) ? $output->render($number_forum_posts_chart) : "NO DATA"
      ];
    }

    $forum_posting_consistency = new ForumPostingConsistency();
    $forum_posting_consistency_chart = $forum_posting_consistency->generate_behaviour_chart([
      'courseids' => $data->selected_courseids,
      "chart_type" => '\core\chart_bar'
    ]);
    if ($forum_posting_consistency_chart instanceof \core\chart_base) {
      $data->student_behaviours[] = [
        'name' => "Forum posting consistency",
        'chart' => !empty($forum_posting_consistency->get_records()) ? $output->render($forum_posting_consistency_chart) : "NO DATA"
      ];
    }

    return $data;
  }

  protected function get_courses_for_selector(int $teacher_id): array
  {
    $courses = \enrol_get_users_courses($teacher_id, true);

    // structure courses data
    foreach ($courses as $course) {
      $courseids[] = [
        'id' => $course->id,
        'name' => $course->shortname,
        'selected' => in_array((int)$course->id, $this->courseids, true)
      ];
    }

    return $courseids;
  }

  protected function get_selected_courseids(array $courses): array
  {
    $selected_course_ids = [];

    foreach ($courses as $course) {
      if ($course['selected']) {
        $selected_course_ids[] = $course['id'];
      }
    }

    return $selected_course_ids;
  }

  protected function get_hidden_params(): array
  {
    global $PAGE;

    $hiddenparams = [];

    foreach ($PAGE->url->params() as $name => $value) {
      if ($name === 'courseids' || is_array($value)) continue;

      $hiddenparams[] = [
        'name' => $name,
        'value' => $value
      ];
    }

    return $hiddenparams;
  }

  // protected function generate_behaviour_chart() {
  //
  // }

  private function check_if_teacher($courses): bool
  {
    global $USER;

    $isTeacher = false;

    foreach ($courses as $course) {
      $course_context = \context_course::instance($course['id']);
      if (has_capability('block/delta_visualizations:viewteacher', $course_context, $USER)) {
        $isTeacher = true;
      }
    }

    return $isTeacher;
  }
}
