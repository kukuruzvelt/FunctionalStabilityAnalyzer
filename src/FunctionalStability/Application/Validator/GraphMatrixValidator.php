<?php

declare(strict_types=1);

namespace App\FunctionalStability\Application\Validator;

class GraphMatrixValidator
{
    public function validate($graph)
    {
        // Проверка типов и значений в nodes
        foreach ($graph['nodes'] as $node) {
            if (!is_string($node)) {
                return false; // Если хотя бы один элемент nodes не строка, возвращаем false
            }
        }

        // Проверка типов и значений в edges
        $sourceTargetPairs = []; // Для отслеживания уникальных комбинаций source и target
        foreach ($graph['edges'] as $edge) {
            if (!is_string($edge['source']) || !is_string($edge['target']) || !is_float($edge['successChance'])) {
                return false; // Если типы не соответствуют ожидаемым, возвращаем false
            }
            // Проверка, что source и target являются допустимыми значениями из nodes
            if (!in_array($edge['source'], $graph['nodes']) || !in_array($edge['target'], $graph['nodes'])) {
                return false;
            }
            // Проверка, что source и target различны
            if ($edge['source'] === $edge['target']) {
                return false;
            }
            // Сортируем source и target для однозначного определения порядка
            $sortedPair = [$edge['source'], $edge['target']];
            sort($sortedPair);
            $pairKey = implode('-', $sortedPair);
            // Проверка на уникальность комбинации source и target
            if (in_array($pairKey, $sourceTargetPairs)) {
                return false;
            }
            $sourceTargetPairs[] = $pairKey;
            // Проверка диапазона значений successChance
            if ($edge['successChance'] < 0 || $edge['successChance'] > 1) {
                return false;
            }
        }

        // Все проверки пройдены успешно
        return true;
    }

}
