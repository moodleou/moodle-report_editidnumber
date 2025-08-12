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

    #[\Override]
    public function definition() {
        global $CFG, $COURSE, $DB;
        $mform = $this->_form;

        $modinfo       = $this->_customdata['modinfo'];
        $course        = $this->_customdata['course'];
        $activitytype  = $this->_customdata['activitytype'];

        // Context instance of the course.
        $coursecontext = context_course::instance($course->id);

        // For showing submit buttons.
        $showactionbuttons = false;

        // Defining array for providing (see above) link from grade items
        // which are associated with course modules.
        $modgradeitemmap = [];

        // Store current activity type.
        $mform->addElement('hidden', 'activitytype', $activitytype);
        $mform->setType('activitytype', PARAM_PLUGIN);

        // Add save action button to the top of the form.
        $this->add_action_buttons();

        // Default -1 to display header for 0th section.
        $prevsectionnum = -1;

        // Cycle through all the sections in the course.
        $cms = $modinfo->get_cms();
        foreach ($modinfo->get_sections() as $sectionnum => $section) {
            // Cycle through each module in a section.
            foreach ($section as $cmid) {
                $cm = $cms[$cmid];

                // No need to display/continue if this module is not visible to user.
                if (!$cm->uservisible) {
                    continue;
                }

                // If activity filter is on, then filter module by activity type.
                if ($activitytype && $cm->modname != $activitytype) {
                    continue;
                }

                // Flag to determine availabiltity of id number feature.
                $supportsidnumber = plugin_supports('mod', $cm->modname, FEATURE_IDNUMBER, true);

                // Check if the user has capability to edit this module settings.
                $modcontext = context_module::instance($cm->id);
                $readonlymod = !has_capability('moodle/course:manageactivities', $modcontext);

                // Module should be displayed only if the module supports ID Number feature.
                if ($supportsidnumber) {
                    // New section, create header.
                    if ($prevsectionnum != $sectionnum) {
                        $sectionname = get_section_name($course, $modinfo->get_section_info($sectionnum));
                        $headername = 'section' . $sectionnum . 'header';
                        $mform->addElement('header', $headername, $sectionname);
                        $mform->setExpanded($headername, false);
                        $prevsectionnum = $sectionnum;
                    }
                    // Var to store element name.
                    $elname = '';

                    // Display activity name.
                    $iconmarkup = html_writer::empty_tag('img', [
                            'src' => $cm->get_icon_url(), 'class' => 'activityicon', 'alt' => '']);
                    $stractivityname = html_writer::tag('strong' , $iconmarkup . $cm->name,
                            ['id' => null, 'class' => renderer_base::prepare_classes('main')]);

                    $elname = 'idnumber[cm]['.$cm->id.']';
                    // Creating element id.
                    $elid = 'idnumber_cm_'.$cm->id;
                    // Set show submit button to true.
                    $showactionbuttons = true;
                    // Creating array for providing (see above) link from grade
                    // items which are associated with course module.
                    $modgradeitemmap[$cm->modname."-".$cm->instance] = "#id_".$elid;

                    // Display activity name on the form.
                    $mform->addElement('static', 'modname', $stractivityname);

                    // For creating an element with id for see above link in grade items.
                    $mform->addElement('html', "<span id=\"id_$elid\"></span>");

                    // Element to display ID number.
                    $mform->addElement('text', $elname, get_string('idnumbermod'),
                             ["id" => $elid], ["id" => $elid]);
                    $mform->setType($elname, PARAM_RAW);
                    $mform->addHelpButton($elname, 'idnumbermod');
                    if (isset($cm->idnumber)) {
                        $mform->setDefault($elname, $cm->idnumber);
                    }
                    if ($readonlymod) {
                        $mform->hardFreeze($elname);
                    }
                }
            }
        }

        // Check if user has capability to upgrade/manage grades.
        $readonlygrades = !has_capability('moodle/grade:manage', $coursecontext);

        // Fetching Gradebook items.
        $gradeitems = grade_item::fetch_all(['courseid' => $course->id]);

        // Course module will be always fetched,
        // so lenghth will always be 1 if no gread item is fetched.
        if (is_array($gradeitems) && (count($gradeitems) > 1)) {
            usort($gradeitems, 'report_editidnumber_sort_array_by_sortorder');

            // Section to display Gradebook ID Numbers.
            $mform->addElement('header', 'gradebookitemsheader',
                    get_string('gradebookidnumbers', 'report_editidnumber'));
            $mform->setExpanded('gradebookitemsheader', false);

            // Looping through all grade items.
            foreach ($gradeitems as $gradeitem) {
                // Skip course grade item.
                if ($gradeitem->itemtype == "course") {
                    continue;
                }
                // Set show submit button to true.
                $showactionbuttons = true;
                // Creating id numbers element's name.
                $elname = "idnumber[gi][$gradeitem->id]";
                // Add element to display grade item.
                if ($gradeitem->itemtype == "mod" ) {
                    if (array_key_exists($gradeitem->itemmodule."-".$gradeitem->iteminstance,
                            $modgradeitemmap)) {
                        $cmurl = $modgradeitemmap[$gradeitem->itemmodule."-".
                                    $gradeitem->iteminstance];
                        $mform->addElement('static', $elname, $gradeitem->itemname);
                        $mform->setDefault($elname, "<a href=\"$cmurl\">See above</a>");
                    }
                } else if ($gradeitem->itemtype == "category") {
                    // In case of itemtype category,
                    // fetching the category fullname from grade_categories table.
                    $gradecategory = $DB->get_record("grade_categories",
                             ["id" => $gradeitem->iteminstance]);
                    $mform->addElement('text', $elname, $gradecategory->fullname);
                    $mform->setType($elname, PARAM_RAW);
                    $mform->setDefault($elname, $gradeitem->idnumber);
                } else {
                    $mform->addElement('text', $elname, $gradeitem->itemname);
                    $mform->setType($elname, PARAM_RAW);
                    $mform->setDefault($elname, $gradeitem->idnumber);
                }
                if ($readonlygrades) {
                    // If user does not has capability to
                    // upgrade/manage grades then do not display textbox.
                    $mform->hardFreeze($elname);
                }
            }
        }

        // Adding submit/cancel buttons @ the end of the form.
        if ($showactionbuttons) {
            $this->add_action_buttons();
        } else {
            // Remove top action button.
            $mform->removeElement('buttonar');
        }
    }

    #[\Override]
    // Perform some extra moodle validation.
    public function validation($data, $files) {

        global $CFG;
        $errors = parent::validation($data, $files);

        // Get the form reference.
        $mform =& $this->_form;

        // If activity filtering is enabled, then the hidden idnumber fields need to be moved
        // into the appropriate data structure expected in the chunk of code below
        // to allow validation to happen.
        foreach ($data as $key => $value) {
            if (strpos($key, 'idnumber_cm_') !== false) {
                $keyparts = explode('_', $key);
                $idnumbercmvalue = (int) $keyparts[2];
                if (! array_key_exists($idnumbercmvalue, $data['idnumber']['cm'])) {
                    $data['idnumber']['cm'][$idnumbercmvalue] = $value;
                }
            }
        }
        ksort($data['idnumber']['cm']);

        $errors = [];
        $cmidnumbers = $data['idnumber']['cm']; // Array for course module id numbers.
        if (!empty($cmidnumbers)) {
            $possibleduplicates = $cmidnumbers;
            foreach ($cmidnumbers as $key => $value) {
                if (empty($value)) {
                    continue; // If idnumber is blank then no need to check.
                }

                // This check  highlights all form fields that have a duplicate key.
                unset($possibleduplicates[$key]); // Removing first occurrence of the key for checking.
                $isduplicatefound = false;
                while ($duplicatevaluekey = array_search($value, $possibleduplicates)) {
                    // Searching all existence of the current key.
                    $elname = "idnumber[cm][$duplicatevaluekey]";
                    if (!$mform->isElementFrozen($elname)) {
                        $errors[$elname] = get_string('idnumbertaken', 'report_editidnumber');
                    }
                    unset($possibleduplicates[$duplicatevaluekey]);
                    $isduplicatefound = true;
                }
                if ($isduplicatefound) {
                    // Add back first occurence of the duplicate key.
                    $elname = "idnumber[cm][$key]";
                    if (!$mform->isElementFrozen($elname)) {
                        $errors[$elname] = get_string('idnumbertaken', 'report_editidnumber');
                    }
                }
            }
        }

        // Array for grade items id numbers.
        $giidnumbers = isset($data['idnumber']['gi']) ? $data['idnumber']['gi'] : "";
        if (!empty($giidnumbers)) {
            $possibleduplicates = $giidnumbers;
            foreach ($giidnumbers as $key => $value) {
                if (empty($value)) {
                    continue; // If idnumber is blank then no need to check.
                }
                // If idnumber is already assigned to any course module.
                if (array_search($value, $cmidnumbers)) {
                    $errors["idnumber[gi][$key]"] = get_string('idnumbertaken',
                             'report_editidnumber');
                }
                unset($possibleduplicates[$key]); // Removing first occurrence of the key.
                // Searching existence of the current key.
                while ($duplicatekey = array_search($value, $possibleduplicates)) {
                    $errors["idnumber[gi][$duplicatekey]"] = get_string('idnumbertaken',
                             'report_editidnumber');
                    unset($possibleduplicates[$duplicatekey]);
                }
            }
        }

        return $errors;
    }
}
