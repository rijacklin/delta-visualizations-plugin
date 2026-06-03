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
 * Plugin version and other meta-data are defined here.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_delta_visualizations\output\main;

class block_delta_visualizations extends block_base
{
  /**
   * Initialises the block.
   *
   * @return void
   */
  public function init()
  {
    $this->title = get_string('pluginname', 'block_delta_visualizations');
  }

  /**
   * Gets the block contents.
   *
   * @return string The block HTML.
   */
  public function get_content()
  {
    global $COURSE;

    // exit early if no content
    if ($this->content !== null) {
      return $this->content;
    }

    $this->content = new stdClass();

    $context = $this->context;
    $renderer = $this->page->get_renderer('block_delta_visualizations');

    $page = new main($context, $COURSE);

    $this->content->text = $renderer->render($page);
    $this->content->footer = "";

    return $this->content;
  }

  // /**
  //  * Renders the page content based on current controller and filter values
  //  *
  //  * @param none.
  //  * @return none.
  //  */
  // private function block_delta_visualizations_render_new_content()
  // {
  //   $filter = "";
  //
  //   // $filter = $this->block_delta_visualizations_filter;
  //   // $controller = $this->block_delta_visualizations_controller;
  //   // $renderable = new \block_delta_visualizations\output\instructor_dashboard($filter, $controller);
  //   $renderable = new \block_delta_visualizations\output\instructor_dashboard($filter);
  //   $renderer = $this->page->get_renderer('block_delta_visualizations');
  //   $this->content = (object) [
  //     'text' => $renderer->render($renderable),
  //     'footer' => ''
  //   ];
  // }

  /**
   * Defines in which pages this block can be added.
   *
   * @return array of the pages where the block can be added.
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
}
