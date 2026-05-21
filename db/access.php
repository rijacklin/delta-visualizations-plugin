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
 * Plugin capabilities for the block_delta_visualizations plugin.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$capabilities = [
  'block/delta_visualizations:myaddinstance' => [
    'captype' => 'read',
    'contextlevel' => CONTEXT_SYSTEM,
    'archetypes' => [
      'user' => CAP_ALLOW
    ],
    'clonepermissionsfrom' => 'moodle/my:manageblocks'
  ],

  'block/delta_visualizations:viewteacher' => [
    'captype' => 'read',
    'contextlevel' => CONTEXT_BLOCK,
    'archetypes' => [
      'editingteacher' => CAP_ALLOW,
      'teacher' => CAP_ALLOW,
      'manager' => CAP_ALLOW,
    ],
  ],

  'block/delta_visualizations:viewstudent' => [
    'captype' => 'read',
    'contextlevel' => CONTEXT_BLOCK,
    'archetypes' => [
      'student' => CAP_ALLOW,
    ],
  ],
];
