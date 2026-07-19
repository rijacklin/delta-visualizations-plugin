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

use block_delta_visualizations\local\behaviour_filter_validator;
use block_delta_visualizations\local\behaviour_registry;
use renderer_base;
use stdClass;
use templatable;

/**
 * Provides the dashboard data consumed by the instructor dashboard template.
 */
class main implements templatable
{
  private \context $context;
  private array $courseids;

  /**
   * Constructor.
   *
   * @param array $courseids Course IDs selected in the dashboard filter.
   * @param \context $context Block context used by chart Fragment requests.
   */
  public function __construct(array $courseids, \context $context)
  {
    $this->courseids = array_values(array_map('intval', $courseids));
    $this->context = $context;
  }

  /**
   * Return output data ready for the dashboard template.
   *
   * @param renderer_base $output Renderer used to produce chart HTML.
   * @return stdClass Dashboard template data.
   */
  public function export_for_template(renderer_base $output): stdClass
  {
    global $USER;

    $data = new stdClass();
    $data->courses = $this->get_courses_for_selector($USER->id);
    $selected_courseids = $this->get_selected_courseids($data->courses);
    $data->hasSelectedCourses = !empty($selected_courseids);

    $data->tabs = [
      ['name' => 'Instructor Behaviour', 'content' => 'Instructor behaviours'],
      ['name' => 'Instructor View of Student Behaviour', 'content' => 'Instructor-Student behaviours'],
    ];

    $data->teacher_behaviours = [];
    $data->student_behaviours = [];

    if ($data->hasSelectedCourses) {
      $data->teacher_behaviours = $this->render_behaviour_group(
        $output,
        behaviour_registry::GROUP_TEACHER,
        $selected_courseids
      );
      $data->student_behaviours = $this->render_behaviour_group(
        $output,
        behaviour_registry::GROUP_STUDENT,
        $selected_courseids
      );
    }

    return $data;
  }

  /**
   * Render every registered behaviour in a dashboard group.
   *
   * @param renderer_base $output Renderer used to produce chart HTML.
   * @param string $group Registry group identifier.
   * @param array $courseids Selected course IDs.
   * @return array Behaviour block template data.
   */
  private function render_behaviour_group(
    renderer_base $output,
    string $group,
    array $courseids
  ): array {
    $items = [];
    $courseidsjson = json_encode($courseids, JSON_THROW_ON_ERROR);

    foreach (behaviour_registry::all($group) as $id => $definition) {
      $filtervalues = behaviour_registry::filter_defaults($id);
      $params = array_replace(
        $definition['defaults'],
        behaviour_filter_validator::validate($id, $filtervalues),
        ['courseids' => $courseids]
      );

      $behaviour = behaviour_registry::create($id);
      $chart = $behaviour->generate_chart($params);

      if (!$chart instanceof \core\chart_base) {
        continue;
      }

      $item = [
        'name' => $definition['name'],
        'studentsuccess' => $definition['studentsuccess'],
        'chart' => !empty($behaviour->get_records())
          ? $output->render($chart)
          : get_string('nodata', 'block_delta_visualizations'),
      ];

      if (!empty($definition['controls'])) {
        $item['behaviour-id'] = $id;
        $item['hascontrols'] = true;
        $item['controls'] = behaviour_registry::controls_for_template($id, $filtervalues);
        $item['contextid'] = $this->context->id;
        $item['courseidsjson'] = $courseidsjson;
      }

      $items[] = $item;
    }

    return $items;
  }

  /**
   * Return the current user's courses formatted for the selector.
   *
   * @param int $teacherid User ID.
   * @return array Course selector data.
   */
  protected function get_courses_for_selector(int $teacherid): array
  {
    $courses = \enrol_get_users_courses($teacherid, true);
    $courseids = [];

    foreach ($courses as $course) {
      $courseids[] = [
        'id' => $course->id,
        'name' => $course->shortname,
        'selected' => in_array((int)$course->id, $this->courseids, true),
      ];
    }

    return $courseids;
  }

  /**
   * Extract selected IDs from course selector data.
   *
   * @param array $courses Course selector data.
   * @return array Selected course IDs.
   */
  protected function get_selected_courseids(array $courses): array
  {
    $selectedcourseids = [];

    foreach ($courses as $course) {
      if ($course['selected']) {
        $selectedcourseids[] = $course['id'];
      }
    }

    return $selectedcourseids;
  }
}
