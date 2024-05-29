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

  Scenario: Test non connected graph
    Given node with name "1"
    And node with name "2"
    And node with name "3"
    And edge with source "1" target "2" and success chance 0.9
    And target probability 0.5
    When graph is send to Simple Search endpoint
    Then validation error should be "Graph is not connected"

  Scenario: Test non existing node
    And node with name "2"
    And node with name "3"
    And edge with source "1" target "2" and success chance 0.9
    And edge with source "2" target "3" and success chance 0.8
    And target probability 0.5
    When graph is send to Simple Search endpoint
    Then validation error should be "Non existing node used in edge"

  Scenario: Test invalid success chance
    Given node with name "1"
    And node with name "2"
    And node with name "3"
    And edge with source "1" target "2" and success chance 2
    And edge with source "2" target "3" and success chance 0.8
    And target probability 0.5
    When graph is send to Simple Search endpoint
    Then validation error should be "Value of successChance must be between 0 and 1"

  Scenario: Test invalid target probability
    Given node with name "1"
    And node with name "2"
    And node with name "3"
    And edge with source "1" target "2" and success chance 0.5
    And edge with source "2" target "3" and success chance 0.8
    And target probability 2
    When graph is send to Simple Search endpoint
    Then validation error should be "Value of targetProbability must be between 0 and 1"