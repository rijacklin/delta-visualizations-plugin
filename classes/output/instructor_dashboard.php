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
 * Defines renderable instructor dashboard.
 *
 * @package     block_delta_visualizations
 * @copyright   2026 Richard Jacklin <rijacklin1@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_delta_visualizations\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;

class instructor_dashboard implements renderable, templatable
{
  private $filter;      //true if filters are current set for this block
  private $filtersEnabled;      //true if filters are current set for this block

  /**
   * Constructor.
   *
   * @param: $filter (patternset_filter) - the filter object being used to trim the patternset data
   * @return: None
   */
  public function __construct($filter)
  {
    $this->filter = $filter;
  }

  /**
   * Returns the output data ready for the mustache page
   *
   * @param $output: renderer_base object
   * @return $data: (stdClass) object that holds the data
   */
  public function export_for_template(renderer_base $output): stdClass
  {
    $data = new stdClass();

    ////create the pattern selection range
    //$today = \core\di::get(\core\clock::class)->now()->gettimestamp();
    //$prevdate = \core\di::get(\core\clock::class)->now()->modify('-90 day')->getTimestamp();
    //$data->patternsetstartdate = $prevdate;
    //$data->patternsetenddate = $today;

    ////create the output needed for the filter widget
    //$data->patternsetfilter = $this->filter->render();

    ////create a "header" section on the page - this is always expanded content
    //$data->filtertext = $this->block_delta_visualizations_generate_filtercontent();

    //build the patternset sections for the template
    // $data->patternsetlist = $this->block_delta_visualizations_build_patternset_output();

    $data->instructor_dashboard = $this->block_delta_visualizations_build_patternset_output();

    return $data;
  }

  /*
     * Get the current filter values to display them on the page
     *
     * @param: none.
     * @return : (string) the text that represents the filter content to display; 
     *               formatted correctly for the patternsetmain mustache template.
    */
  private function block_delta_visualizations_generate_filtercontent(): string
  {
    ////store the filter state for later so we can use it when generating tabs
    //$this->filtersEnabled = $this->filter->block_delta_visualizations_isfiltering();

    ////get filter text to display on form
    //$filtertext = $this->filter->block_delta_visualizations_get_filters_for_mustache();
    //return $filtertext;

    return "";
  }

  /**
   ** prepare the main patternset output area for the template
   *
   * @param: none.
   * @return : $patterndata (array) data that represents the patternset content to display; 
   *               formatted correctly for the patternsetmain mustache template.
   **/
  private function block_delta_visualizations_build_patternset_output(): array
  {
    //create a data array to hold all the output elements
    $data = [];

    //add the pattern widgets to the output data
    // $patternsetentries = $this->controller->block_delta_visualizations_get_patternsets();
    // $templist = [];
    // $temppos = 0;
    // foreach ($patternsetentries as $patternsetentry) {
    //   $tempcode = $patternsetentry['code'];
    //   $templist[$temppos]['patternsetid'] = $tempcode;
    //   $temptitle = $patternsetentry['title'];
    //   $temppatternset = $patternsetentry['builder']->block_delta_visualizations_get_patternset_node();
    //   //get the output details for the patternset
    //   $patternsetoutput = $temppatternset->block_delta_visualizations_get_patternset_for_mustache();
    //
    //   //check for an empty patternset first
    //   if ($temppatternset->block_delta_visualizations_patternset_isempty() == true) {
    //     //pattern has no data so use the empty pattern details provided by the builder
    //     $emoji = $patternsetoutput['emoji'];
    //     $templist[$temppos]['emptypatternset']['emoji'] = $this->block_delta_visualizations_convert_emoji($emoji);
    //     $templist[$temppos]['emptypatternset']['content'] = $patternsetoutput['content'];
    //     $templist[$temppos]['emptypatternset']['patternsetname'] =
    //       get_string('nodata', 'block_delta_visualizations') . $temptitle;
    //   } else {
    //     //check if this pattern should be inside a collapsed view
    //     //  pattern at position 0 may be an always open set, all others are collapsed
    //     if (($temppos == 0) && ($this->hasopenpatternset == true)) {
    //       //pattern is always open
    //       $collapsedview = 'openpatternset';
    //     } else {
    //       //pattern is initially closed
    //       $collapsedview = 'closedpatternset';
    //     }
    //     //create the patternset tabs based on the patterns in the patternset
    //     $templist[$temppos][$collapsedview]['showtabsnavigation'] = 1;   //triggers the tabs to be "live"
    //     $templist[$temppos][$collapsedview]['tabs'] =
    //       $this->block_delta_visualizations_build_pattern_tabs($temppatternset, $tempcode);
    //     //generate a report button to see the details
    //     $templist[$temppos][$collapsedview]['patternsetreportbutton'] = $this->block_delta_visualizations_generate_report_button($tempcode);
    //     if ($temppatternset->block_delta_visualizations_patternset_isfiltered() == true) {
    //       $temptitle = $temptitle . get_string('filtered', 'block_delta_visualizations');
    //     }
    //     $templist[$temppos][$collapsedview]['patternsetname'] = $temptitle;
    //   }
    //   $temppos++;
    // }
    // //translate the resulting pattern list into a proper array
    // //  so mustache can iterate over it  -- the mustache template won't work if it isn't a proper array
    // $patterndata = [];
    // $patterndata = array_values($templist);
    // return $patterndata;
    // $tabs = block_delta_visualizations_build_pattern_tabs($temppatternset, string $patternsetid);

    $tabs = $this->block_delta_visualizations_build_pattern_tabs();

    return ["heading" => "TITLE", "content" => "TEST CONTENT", $tabs];
  }

