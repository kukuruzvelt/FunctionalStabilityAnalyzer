Feature:
  In order to prove that the Functional Stability Analyzer works correctly
  As a user
  I want to be able to get functional stability parameters

  Scenario: Test results with simple graph
    Given node with name "1"
    And node with name "2"
    And node with name "3"
    And edge with source "1" target "2" and success chance 0.9
    And edge with source "2" target "3" and success chance 0.8
    And edge with source "1" target "3" and success chance 0.7
    And target probability 0.5
    When graph is send to Simple Search endpoint
    And graph is send to Structural Transformation endpoint
    Then the results should be equal
