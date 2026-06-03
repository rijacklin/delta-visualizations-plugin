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
 * Models teacher behaviour: Course Performance Feedback
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
class CoursePerformanceFeedback extends TeacherBehaviourPattern
{
  use BarChart;
  use PieChart;

  public function feedback_low_assignment_grade(int $threshold_grade, int $response_period)
  {
    global $DB, $USER;

    $sql = "
      WITH target_students AS (
        select
            ag.id,
            ag.userid AS studentid,
            ag.assignment,
            ag.grade,
            ag.timemodified AS gradeddate
        FROM {assign_grades} ag
        JOIN {assign} assign
          ON assign.id = ag.assignment
        WHERE ag.grade < :gradethreshold
          AND assign.course = :courseid
        ORDER BY ag.userid ASC
      ),
      targeted_feedback AS (
        select 
          afcom.id as feedback_id,
          ts.studentid as student_id,
          afcom.commenttext as feedback_text
        FROM target_students ts
        join {assignfeedback_comments} afcom
          on afcom.assignment = ts.assignment AND afcom.grade = ts.id
      )
      select
        feedback_id,
        student_id,
        feedback_text
      FROM targeted_feedback
      ORDER BY
        feedback_id ASC,
        student_id ASC;
    ";

    $records = $DB->get_records_sql($sql, [
      'gradethreshold' => $threshold_grade,
      'teacherid' => $USER->id,
      'courseid' => 3
    ]);

    $improvement_keywords = ['organization', 'textbook', 'materials', 'effort'];

    $messages = $records;

    $data = new stdClass();

    foreach ($messages as $message) {
      // message properties
      $message_to = $message->student_id;
      $message_text = $message->feedback_text;

      // early exit
      if (empty($message_text)) {
        $data->{$message_to} = ActivityBehaviour::NotExhibited;
        continue;
      }

      // default
      $behaviour = ActivityBehaviour::NotExhibited;

      // check for time_commitment_keywords in message
      foreach ($improvement_keywords as $keyword) {
        if (!empty($keyword) && stripos($message_text, $keyword) !== false) {
          // teacher is exhibiting the behaviour
          $behaviour = ActivityBehaviour::Exhibited;
          break;
        }
      }

      $data->{$message_to} = $behaviour;
    }

    // TODO: REMOVE MOCK DATA
    $data->{203} = ActivityBehaviour::NotExhibited;
    $data->{97} = ActivityBehaviour::Exhibited;
    $data->{67} = ActivityBehaviour::Exhibited;
    $data->{42} = ActivityBehaviour::Exhibited;
    $data->{87} = ActivityBehaviour::NotRequired;
    // END TODO

    return $data;
  }