  /**
   ** prepare the output data for pattern tabs
   **
   * @param: $temppatternset (patternset_builder) object holding the patternset information
   * @param: $patternsetid (string) - the key that represents this patternset type
   * @return : an array of data that represents the patternset content to display; 
   *               formatted correctly for the patternsetmain mustache template.
   **/
  // private function block_delta_visualizations_build_pattern_tabs($temppatternset, string $patternsetid): array
  private function block_delta_visualizations_build_pattern_tabs(): array
  {
    global $OUTPUT;

    $tabs = [
      ["id" => "tab1", "name" => "Instructor", "content" => "This is tab 1 content <a href=\"#\">test</a>"],
      ["id" => "tab2", "name" => "Instructor-Student", "content" => "This is tab 1 content <a href=\"#\">test</a>"],
    ];

    return $tabs;

    ////if filters on -- generate a new tab shortname
    //if ($this->filtersEnabled == true) {
    //
    //  //set a specific filter value for the tab key 
    //  $filterkey = 'F';
    //} else {
    //  $filterkey = '';
    //}
    ////get the individual patterns stored in this pattern set
    //$temppatterns = $temppatternset->block_delta_visualizations_get_patterns();
    //
    ////loop through the patterns and put each pattern on its own individual tab        
    //$temptabs = [];
    //$temppos = 0;
    //$tabisvisible = true;
    //
    ////for each individual pattern returned, add it to the temp tabs list
    //foreach ($temppatterns as $temppattern) {
    //  //get the individual patterns to be displayed
    //  $tempcontent = $temppattern->block_delta_visualizations_get_pattern_for_mustache();
    //  //convert the emoji defined for the empty pattern to a proper icon
    //  $altemoji = $tempcontent['altemoji'];
    //  $tempcontent['altemoji'] = $this->block_delta_visualizations_convert_emoji($altemoji);
    //  if ($temppos > 0) {
    //    //if not the main pattern in the set, tab is hidden initially
    //    $tabisvisible = false;
    //  }
    //
    //  $temptabs[$temppos] = [
    //    'shortname' => $patternsetid . '_' . $temppos . $filterkey,
    //    'displayname' => $temppattern->block_delta_visualizations_get_patternkey(),
    //    'active' => $tabisvisible,
    //    'enabled' => true,
    //    'patterncontent' => $tempcontent,
    //  ];
    //
    //  $temppos++;
    //}
    //
    ////return the prepared tab data
    //return $temptabs;
  }

  /*
     * A helper function to translate the emoji text into an appropriate mustache output string
     *
     * @param: $emojiname (string) - the text that refers to the emoji.
     * @return: the rendered icon formatted for output
    */
  private function block_delta_visualizations_convert_emoji(string $emojiname)
  {
    global $OUTPUT;
    if ($emojiname == 'sad') {
      //return the picture for sad
      return $OUTPUT->pix_icon('s/sad', 'Ooops..'); //'s/sad, core, Not Good';
    } else if ($emojiname == 'happy') {
      //return the picture for happy
      return $OUTPUT->pix_icon('s/biggrin', 'Excellent'); //'s/biggrin, core, Excellent';
    } else if ($emojiname == 'neutral') {
      //return a mixed emoji
      return $OUTPUT->pix_icon('s/mixed', 'Average'); //'s/mixed, core, Excellent';
    } else {
      return $emojiname;
    }
  }
}
