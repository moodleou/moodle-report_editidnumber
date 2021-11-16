@report @report_editidnumber @ou @ou_vle
Feature: Edit idnumber report navigation
  In order to navigate through report page
  As an admin
  Go to course administration -> Reports -> ID numbers

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | admin | C1 | editingteacher |
      | student1 | C1 | student |

  @javascript
  Scenario: Selector should be available in the Edit idnumber report
    Given I log in as "admin"
    And I am on "Course 1" course homepage
    When I navigate to "Reports > ID numbers" in current page administration
    Then "Report" "field" should exist
    And the "Report" select box should contain "ID numbers"
    And the field "Report" matches value "ID numbers"
