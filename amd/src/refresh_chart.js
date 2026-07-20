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
 * Handles refreshing any charts that have filters changed on the front-end.
 *
 * @module     block_delta_visualizations/refresh_chart
 * @copyright  2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fragment from 'core/fragment';
import Notification from 'core/notification';
import Templates from 'core/templates';
import Chart from 'core/chartjs';

/**
 * Destory instance of Chartjs before their canvas is removed from the DOM
 * during fragment refresh.
 *
 * @param {HTMLElement} chartArea
 */
const destroyCharts = (chartArea) => {
  chartArea.querySelectorAll('canvas').forEach((canvas) => {
    Chart.getChart(canvas)?.destroy();
  });
};

/**
 * Refresh the given chart after filtering behaviour.
 *
 * @param {HTMLFormElement} form
 */
const refreshChart = (form) => {
  // grab the specific behaviour from the form (as there are many behaviours being rendered)
  const behaviour = form.closest('[data-region="behaviour"]');

  // optional chaining because behaviour may not exist (i.e., be null or undefined)
  const chartArea = behaviour?.querySelector('[data-region="behaviour-chart"]');
  const behaviourId = behaviour?.dataset.behaviour;
  const contextId = Number(form.dataset.contextId);

  // structures the behaviour and courses the server will return a chart fragment based on
  const args = {
    behaviourid: behaviourId,
    courseids: form.dataset.courseIds
  };

  // instantiate the form data
  new FormData(form).forEach((value, name) => {
    args[name] = value;
  });

  // create arrays of form controls and their states so they can be restored after AJAX
  const controls = Array.from(form.elements);
  const disabledStates = controls.map((control) => control.disabled);

  // iterate over and disable each control, then set the element to busy
  controls.forEach((control) => {
    control.disabled = true;
  });
  chartArea.setAttribute('aria-busy', 'true');

  // load a fragment from the server
  Fragment.loadFragment(
    'block_delta_visualizations',
    'refresh_chart',
    contextId,
    args
  ).then((html, js) => {
    // on success, replace DOM chart element with the fragment
    destroyCharts(chartArea);
    Templates.replaceNodeContents(chartArea, html, js);
  }).always(() => {
    // always restore the state of each form control and remove aria-busy state
    controls.forEach((control, index) => {
      control.disabled = disabledStates[index];
    });
    chartArea.removeAttribute('aria-busy');
  }).fail((error) => {
    // on error, notify with an exception after restoring the form controls
    Notification.exception(error);
  });
};

/**
 * Initialize chart refreshing for all behaviour filters
 *
 * @param {string} rootSelector
 */
export const init = (rootSelector) => {
  // grab plugin block's root DOM element
  const root = document.querySelector(rootSelector);

  root.dataset.chartRefreshInitialized = 'true';

  // attach a submit event listener to the filter's apply button
  root.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-region="behaviour-filter"]');

    // ignores all other forms on the page
    if (!form || !root.contains(form)) {
      return;
    }

    // prevent default submit button behaviour which is a full-page refresh
    event.preventDefault();

    // only refresh chart if the form controls are valid (i.e., basic validation of the form and not the data being passed)
    if (form.reportValidity()) {
      refreshChart(form);
    }
  });
};
