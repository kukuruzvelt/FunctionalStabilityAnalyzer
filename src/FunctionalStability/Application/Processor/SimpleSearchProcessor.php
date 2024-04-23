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
        //                $graph = [
        //                    "nodes" => ["1", "2", "3"],
        //                    "edges" => [
        //                        ["source" => "1", "target" => "2", "successChance" => 0.9],
        //                        ["source" => "2", "target" => "3", "successChance" => 0.8]
        //                    ]
        //                ];

        $graph = [
            "nodes" => $data->nodes,
            "edges" => $data->edges
        ];

        if($this->graphMatrixValidator->validate($graph) && $this->isConnectedGraph($graph)) {
            return new Response('true');
        }
        return new Response('false');

        // TODO 1. Число вершинной связности χ(G) – это наименьшее число вершин, удаление которых вместе с инцидентными им ребрами приводит к несвязному
        //или одновершинному графу.
        //2. Число реберной связности λ(G) – это наименьшее число ребер, удаление которых приводит к несвязному графу.
        //3. Вероятность связности Pij(t) – это вероятность того, что сообщение из
        //узла i в узел j будет передано за время не более, чем t.
        //Response : stable: true, false, χ(G), λ(G), Pij(t)(json array for each node pair), timeSpentOnCalculations
    }

    public function isConnectedGraph($graph)
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

    public function dfs($adjMatrix, $source, &$visited)
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
}
