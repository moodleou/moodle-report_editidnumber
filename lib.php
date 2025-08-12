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
 * Library functions for report_editgroups.
 *
 * @package   report_editidnumber
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_editidnumber_extend_navigation_course($navigation, $course, $context) {
    global $CFG, $OUTPUT;
    if (has_capability('report/editidnumber:view', $context)) {
        $url = new moodle_url('/report/editidnumber/index.php', ['id' => $course->id]);
        if ($activitytype = optional_param('activitytype', '', PARAM_PLUGIN)) {
            $url->param('activitytype', $activitytype);
        }
        $navigation->add(get_string( 'editidnumber', 'report_editidnumber' ),
                $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 * @return array
 */
function report_editidnumber_page_type_list($pagetype, $parentcontext, $currentcontext) {
    return [
        '*'                         => get_string('page-x', 'pagetype'),
        'report-*'                  => get_string('page-report-x', 'pagetype'),
        'report-editidnumber-index' => get_string('page-report-editidnumber-index',  'report_editidnumber'),
    ];
}

/**
 * This function is called by 'usort' method to sort objects in array by property 'sortorder'
 *
 * @param grade_item $item1 object 1 to compare
 * @param grade_item $item2 object 2 to compare with object 1
 */
function report_editidnumber_sort_array_by_sortorder($item1, $item2) {
    if (!$item1->sortorder || !$item2->sortorder) {
        return 0;
    }
    if ($item1->sortorder == $item2->sortorder) {
        return 0;
    }
    return ($item1->sortorder > $item2->sortorder) ? 1 : -1;
}
