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
 * lib.php has largely been deprecated from Moodle, but needs to be used here for
 * the Moodle Fragments API.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function block_delta_visualizations_output_fragment_refresh_chart(array $args): string
{
  global $PAGE;

  // grab context ID from client-side fragment request
  $context = $args['context'];

  // ensure fragment request is passing the proper block context ID
  if ($context->contextlevel !== CONTEXT_BLOCK) {
    throw new invalid_parameter_exception('Invalid context');
  }

  // ensure user has the proper permission (i.e., is a teacher in the selected courses)
  require_capability('block/delta_visualizations:viewteacher', $context);

  // decodes the fragment JSON
  $courseids = json_decode($args['courseids'] ?? '', true, JSON_THROW_ON_ERROR);
  if (!is_array($courseids)) {
    throw new invalid_parameter_exception('Invalid course IDs');
  }

  // normalizes courseids provided by front-end for backend use
  $courseids = array_values(array_unique(array_map('intval', $courseids)));
  if (empty($courseids) || in_array(0, $courseids, true)) {
    throw new invalid_parameter_exception('Invalid course IDs');
  }

  // sanitize the client-side values, then pass to registry for validation
  $behaviourid = clean_param($args['behaviourid'] ?? '', PARAM_RAW_TRIMMED);
  $filtervalue = clean_param($args['filtervalue'] ?? '', PARAM_RAW_TRIMMED);
  $params = \block_delta_visualizations\local\BehaviourRegistry::params_for_filter(
    $behaviourid,
    $filtervalue
  );

  // restore the selected course ids before constructing new behaviour and chart
  $params['courseids'] = $courseids;
  $behaviour = \block_delta_visualizations\local\BehaviourRegistry::create($behaviourid);
  $chart = $behaviour->generate_chart($params);

  // if no records, need to send constructed HTML elemenet to avoid runtime error
  if (empty($behaviour->get_records())) {
    return \html_writer::div(
      get_string('nodata', 'block_delta_visualizations')
    );
  }

  // render and send updated chart to client-side
  $output = $PAGE->get_renderer('core');

  return $output->render($chart);
}
