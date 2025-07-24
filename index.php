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
use core\report_helper;

define('REPORT_EDITIDNUMBER_ENABLE_FILTER_THRESHOLD', 50);

$id = required_param('id', PARAM_INT); // Course id.
$activitytype = optional_param('activitytype', '', PARAM_PLUGIN);

// Should be a valid course id.
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_login($course);

// Setup page.
$urlparams = ['id' => $id];
if ($activitytype) {
    $urlparams['activitytype'] = $activitytype;
}
$pageurl = new moodle_url('/report/editidnumber/index.php', $urlparams);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');

// Check permissions.
$coursecontext = context_course::instance($course->id);
require_capability('report/editidnumber:view', $coursecontext);

// Fetching all modules in the course.
$modinfo = get_fast_modinfo($course);
$cms = $modinfo->get_cms();

// Prepare a list of activity types used in this course, and count the number that
// might be displayed.
$activitiesdisplayed = 0;
$activitytypes = [];
foreach ($modinfo->get_sections() as $sectionnum => $section) {
    foreach ($section as $cmid) {
        $cm = $cms[$cmid];

        // Filter activities to those that are relevant to this report.
        if (!$cm->uservisible || !plugin_supports('mod', $cm->modname, FEATURE_IDNUMBER, true)) {
            continue;
        }

        $activitiesdisplayed += 1;
        $activitytypes[$cm->modname] = get_string('modulename', $cm->modname);
    }
}
core_collator::asort($activitytypes);

if ($activitiesdisplayed <= REPORT_EDITIDNUMBER_ENABLE_FILTER_THRESHOLD) {
    $activitytypes = ['' => get_string('all')] + $activitytypes;
}

// If activity count is above the threshold, activate the filter controls.
if (!$activitytype && $activitiesdisplayed > REPORT_EDITIDNUMBER_ENABLE_FILTER_THRESHOLD) {
    reset($activitytypes);
    redirect(new moodle_url('/report/editidnumber/index.php',
            ['id' => $id, 'activitytype' => key($activitytypes)]));
}

// Creating form instance, passed course id as parameter to action url.
$baseurl = new moodle_url('/report/editidnumber/index.php', ['id' => $id]);
$mform = new report_editidnumber_form($baseurl, ['modinfo' => $modinfo,
        'course' => $course, 'activitytype' => $activitytype]);

$returnurl = new moodle_url('/course/view.php', ['id' => $id]);
if ($mform->is_cancelled()) {
    // Redirect to course view page if form is cancelled.
    redirect($returnurl);

} else if ($data = $mform->get_data()) {
    // Process data if submitted, update only if user can manage activities in course context
    // idnumber values from the $data.
    $idnumbers = $data->idnumber;
    // Start transaction.
    $transaction = $DB->start_delegated_transaction();
    // Cycle through all the course modules in the course.
    foreach ($modinfo->get_cms() as $cmid => $cm) {
        // Context instance of the module.
        $modcontext = context_module::instance($cmid);
        // Check if the user has capability to edit this module settings.
        if (has_capability('moodle/course:manageactivities', $modcontext)) {
            // If this id exists in the array received from $mform.
            if (array_key_exists($cmid, $idnumbers['cm'])) {
                $DB->set_field('course_modules', 'idnumber', null, ['id' => $cmid]);
            }
        }
    }

    foreach ($modinfo->get_cms() as $cmid => $cm) {
        // Context instance of the module.
        $modcontext = context_module::instance($cmid);
        // Check if the user has capability to edit this module settings.
        if (has_capability('moodle/course:manageactivities', $modcontext)) {
            // If this id exists in the array received from $mform.
            if (array_key_exists($cmid, $idnumbers['cm'])) {
                $DB->set_field('course_modules', 'idnumber', $idnumbers['cm'][$cmid],
                        ['id' => $cmid]);
                // Sync idnumber with grade_item.
                if ($gradeitem = grade_item::fetch(['itemtype' => 'mod',
                        'itemmodule' => $cm->modname, 'iteminstance' => $cm->instance,
                        'itemnumber' => 0, 'courseid' => $course->id])) {
                    if ($gradeitem->idnumber != $idnumbers['cm'][$cmid]) {
                        $gradeitem->idnumber = $idnumbers['cm'][$cmid];
                        // Update the grade item object.
                        $gradeitem->update();
                    }
                }
            }
        }
    }

    // Checking if grade items exists.
    if (isset($idnumbers['gi']) && has_capability('moodle/grade:manage', $coursecontext)) {
        $gis = $idnumbers['gi'];

        // Cycle through each grade items for setting idnumber to null.
        foreach ($gis as $key => $value) {
            // Setting all idnumbers to null.
            $DB->set_field('grade_items', 'idnumber', null, ['id' => $key]);
        }
        // Cycle through each grade items for setting idnumber.
        foreach ($gis as $key => $value) {
            // Setting idnumbers to its new value.
            $DB->set_field('grade_items', 'idnumber', $value, ['id' => $key]);
        }
    }
    // Commit transaction.
    $transaction->allow_commit();
    rebuild_course_cache($course->id);
    redirect($PAGE->url);
}

// Prepare activity type menu.
$select = new single_select($baseurl, 'activitytype', $activitytypes, $activitytype, null, 'activitytypeform');
$select->set_label(get_string('activitytypefilter', 'report_editidnumber'));
$select->set_help_icon('activitytypefilter', 'report_editidnumber');

// Making log entry.
$event = \report_editidnumber\event\report_viewed::create(
        ['context' => $coursecontext, 'other' => ['activitytype' => $activitytype]]);
$event->trigger();

// Set page title and page heading.
$PAGE->set_title($course->shortname .': '. get_string('editidnumber', 'report_editidnumber'));
$PAGE->set_heading($course->fullname);

// Displaying the form.
echo $OUTPUT->header();

// Print the selected dropdown.
$pluginname = get_string('pluginname', 'report_editidnumber');
report_helper::print_report_selector($pluginname);

echo $OUTPUT->heading(format_string($course->fullname));

echo $OUTPUT->heading(get_string('activityfilter', 'report_editidnumber'));
echo $OUTPUT->render($select);

$mform->display();

echo $OUTPUT->footer();