  public function time_commitment_messaging_low_participation(int $threshold_grade, int $response_period)
  {
    global $DB;

    $sql = "
      WITH ordered_view_logs AS (
        SELECT
          log.id,
          log.userid,
          log.courseid,
          log.contextinstanceid,
          log.eventname,
          log.component,
          log.action,
          log.timecreated,
          LEAD(log.timecreated) OVER (
            PARTITION BY log.userid, log.courseid
            ORDER BY log.timecreated, log.id
          ) AS next_event_time
        FROM m_logstore_standard_log log
        WHERE log.userid IS NOT NULL
      ),
      forum_viewing_time AS (
        WITH forum_view_duration AS (
          SELECT
            id,
            userid,
            courseid,
            contextinstanceid as coursemoduleid,
            component,
            action,
            LEAST(
              -- THRESHOLD: 30 mins (1800 seconds)
              COALESCE(next_event_time - timecreated, 0), 1800
            ) AS estimated_seconds_spent
            FROM ordered_view_logs
            WHERE component = 'mod_forum' AND action = 'viewed'
        )
        SELECT
          id,
          userid,
          courseid,
          SUM(estimated_seconds_spent) AS total_seconds_spent
        FROM forum_view_duration
        GROUP BY id, userid, courseid
        ORDER BY id ASC
      ),
      learning_object_viewing_time AS (
        WITH module_view_duration AS (
          SELECT
            id,
            userid,
            courseid,
            contextinstanceid as coursemoduleid,
            component,
            LEAST(
              -- THRESHOLD: 30 mins (1800 seconds)
              COALESCE(next_event_time - timecreated, 0), :threshold
            ) AS estimated_seconds_spent
            FROM ordered_view_logs
            WHERE eventname IN (
              '\\mod_forum\\event\\course_module_viewed',
              '\\mod_assign\\event\\course_module_viewed',
              '\\mod_resource\\event\\course_module_viewed',
              '\\mod_url\\event\\course_module_viewed',
              '\\mod_page\\event\\course_module_viewed',
              '\\mod_lesson\\event\\course_module_viewed'
            )
        )
        SELECT
          id,
          userid,
          courseid,
          SUM(estimated_seconds_spent) AS total_seconds_spent
        FROM module_view_duration
        GROUP BY id, userid, courseid
        ORDER BY id ASC
      ),
      forum_postings_frequency AS (
        SELECT
          fp.userid,
          c.id as courseid,
          COUNT(fp.id)
        FROM m_forum_posts fp
        JOIN m_forum f
          ON f.id = fp.discussion
        JOIN m_course c
          ON f.course = c.id
        WHERE fp.userid IS NOT null
          AND f.course = :courseid
          -- Filter by semester start date
          AND c.timecreated >= :startdate
        GROUP BY fp.userid, c.id
        ORDER BY fp.userid ASC
      ),
      students_to_message AS (
        select distinct students_to_message
        FROM forum_viewing_time fvt
        LEFT JOIN learning_object_viewing_time lvt
          ON fvt.courseid = lvt.courseid
        LEFT JOIN forum_postings_frequency fpostings
          ON fvt.courseid = fpostings.courseid
        CROSS JOIN LATERAL (
          VALUES (fvt.userid), (lvt.userid), (fpostings.userid)
        ) AS sub(students_to_message)
        WHERE students_to_message IS NOT NULL
          AND fvt.total_seconds_spent >= :forumviewthreshold
          AND lvt.total_seconds_spent >= :loviewthreshold
          AND fpostings.count >= :forumpostthreshold
      ),
      targeted_messages AS (
        select 
          mcm.id,
          stm.students_to_message as studentid,
          m.useridfrom as teacherid,
          m.conversationid,
          m.fullmessage as messagetext,
          m.fullmessagehtml as messagetexthtml,
          m.timecreated,
          m.fullmessage
        FROM students_to_message stm
        join m_message_conversation_members mcm
          on mcm.userid = stm.students_to_message
        JOIN m_messages m
          ON m.conversationid = mcm.conversationid
      )
      select
        id,
        conversationid,
        teacherid,
        studentid,
        messagetext,
        messagetexthtml
      FROM targeted_messages
      ORDER BY
        id ASC,
        conversationid asc,
        teacherid ASC,
        studentid ASC;
    ";

    $records = $DB->get_records_sql($sql, [
      // TODO: Replace with non-hardcoded value
      'courseid' => 3,
      // TODO: Replace with non-hardcoded value
      'threshold' => 1800,
      // TODO: Replace hardcoding with start of given semester?
      'startdate' => 1778540400,
      // TODO: Replace with time; not hardcoded
      'forumviewthreshold' => 1,
      // TODO: Replace with time; not hardcoded
      'loviewthreshold' => 1,
      // TODO: Replace with non-hardcoded value
      'forumpostthreshold' => 1,
    ]);

    $time_commiment_keywords = ['time management', 'organization'];

    $messages = $records;

    $data = new stdClass();

    foreach ($messages as $message) {
      $message_to = $message->studentid;

      // parse html version of message text if available; otherwise fallback to plain text
      if (!empty($message->messagetexthtml)) {
        $message_text = $message->messagetexthtml;
      } else {
        $message_text = $message->messagetext;
      }

      // early exit
      if (empty($message_text)) {
        $data->{$message_to} = $activity_behaviour = ActivityBehaviour::NotExhibited;
        continue;
      }

      // default
      $behaviour = $activity_behaviour = ActivityBehaviour::NotExhibited;

      // check for time_commitment_keywords in message
      foreach ($time_commiment_keywords as $keyword) {
        if (!empty($keyword) && stripos($message_text, $keyword) !== false) {
          // teacher is exhibiting the behaviour
          $behaviour = $activity_behaviour = ActivityBehaviour::Exhibited;
          break;
        }
      }

      $data->{$message_to} = $behaviour;
    }

    return $data;
  }

  public function create_pie_chart(stdClass $activity_behaviour): \core\chart_pie
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

    return $chart;
  }

  public function create_bar_chart(stdClass $activity_behaviour): \core\chart_bar
  {
    $students = [];
    $values = [];

    foreach ($activity_behaviour as $student_id => $state) {
      if ($state === ActivityBehaviour::NotRequired) {
        continue;
      }

      $students[] = (string) $student_id;

      $values[] = match ($state) {
        ActivityBehaviour::Exhibited => 1,
        ActivityBehaviour::NotExhibited => 0,
      };
    }

    $chart = new \core\chart_bar();

    // x-axis
    $chart->set_labels($students);

    // y-axis
    $series = new \core\chart_series('Behaviour Exhibited', $values);
    $chart->add_series($series);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label("Student ID");

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label("Behaviour Exhibited");
    $yaxis->set_min(0);
    $yaxis->set_max(1);
    $yaxis->set_stepsize(1);

    return $chart;
  }
}
