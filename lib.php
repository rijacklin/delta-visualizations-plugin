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
 * lib.php has largely been deprecated from Moodle, but needs to be used for the
 * Moodle Fragments API.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function block_delta_visualizations_output_fragment_refresh_chart(array $args): string
{
  global $PAGE;

  // grab context ID from font-end fragment request
  $context = $args['context'];

  // ensure fragment request from front-end is passing the proper block context ID
  if ($context->contextlevel !== CONTEXT_BLOCK) {
    throw new invalid_parameter_exception('Invalid context');
  }

  // ensure user has the proper permission (i.e., is a teacher in the selected courses)
  require_capability('block/delta_visualizations:viewteacher', $context);

  // decodes the selected courses passed from the fragment (as JSON) and stores them in PHP array
  $courseids = json_decode($args['courseids'] ?? '', true, JSON_THROW_ON_ERROR);
  if (!is_array($courseids)) {
    // throw error if courseids can't be decoded or stored in array
    throw new invalid_parameter_exception('Invalid course IDs');
  }

  // normalizes courseids provided by front-end for backend use
  $courseids = array_values(array_unique(array_map('intval', $courseids)));
  if (empty($courseids) || in_array(0, $courseids, true)) {
    // throw error if courseids can't be decoded or stored in array
    throw new invalid_parameter_exception('Invalid course IDs');
  }

  // sanitize the behaviourid passed from front-end
  $behaviourid = clean_param($args['behaviourid'] ?? '', PARAM_RAW_TRIMMED);

  // store a copy of the updated parameters from the front-end, then remove the non-filter values
  $filterargs = $args;
  unset($filterargs['context'], $filterargs['behaviourid'], $filterargs['courseids']);

  // replace the default params with the updated filter values
  $params = array_replace(
    \block_delta_visualizations\local\behaviour_registry::defaults($behaviourid),
    \block_delta_visualizations\local\behaviour_filter_validator::validate(
      $behaviourid,
      $filterargs
    )
  );

  // restore the selected course ids before constructing new behaviour from registry
  $params['courseids'] = $courseids;
  $behaviour = \block_delta_visualizations\local\behaviour_registry::create($behaviourid);

  // generate updated behaviour visualization chart
  $chart = $behaviour->generate_chart($params);

  // if there are no records for the updated behaviour chart, pass a proper HTML element with "nodata" text
  if (empty($behaviour->get_records()) || !$chart instanceof \core\chart_base) {
    // NOTE: can't pass the "nodata" text as just a string; Fragment API requires proper HTML element
    return \html_writer::div(
      get_string('nodata', 'block_delta_visualizations')
    );
  }

  // grab the renderer object for the page and render the updated chart and send to front-end as a fragment
  $output = $PAGE->get_renderer('core');
  return $output->render($chart);
}
