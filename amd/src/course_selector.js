// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Transforms a multi-select field to the Moodle multi-select UI without needing
 * to use the Moodle form API on the back-end.
 *
 * @module     block_delta_visualizations/course_selector
 * @copyright  2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Autocomplete from 'core/form-autocomplete';

/**
 * Transforms the provided multi-select field to Moodle UI.
 *
 * @param {string} selector
 */
export const init = (selector) => {
  const field = document.querySelector(selector);

  Autocomplete.enhanceField(
    selector,
    false,
    '',
    field.dataset.placeholder || 'Search',
    false,
    true,
    'No selection',
    false
  );
};
