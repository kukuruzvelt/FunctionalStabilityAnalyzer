<?php

declare(strict_types=1);

namespace App\FunctionalStability\Application\Validator;

use App\FunctionalStability\Domain\Entity\FunctionalStability;

class GraphMatrixValidator
{
    public function validate($graph): void
    {
        // Проверка типов и значений в nodes
        foreach ($graph['nodes'] as $node) {
            if (!is_string($node)) {
                throw new \Exception('All elements in nodes must be strings'); // Если хотя бы один элемент nodes не строка, возвращаем false
            }
        }

        // Проверка типов и значений в edges
        $sourceTargetPairs = []; // Для отслеживания уникальных комбинаций source и target
        foreach ($graph['edges'] as $edge) {
            if (!is_string($edge['source']) || !is_string($edge['target']) || !is_float($edge['successChance'])) {
                throw new \Exception('Invalid types in an edge, source and target must be strings
                , while successChance must me a float'); // Если типы не соответствуют ожидаемым, возвращаем false
            }
            // Проверка, что source и target являются допустимыми значениями из nodes
            if (!in_array($edge['source'], $graph['nodes']) || !in_array($edge['target'], $graph['nodes'])) {
                throw new \Exception('Non existing node used in edge');
            }
            // Проверка, что source и target различны
            if ($edge['source'] === $edge['target']) {
                throw new \Exception('Values of source and target can not be the same');
            }
            // Сортируем source и target для однозначного определения порядка
            $sortedPair = [$edge['source'], $edge['target']];
            sort($sortedPair);
            $pairKey = implode('-', $sortedPair);
            // Проверка на уникальность комбинации source и target
            if (in_array($pairKey, $sourceTargetPairs)) {
                throw new \Exception('Combinations of source and target are not unique ');
            }
            $sourceTargetPairs[] = $pairKey;
            // Проверка диапазона значений successChance
            if ($edge['successChance'] < 0 || $edge['successChance'] > 1) {
                throw new \Exception('Value of successChance must be between 0 and 1');
            }
        }

        $functionalStability = new FunctionalStability($graph);

        if(!$functionalStability->isConnectedGraph()) {
            throw new \Exception('Graph is not connected');
        }
    }
}
