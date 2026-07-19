<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Core file of block plugin. Handles initializing and rendering the block.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_delta_visualizations extends block_base
{
  /**
   * Initialises the block with the plugin name/title
   *
   * @return void
   */
  public function init()
  {
    $this->title = get_string('pluginname', 'block_delta_visualizations');
  }

  /**
   * Gets the block contents. This consists of the HTML from the mustache
   * template and the dynamic data from the backend (e.g., behaviour data,
   * rendered charts).
   *
   * @return string HTML to be rendered for the block
   */
  public function get_content()
  {
    global $OUTPUT;

    // immediately return rendered content if already exists
    if ($this->content !== null) {
      return $this->content;
    }

    // initialize generic, empty class to store content
    $this->content = new stdClass();

    // grab the selected course ids (multi-select box at top of block)
    $selected_courseids = optional_param_array('courseids', [], PARAM_RAW);

    // create an instance of the renderer for the current context
    $renderable = new \block_delta_visualizations\output\main(
      $selected_courseids,
      $this->context
    );

    // store the html and dynamic data to be rendered for the block
    $this->content->text = $OUTPUT->render_from_template(
      'block_delta_visualizations/instructor_dashboard',
      $renderable->export_for_template($OUTPUT)
    );

    return $this->content;
  }

  /**
   * Defines the pages in which this block can and cannot be added.
   *
   * @return array of page types
   */
  public function applicable_formats()
  {
    return [
      'admin' => false,
      'site-index' => true,
      'course-view' => true,
      'mod' => false,
      'my' => true,
    ];
  }

  // Enables configuration/settings page
  public function has_config(): bool
  {
    return true;
  }
}
