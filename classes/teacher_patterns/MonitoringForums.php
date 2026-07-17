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
 * Models teacher behaviour: Monitoring Forums
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\teacher_patterns;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates a renderer for the block_delta_visualizations
 *
 */
class MonitoringForums extends TeacherBehaviourPattern
{
  use PieChart;

  public function query_behaviour_data(array $params)
  {
    global $DB, $USER;

    if (empty($params['courseids'])) {
      return [];
    }

    [$courseidssql, $courseidsparams] = $DB->get_in_or_equal(
      $params['courseids'],
      SQL_PARAMS_NAMED,
      'courseid'
    );

    $sql = "
      WITH teacher_response AS (
        select
          reply.id as post_id,
          fd.id AS discussion_id
        FROM {forum_discussions} fd
        JOIN {forum_posts} studentpost
          ON studentpost.discussion = fd.id
        JOIN {forum_posts} reply
          ON reply.parent = studentpost.id
        WHERE fd.course $courseidssql
          AND reply.userid = :userid
        ORDER BY fd.id ASC
      )
      SELECT
        post_id,
        fp.message
      FROM teacher_response tr
      JOIN {forum_posts} fp
        ON tr.discussion_id = fp.discussion
      WHERE fp.parent = tr.post_id
      ORDER BY post_id ASC
    ";

    $records = $DB->get_records_sql($sql, [
      'userid' => $USER->id,
    ] + $courseidsparams);

    // store records
    $this->records = $records;

    $data = new stdClass();

    $improvement_keywords = ['thanks', 'thank you', 'appreciate', 'that worked'];

    $posts = $records;

    $data = new stdClass();

    foreach ($posts as $post) {
      // message properties
      $post_from = "";
      $post_text = $post->message;

      // early exit
      if (empty($post_text)) {
        $data->{$post_from} = ActivityBehaviour::NotExhibited;
        continue;
      }

      // default
      $behaviour = ActivityBehaviour::NotExhibited;

      // check for time_commitment_keywords in message
      foreach ($improvement_keywords as $keyword) {
        if (!empty($keyword) && stripos($post_text, $keyword) !== false) {
          // teacher is exhibiting the behaviour
          $behaviour = ActivityBehaviour::Exhibited;
          break;
        }
      }

      $data->{$post_from} = $behaviour;
    }

    return $data;
  }

  public function create_pie_chart(stdClass $activity_behaviour): void
  {
    $exhibited = 0;
    $not_exhibited = 0;
    $not_required = 0;

    foreach ($activity_behaviour as $state) {
      switch ($state) {
        case ActivityBehaviour::Exhibited:
          $exhibited++;
          break;

        case ActivityBehaviour::NotExhibited:
          $not_exhibited++;
          break;

        case ActivityBehaviour::NotRequired:
          $not_required++;
          break;
      }
    }

    $chart = new \core\chart_pie();
    $chart->set_labels([
      ActivityBehaviour::Exhibited->label(),
      ActivityBehaviour::NotExhibited->label(),
      ActivityBehaviour::NotRequired->label(),
    ]);

    $series_behaviour = new \core\chart_series('Behaviour Exhibited', [
      $exhibited,
      $not_exhibited,
      $not_required,
    ]);

    $chart->add_series($series_behaviour);

    $this->chart = $chart;
  }

  public function generate_behaviour_pie_chart(array $params): void
  {
    if (!empty($params['courseids'])) {
      $behaviour_data = $this->query_behaviour_data($params);
      $this->create_pie_chart($behaviour_data);
    }
  }
}
