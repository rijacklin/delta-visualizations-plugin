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
 * Handles the tabs separating teacher behaviours from teacher's view of student
 * behaviours.
 *
 * @module     block_delta_visualizations/tabs
 * @copyright  2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates the tabs in the provided container DOM element.
 *
 * @param {string} containerId
 */
export const init = (containerId) => {
    const container = document.getElementById(containerId);

    if (!container) {
      return;
    }

    const tabs = container.querySelectorAll('[role="tab"]');
    const panels = container.querySelectorAll('[role="tabpanel"]');

    const activate = (tab) => {
      const target = tab.getAttribute('data-target');

      tabs.forEach(t => {
        t.setAttribute('aria-selected', 'false');
        t.classList.remove('active');
      });

      panels.forEach(panel => {
        panel.classList.remove('active', 'show');
        panel.hidden = true;
      });

      tab.setAttribute('aria-selected', 'true');
      tab.classList.add('active');

      const panel = document.getElementById(target);

      if (panel) {
        panel.hidden = false;
        panel.classList.add('active', 'show');
      }
    };

    tabs.forEach(tab => {
      tab.addEventListener('click', (event) => {
        event.preventDefault();
        activate(tab);
      });
    });
};
