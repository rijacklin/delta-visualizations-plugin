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

    // structure behaviours data
    $data->behaviours = [
      ["name" => "Messaging struggling students"],
      ["name" => "Personalized and timely feedback"],
      ["name" => "Solicit student feedback"],
      ["name" => "Consistent use of LMS"],
      ["name" => "Peer-to-peer support"],
      ["name" => "Ensure students have timely access to technical support"]
    ];

    $chart = new \core\chart_pie();
    $chart->set_labels(['2023', '2024', '2025', '2026']);
    $series = new \core\chart_series('My series title', [400, 460, 1120, 540]);
    $chart->add_series($series);

    // $data->chartjson = json_encode($chart);
    $data->chart = $output->render($chart);

    // echo "<pre>";
    // var_dump($data);
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
