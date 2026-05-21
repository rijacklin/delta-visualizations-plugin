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
 * Creates rendered blocks for the delta visualizations plugin.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

/**
 * Creates a renderer for the block_delta_visualizations
 *
 */
class renderer extends plugin_renderer_base
{
  /**
   * Render the instructor dashboard block.
   *
   * @param: renderable $mainpage The output for the instructor dashboard.
   * @return: a renderer for the instructor dashboard mustache page
   */
  public function render_main(main $page): string
  {
    $data = $page->export_for_template($this);

    // echo "<pre>";
    // var_dump($data);
    // echo "</pre>";
    // die();

    return $this->render_from_template(
      'block_delta_visualizations/instructor_dashboard',
      $data
    );
  }
}
