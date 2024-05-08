<?php

declare(strict_types=1);

namespace App\FunctionalStability\Application\Validator;

use App\FunctionalStability\Domain\Entity\FunctionalStability;

class GraphMatrixValidator
{
    public function validateGraph($graph): void
    {
        // Перевірка типів і значень в nodes
        foreach ($graph['nodes'] as $node) {
            if (!is_string($node)) {
                throw new \Exception('All elements in nodes must be strings'); // Если хотя бы один элемент nodes не строка, возвращаем false
            }
        }

        // Перевірка типів і значень в edges
        $sourceTargetPairs = []; // Для відстежування унікальних комбінацій source и target
        foreach ($graph['edges'] as $edge) {
            if (!is_string($edge['source']) || !is_string($edge['target']) || !is_float($edge['successChance'])) {
                throw new \Exception('Invalid types in an edge, source and target must be strings
                , while successChance must me a float'); // Якщо типи не відповідають очікуваним, повертаємо помилку
            }
            // Перевіряємо, що source и target є допустимими значеннями із nodes
            if (!in_array($edge['source'], $graph['nodes']) || !in_array($edge['target'], $graph['nodes'])) {
                throw new \Exception('Non existing node used in edge');
            }
            // Перевіряємо, що source та target різні
            if ($edge['source'] === $edge['target']) {
                throw new \Exception('Values of source and target can not be the same');
            }
            // Сортуємо source та target
            $sortedPair = [$edge['source'], $edge['target']];
            sort($sortedPair);
            $pairKey = implode('-', $sortedPair);
            // Перевіряємо унікальність комбінації source и target
            if (in_array($pairKey, $sourceTargetPairs)) {
                throw new \Exception('Combinations of source and target are not unique ' . $pairKey);
            }
            $sourceTargetPairs[] = $pairKey;
            // Перевіряємо діапазон значень successChance
            if ($edge['successChance'] <= 0 || $edge['successChance'] > 1) {
                throw new \Exception('Value of successChance must be between 0 and 1');
            }
        }

        $functionalStability = new FunctionalStability($graph);

        if(!$functionalStability->isConnectedGraph()) {
            throw new \Exception('Graph is not connected');
        }
    }

    public function validateTargetProbability($targetProbability): void
    {
        if ($targetProbability <= 0 || $targetProbability > 1) {
            throw new \Exception('Value of targetProbability must be between 0 and 1');
        }
    }
}
