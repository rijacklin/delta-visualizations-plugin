<?php

namespace block_delta_visualizations\form;

// moodleform is defined in formslib.php
require_once("$CFG->libdir/formslib.php");

class CourseSelectForm extends \moodleform
{
  // Add elements to form.
  public function definition()
  {
    global $USER;

    // A reference to the form is stored in $this->form.
    // A common convention is to store it in a variable, such as `$mform`.
    $mform = $this->_form; // Don't forget the underscore!

    $mform->addElement('text', 'name', get_string('courseselecttitle', 'block_delta_visualizations'));
    $mform->setType('name', PARAM_RAW);

    // #TODO: Can this be passed here instead of making another DB call?
    $courses = \enrol_get_users_courses($USER->id, true);
    $options = ['multiple' => true, 'size' => 3];

    $mform->addElement('select', 'courseids', 'test', $courses, $options);
    $mform->setDefault('courseids', 'Select one or more courses');
    $mform->setType('courseids', PARAM_RAW);
  }

  // Custom validation should be added here.
  function validation($data, $files)
  {
    return [];
  }
}
