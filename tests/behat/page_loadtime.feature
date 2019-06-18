@mod @mod_personalschedule
Feature: Developer can log performance info from the module page with proposed items to the file
  In order to log performance info
  As a teacher
  I need to add personalschedule activity to the course.
  As a user
  I need to enrol to the course, then fill schedule.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Teacher   | 1        | teacher1@example.com  |
      | xs1      | xs1       | xs1      | xs1@example.com       |
    And the course "XS1" with "0" elements exists
    And the following "course enrolments" exist:
      | user     | course | role           |
      | xs1      | XS1    | student        |
      | teacher1 | XS1    | editingteacher |
    And I log in as "teacher1"
    And performance debug enabled
    And I am on "Test course: XS" course homepage with editing mode on

  Scenario: XS1 Test
    Given I add a "Personalized Schedule" to section "1" and I fill the form with:
      | Name | Test personalschedule name |
    And I turn editing mode off
    And "Test personalschedule name" activity should be visible
    And I fill personalschedule "Test personalschedule name" activity settings with the test data
    And I log out
    And I log in as "xs1"
    And I fill personalschedule "Test personalschedule name" user settings with the test data
    And I am on "Test course: XS" course homepage
    And I should see "Test personalschedule name"
    When I follow "Test personalschedule name"
    Then I should log performance info with tag "xs1"