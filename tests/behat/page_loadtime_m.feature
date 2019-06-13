@mod @mod_personalschedule
Feature: A teacher can use activity completion to track a student progress
  In order to use activity completion
  As a teacher
  I need to set personalschedule activities and enable activity completion

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com  |
      | m1      | m1       | m1      | m1@example.com  |
    And the course "M1" with "2" elements exists
    And the following "course enrolments" exist:
      | user | course | role |
      | m1 | M1 | student |
      | teacher1 | M1 | editingteacher |
    And I log in as "teacher1"
    And I am on "Test course: M" course homepage with editing mode on

  Scenario: M1 Test
    Given I add a "Personalization" to section "1" and I fill the form with:
      | Name | Test personalschedule name |
    And I turn editing mode off
    And "Test personalschedule name" activity should be visible
    And I fill personalschedule "Test personalschedule name" activity settings with the test data
    And I log out
    And I log in as "m1"
    And I fill personalschedule "Test personalschedule name" user settings with the test data
    And I am on my homepage
    And I press "Customise this page"
    And I add the "Блок персонализированных элементов" block
    And I log out
    And I log in as "m1"
    And I am on my homepage
    Then I should see "Текущее время" in the "Блок персонализированных элементов" "block"
    Then I should log performance info with tag "m1"
