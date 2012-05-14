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
 * Script to let users edit idnumbers throughout a course.
 *
 * @package   report_editidnumber
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once(dirname(__FILE__) . '/form.php');

$id = required_param('id', PARAM_INT);       // course id

//should be a valid course id
if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}

// needed to setup proper $COURSE
require_login($course);

//setting page url
$PAGE->set_url('/report/editidnumber/index.php', array('id' => $id));
//setting page layout to report
$PAGE->set_pagelayout('report');

//coursecontext instance
$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

//checking if user is capable of viewing this report in $coursecontext
require_capability('report/editidnumber:view', $coursecontext);

//strings
$stridnumberreport = get_string('editidnumber' , 'report_editidnumber');

//fetching all modules in the course
$modinfo = get_fast_modinfo($course);

//creating form instance, passed course id as parameter to action url
$mform = new report_editidnumber_form( new moodle_url('index.php', array('id' => $id)),
            array('modinfo' => $modinfo, 'course' => $course));
//create the return url after form processing
$returnurl = new moodle_url('/course/view.php', array('id' => $id));
if ($mform->is_cancelled()) {
    //proper redirection if form is cancelled
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    //process data if submitted
    //update only if user can manage activities in course context
    //idnumber values from the $data
    $idnumbers = $data->idnumber;
    //start transaction
    $transaction = $DB->start_delegated_transaction();
    //cycle through all the course modules in the course
    foreach ($modinfo->cms as $cmid => $cm) {
        // context instance of the module
        $modcontext = get_context_instance(CONTEXT_MODULE, $cmid);
        // check if the user has capability to edit this module settings
        if (has_capability('moodle/course:manageactivities', $modcontext)) {
            //if this id exists in the array received from $mform
            if (array_key_exists($cmid, $idnumbers['cm'])) {
                $DB->set_field('course_modules', 'idnumber', null, array('id' => $cmid));
            }
        }
    }

    foreach ($modinfo->cms as $cmid => $cm) {
        // context instance of the module
        $modcontext = get_context_instance(CONTEXT_MODULE, $cmid);
        // check if the user has capability to edit this module settings
        if (has_capability('moodle/course:manageactivities', $modcontext)) {
            //if this id exists in the array received from $mform
            if (array_key_exists($cmid, $idnumbers['cm'])) {
                $DB->set_field('course_modules', 'idnumber', $idnumbers['cm'][$cmid],
                        array('id' => $cmid));
            }
            // sync idnumber with grade_item
            if ($grade_item = grade_item::fetch(array('itemtype'=>'mod',
                 'itemmodule'=>$cm->modname, 'iteminstance'=>$cm->instance,
                     'itemnumber'=>0, 'courseid' => $course->id))) {
                if ($grade_item->idnumber != $idnumbers['cm'][$cmid]) {
                    $grade_item->idnumber = $idnumbers['cm'][$cmid];
                    //update the grade item object
                    $grade_item->update();
                }
            }
        }
    }

    // checking if grade items exists
    if (isset($idnumbers['gi']) && has_capability('moodle/grade:manage', $coursecontext)) {
        $gis = $idnumbers['gi'];

        //cycle through each grade items for setting idnumber to null
        foreach ($gis as $key => $value) {
                // setting all idnumbers to null
                $DB->set_field('grade_items', 'idnumber', null, array('id' => $key));
        }
        //cycle through each grade items for setting idnumber
        foreach ($gis as $key => $value) {
                // setting idnumbers to its new value
                $DB->set_field('grade_items', 'idnumber', $value, array('id' => $key));
        }
    }
    //commit transaction
    $transaction->allow_commit();
    rebuild_course_cache($course->id);

    //redirect to course view page after updating DB
    redirect($returnurl);
}

//making log entry
add_to_log($course->id, 'course', 'report id number',
     "report/editidnumber/index.php?id=$course->id", $course->id);

//setting page title and page heading
$PAGE->set_title($course->shortname .': '. $stridnumberreport);
$PAGE->set_heading($course->fullname);

//Displaying header and heading
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($course->fullname));

//display form
$mform->display();

echo $OUTPUT->footer();
