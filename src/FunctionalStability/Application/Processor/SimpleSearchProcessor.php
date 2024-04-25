<?php

declare(strict_types=1);

namespace App\FunctionalStability\Application\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\State\ProcessorInterface;
use App\FunctionalStability\Application\Validator\GraphMatrixValidator;
use App\FunctionalStability\Domain\DTO\SimpleSearchDTO;

/**
 * @implements ProcessorInterface<SimpleSearchDTO, Response>
 */
class SimpleSearchProcessor implements ProcessorInterface
{
    public function __construct(
        private GraphMatrixValidator $graphMatrixValidator
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $startTime = time(); // First timestamp (current time)

//        {
//  "nodes": ["1", "2", "3"],
//  "edges": [
//    {"source": "1", "target": "2", "successChance": 0.9},
//    {"source": "2", "target": "3", "successChance": 0.8}
//  ]
//}

//        $graph = [
//            "nodes" => ["1", "2", "3"],
//            "edges" => [
//                ["source" => "1", "target" => "2", "successChance" => 0.9],
//                ["source" => "2", "target" => "3", "successChance" => 0.8]
//            ]
//        ];

        $graph = [
            "nodes" => $data->nodes,
            "edges" => $data->edges
        ];

        if($this->graphMatrixValidator->validate($graph) && $this->isConnectedGraph($graph)) {
            return new Response(content: new \ArrayObject(
                [
                    "execTime" => $startTime - time(),
                    "x(G)" => 0,
                    "λ(G)" => 0,
                    "probabilityMatrix" => $this->countProbabilities($graph)
                ])
            );
        }
        return new Response('validation error');

        // TODO 1. Число вершинной связности χ(G) – это наименьшее число вершин, удаление которых вместе с инцидентными им ребрами приводит к несвязному
        //или одновершинному графу.
        //2. Число реберной связности λ(G) – это наименьшее число ребер, удаление которых приводит к несвязному графу.
        //3. Вероятность связности Pij(t) – это вероятность того, что сообщение из
        //узла i в узел j будет передано за время не более, чем t.
        //Response : stable: true, false, χ(G), λ(G), Pij(t)(json array for each node pair), timeSpentOnCalculations
    }

    private  function isConnectedGraph($graph)
    {
        $nodes = $graph['nodes'];
        $edges = $graph['edges'];
        $n = count($nodes);

        // Создаем матрицу смежности для графа
        $adjMatrix = [];
        foreach ($nodes as $node1) {
            $adjMatrix[$node1] = array_fill(0, $n, false);
        }
        foreach ($edges as $edge) {
            $source = $edge['source'];
            $target = $edge['target'];
            $adjMatrix[$source][$target] = true;
            $adjMatrix[$target][$source] = true; // Учитываем двусторонние рёбра
        }

        // Выбираем произвольную вершину в качестве начальной для обхода
        $startNode = reset($nodes);

        // Проверяем связность графа, запуская DFS от начальной вершины
        $visited = [];
        $this->dfs($adjMatrix, $startNode, $visited);

        // Если все вершины посещены, граф связный
        return count($visited) === $n;
    }

    private  function dfs($adjMatrix, $source, &$visited)
    {
        // Помечаем текущую вершину как посещённую
        $visited[$source] = true;

        // Рекурсивно обходим все смежные вершины
        foreach (array_keys($adjMatrix[$source]) as $neighbor) {
            if ($adjMatrix[$source][$neighbor] && !isset($visited[$neighbor])) {
                $this->dfs($adjMatrix, $neighbor, $visited);
            }
        }
    }

    private function getAllNodePairs($nodes) {
        $pairs = [];
        $numNodes = count($nodes);

        for ($i = 0; $i < $numNodes; $i++) {
            for ($j = $i + 1; $j < $numNodes; $j++) {
                $pairs[] = [$nodes[$i], $nodes[$j]];
            }
        }

        return $pairs;
    }

    private function getAllEdgeCombinations($edges) {
        $numEdges = count($edges);
        $combinations = [];

        // Генерируем все числа от 0 до 2^numEdges - 1
        for ($i = 0; $i < pow(2, $numEdges); $i++) {
            $combination = [];
            // Преобразуем число в двоичную строку длиной numEdges
            $binary = str_pad(decbin($i), $numEdges, '0', STR_PAD_LEFT);
            // Для каждого ребра определяем его состояние (присутствует или отсутствует)
            for ($j = 0; $j < $numEdges; $j++) {
                $combination[] = $binary[$j] === '1'; // Преобразуем '1' в true, '0' в false
            }
            $combinations[] = $combination;
        }

        return $combinations;
    }

    private function hasPathBetweenNodesWithEdgeCombination($edges, $source, $target, $edgeCombination) {
        $graph = $this->buildGraph($edges, $edgeCombination);
        $visited = [];
        return $this->hasPathDFS($graph, $source, $target, $visited);
    }

// Функция поиска в глубину (DFS) для проверки существования пути между вершинами
    private function hasPathDFS($graph, $source, $target, &$visited) {
        if ($source === $target) {
            return true; // Найден путь
        }
        if(!key_exists($source, $graph)){
            return false;
        }
        $visited[$source] = true;
        foreach ($graph[$source] as $neighbor) {
            if (!isset($visited[$neighbor])) {
                if ($this->hasPathDFS($graph, $neighbor, $target, $visited)) {
                    return true; // Найден путь
                }
            }
        }
        return false; // Путь не найден
    }


// Функция для построения графа на основе списка рёбер и комбинации рёбер
    private function buildGraph($edges, $edgeCombination) {
        $graph = [];
        foreach ($edges as $key => $edge) {
            if ($edgeCombination[$key]) { // Проверяем, присутствует ли ребро в комбинации
                $graph[$edge['source']][] = $edge['target'];
            }
        }
        return $graph;
    }

    private function countProbabilities($graph)
    {
        $edges = $graph['edges'];
        $nodePairs = $this->getAllNodePairs($graph['nodes']);
        $edgeCombinations = $this->getAllEdgeCombinations($edges);

        $result = [];
        foreach ($nodePairs as $pair) {
            $source = $pair[0];
            $target = $pair[1];
            $probability = 0;
            foreach ($edgeCombinations as $combination) {
                if($this->hasPathBetweenNodesWithEdgeCombination($edges, $source, $target, $combination)){
                    $temp = 1;
                    for ($i = 0; $i < count($combination); $i++) {
                        if($combination[$i]){
                            $temp *= $edges[$i]["successChance"];
                        }
                        else{
                            $temp *= 1 - $edges[$i]["successChance"];
                        }
                    }
                    $probability += $temp;
                }
            }

            $result[] = "Общая вероятность для пары вершин $source и $target: $probability";
        }

        return $result;
    }

}

