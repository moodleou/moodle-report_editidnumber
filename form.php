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
 * report_editidnumber capability definitions.
 *
 * @package   report_editidnumber
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/gradelib.php');
require_once(dirname(__FILE__) . '/lib.php');


/**
 * The form for editing the idnumber settings throughout a course.
 *
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_editidnumber_form extends moodleform {

    public function definition() {
        //global variables
        global $CFG, $COURSE, $DB;
        //get the form reference
        $mform =& $this->_form;
        //fetching $modinfo from the constructor data array
        $modinfo = $this->_customdata['modinfo'];
        //fetching $course from the constructor data array
        $course = $this->_customdata['course'];
        //fetching all the sections in the course
        $sections = get_all_sections($modinfo->courseid);
        //default -1 to display header for 0th section
        $prevsectionnum = -1;
        //context instance of the course
        $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
        // for showing submit buttons
        $showactionbuttons = false;
        //defining array for providing (see above) link from grade items
        //which are associated with course modules
        $mod_gradeitem_map_array = array();
        //cycle through all the sections in the course
        foreach ($modinfo->sections as $sectionnum => $section) {
            //cycle through each module in a section
            foreach ($section as $cmid) {
                $readonlymod = false;
                //fetching  course module object from the $modinfo array
                $cm = $modinfo->cms[$cmid];
                //no need to display/continue if this module is not visible to user.
                if (!$cm->uservisible) {
                    continue;
                }
                //flag to determine availabiltity of id number feature
                $supportsidnumber = false;
                //creating function name for each module
                $function = $cm->modname.'_supports';
                //set flag to true if module support FEATURE_GROUPS
                if (plugin_supports('mod', $cm->modname, FEATURE_IDNUMBER, true)) {
                    $supportsidnumber = true;
                }
                //context instance of the module
                $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
                // check if the user has capability to edit this module settings
                if (!has_capability('moodle/course:manageactivities', $modcontext)) {
                    $readonlymod = true;
                }

                //module should be displayed only if the module supports ID Number feature
                if ($supportsidnumber) {
                    //new section, create header
                    if ($prevsectionnum != $sectionnum) {
                        $sectionname = get_section_name($course, $sections[$sectionnum]);
                        $mform->addElement('header', $sectionname, $sectionname);
                        $prevsectionnum = $sectionnum;
                    }
                    //var to store element name
                    $elname = '';
                    //fetching activity name with <h3> tag.
                    $stractivityname = html_writer::tag('h3', $cm->name, array('id' => null,
                         'class' => renderer_base::prepare_classes('main')));
                    $elname = 'idnumber[cm]['.$cm->id.']';
                    //creating element id
                    $elid = 'idnumber_cm_'.$cm->id;
                    //set show submit button to true
                    $showactionbuttons = true;
                    // creating array for providing (see above) link from grade
                    //items which are associated with course module
                    $mod_gradeitem_map_array[$cm->modname."-".$cm->instance] = "#id_".$elid;
                    //display activity name on the form
                    $mform->addElement('static', 'modname', $stractivityname);
                    if ($readonlymod) {
                        //for creating an element with id for see above link in grade items
                        $mform->addElement('html', "<span id=id_$elid></span>");
                    }
                    //element to display ID number
                    $mform->addElement('text', $elname, get_string('idnumbermod'),
                             array("id" => $elid), array("id" => $elid));
                    //help button
                    $mform->addHelpButton($elname, 'idnumbermod');
                    //if module has a default id number, it should be displayed
                    if (isset($cm->idnumber)) {
                        $mform->setDefault($elname, $cm->idnumber);
                    }
                    //if user is not capable, the element should be read-only
                    if ($readonlymod) {
                        $mform->hardFreeze($elname);
                    }
                }
            }
        }

        //check if user has capability to upgrade/manage grades
        $readonlygrades = false;
        if (!has_capability('moodle/grade:manage', $coursecontext)) {
            $readonlygrades = true;
        }
        //fetching Gradebook items
        $gradeitems = grade_item::fetch_all(array('courseid' => $course->id));
        // course module will be always fetched,
        // so lenghth will always be 1 if no gread item is fetched
        if (is_array($gradeitems) && (count($gradeitems) >1)) {
            //sort grade item array on the basis of 'sortorder'
            usort($gradeitems, 'report_editidnumber_sort_array_by_sortorder');
            //var to store element name
            $elname = '';
            //section to display Gradebook ID Numbers
            $mform->addElement('header', get_string('gradebookidnumbersunderscore',
                     'report_editidnumber'));
            //fetching activity name with <h3> tag.
            $strgradebookheader = html_writer::tag('h3', get_string('gradebookidnumbers',
                     'report_editidnumber'));
            //display gradebook id header
            $mform->addElement('static', 'gradebookitems', $strgradebookheader);
            //looping through all grade items
            foreach ($gradeitems as $gradeitem) {
                //skip course grade item
                if ($gradeitem->itemtype == "course") {
                    continue;
                }
                //set show submit button to true
                $showactionbuttons = true;
                //creating id numbers element's name
                $elname = "idnumber[gi][$gradeitem->id]";
                //add element to display grade item
                if ($gradeitem->itemtype == "mod" ) {
                    $cm_id_link = $mod_gradeitem_map_array[$gradeitem->itemmodule."-".
                                $gradeitem->iteminstance];
                    $mform->addElement('static', $elname, $gradeitem->itemname);
                    $mform->setDefault($elname, "<a href=$cm_id_link >See above</a>");
                } else if ($gradeitem->itemtype == "category") {
                    // in case of itemtype category,
                    // fetching the category fullname from grade_categories table
                    $grade_category = $DB->get_record("grade_categories",
                             array("id" => $gradeitem->iteminstance));
                    $mform->addElement('text', $elname, $grade_category->fullname);
                    $mform->setDefault($elname, $gradeitem->idnumber);
                } else {
                    $mform->addElement('text', $elname, $gradeitem->itemname);
                    $mform->setDefault($elname, $gradeitem->idnumber);
                }
                if ($readonlygrades) {
                    // if user does not has capability to
                    // upgrade/manage grades then do not display textbox
                    $mform->hardFreeze($elname);
                }
            }

        }
        //adding submit/cancel buttons @ the end of the form
        if ($showactionbuttons) {
            $this->add_action_buttons();
        } else {
            // <div> is used for center align the continue link
            $continue_url = new moodle_url('/course/view.php', array('id' => $course->id));
            $mform->addElement('html', "<div style=text-align:center><a href=".
                $continue_url."><b>[Continue]</b></a></div>");
        }
    }

    /// perform some extra moodle validation
    public function validation($data, $files) {

        global $CFG;
        $errors = parent::validation($data, $files);

        //get the form reference
        $mform =& $this->_form;

        $errors = array();
        $cm_idnumbers = $data['idnumber']['cm']; // array for course module id numbers
        if (!empty($cm_idnumbers)) {
            $tmp_array = array();
            $tmp_array = $cm_idnumbers;
            foreach ($cm_idnumbers as $key => $value) {
                if (empty($value)) {
                    continue; // if idnumber is blank then no need to check
                }
                unset($tmp_array[$key]); // removing first occurence of the key
                while ($duplicate_value_key = array_search($value, $tmp_array)) {
                    // searching existence of the current key

                    $elname = "idnumber[cm][$duplicate_value_key]";

                    // showing error on subsequent duplicate values.
                    if ($mform->isElementFrozen($elname)) {
                        // if the 2nd duplicate value found is frozen,
                        // then show error on first occurrence
                        $errors["idnumber[cm][$key]"]= get_string('idnumbertaken',
                                 'report_editidnumber');
                    } else {
                        // if duplicate is not frozen show error on it.
                        $errors[$elname]= get_string('idnumbertaken', 'report_editidnumber');
                    }
                    unset($tmp_array[$duplicate_value_key]);
                }
            }
        }
        // array for grade items id numbers
        $gi_idnumbers = isset($data['idnumber']['gi']) ? $data['idnumber']['gi'] : "";

        if (!empty($gi_idnumbers)) {
            $tmp_array = array();
            $tmp_array = $gi_idnumbers;
            foreach ($gi_idnumbers as $key => $value) {
                if (empty($value)) {
                    continue; // if idnumber is blank then no need to check
                }
                // if idnumber is already assigned to any course module
                if (array_search($value, $cm_idnumbers)) {
                    $errors["idnumber[gi][$key]"]= get_string('idnumbertaken',
                             'report_editidnumber');
                }
                unset ($tmp_array[$key]); // removing first occurence of the key
                 // searching existence of the current key
                while ($duplicate_value_key = array_search($value, $tmp_array)) {
                    $errors["idnumber[gi][$duplicate_value_key]"]= get_string('idnumbertaken',
                             'report_editidnumber');
                    unset($tmp_array[$duplicate_value_key]);
                }
            }
        }

        return $errors;
    }
}
