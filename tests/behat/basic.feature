@ou @ou_vle @report @report_editidnumber
Feature: Edit course plugin ID numbers
    When a user view edit ID number report
    They can change the plugn dID number

    Background: Setup course and sample plugins
        Given the following "users" exist:
            | username | firstname | lastname | email |
            | teacher1 | Teacher | 1 | teacher1@asd.com |
            | student1 | Student | 1 | student1@asd.com |
            | student2 | Student | 2 | student2@asd.com |
            | student3 | Student | 3 | student3@asd.com |
            | student4 | Student | 4 | student4@asd.com |
        And the following "courses" exist:
            | fullname | shortname | category |
            | Course 1 | C1 | 0 |
        And the following "course enrolments" exist:
            | user | course | role |
            | teacher1 | C1 | editingteacher |
            | student1 | C1 | student |
            | student2 | C1 | student |
            | student3 | C1 | student |
            | student4 | C1 | student |
        And I log in as "teacher1"
        And I follow "Course 1"
        And I turn editing mode on
        And I add a "Quiz" to section "1" and I fill the form with:
          | Name | Test quiz name 1 |
          | Description | Test forum description |
        And I add a "Quiz" to section "2" and I fill the form with:
          | Name | Test quiz name 2 |
          | Description | Test forum description |
        And I add a "Quiz" to section "3" and I fill the form with:
          | Name | Test quiz name 3 |
          | Description | Test forum description |
        Given I log out

    @javascript @_switch_iframe
    Scenario: Test edit ID number report can be used to change plugin instance ID numbers
        When I log in as "admin"
        And I follow "Course 1"
        And I navigate to "ID numbers" node in "Course administration > Reports"
        And I follow "ID numbers"
        Then I should see "Course 1"
        And I should see "Activity view filter "
        And I follow "Expand all"
        And I should see "Test quiz name 1"
        And I should see "Test quiz name 2"
        And I should see "Test quiz name 3"
        # In ouvle, the three quizzes get cmids 2, 3, 4 (because one module creates
        # an instance of itself on install). In core Moodle they are 1, 2, 3.
        # So, to aviod problems we only test with cmids 2 and 3.
        When I set the following fields to these values:
            | idnumber_cm_2 | 1 |
            | idnumber_cm_3 | 2 |
        And I press "Save changes"
        Then I should see "Course 1"
        And I should see "Activity view filter "
        And I follow "Expand all"
        And I should see "Test quiz name 1"
        And I should see "Test quiz name 2"
        And I should see "Test quiz name 3"
        And the field "idnumber_cm_2" matches value "1"
        And the field "idnumber_cm_3" matches value "2"
