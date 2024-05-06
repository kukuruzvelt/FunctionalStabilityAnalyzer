<?php

declare(strict_types=1);

namespace App\FunctionalStability\Domain\Entity;

use App\FunctionalStability\Infrastructure\FunctionalStabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FunctionalStabilityRepository::class)]
final class FunctionalStability
{
    public function __construct(private array $graph)
    {
    }

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private ?Uuid $id = null;

    #[ORM\Column]
    private ?int $xG = null;

    #[ORM\Column]
    private ?int $alphaG = null;

    #[ORM\Column(type: Types::JSON)]
    private array $probabilities = [];

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getXG(): ?int
    {
        return $this->xG;
    }

    public function setXG(int $xG): static
    {
        $this->xG = $xG;

        return $this;
    }

    public function getAlphaG(): ?int
    {
        return $this->alphaG;
    }

    public function setAlphaG(int $alphaG): static
    {
        $this->alphaG = $alphaG;

        return $this;
    }

    public function getProbabilities(): array
    {
        return $this->probabilities;
    }

    public function setProbabilities(array $probabilities): static
    {
        $this->probabilities = $probabilities;

        return $this;
    }

    public function setId(Uuid $id): static
    {
        $this->id = $id;

        return $this;
    }

    private function getAllNodePairs($nodes): array
    {
        $pairs = [];
        $numNodes = count($nodes);

        for ($i = 0; $i < $numNodes; $i++) {
            for ($j = $i + 1; $j < $numNodes; $j++) {
                $pairs[] = [$nodes[$i], $nodes[$j]];
            }
        }

        return $pairs;
    }

    private function getAllEdgeCombinations($edges): array
    {
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

    private function hasPathBetweenNodesWithEdgeCombination($edges, $source, $target, $edgeCombination): bool
    {
        $graph = $this->buildGraph($edges, $edgeCombination);
        $visited = [];
        return $this->hasPathDFS($graph, $source, $target, $visited);
    }

    // Функция поиска в глубину (DFS) для проверки существования пути между вершинами
    private function hasPathDFS($graph, $source, $target, &$visited): bool
    {
        if ($source === $target) {
            return true; // Найден путь
        }
        if (!key_exists($source, $graph)) {
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
    public function buildGraph($edges, $edgeCombination): array
    {
        $graph = [];
        foreach ($edges as $key => $edge) {
            if ($edgeCombination[$key]) { // Проверяем, присутствует ли ребро в комбинации
                $source = $edge['source'];
                $target = $edge['target'];
                $graph[$source][] = $target;
                // Учитываем и обратное направление ребра
                $graph[$target][] = $source;
            }
        }
        return $graph;
    }

    public function countProbabilitiesSimpleSearch(): array
    {
        $graph = $this->graph;
        $edges = $graph['edges'];
        $nodePairs = $this->getAllNodePairs($graph['nodes']);
        $edgeCombinations = $this->getAllEdgeCombinations($edges);

        $result = [];
        foreach ($nodePairs as $pair) {
            $source = $pair[0];
            $target = $pair[1];
            $probability = 0;
            foreach ($edgeCombinations as $combination) {
                if ($this->hasPathBetweenNodesWithEdgeCombination($edges, $source, $target, $combination)) {
                    $temp = 1;
                    for ($i = 0; $i < count($combination); $i++) {
                        if ($combination[$i]) {
                            $temp *= $edges[$i]["successChance"];
                        } else {
                            $temp *= 1 - $edges[$i]["successChance"];
                        }
                    }
                    $probability += $temp;
                }
            }

            $result[] = ['source' => $source, 'target' => $target, 'probability' => $probability];
        }

        return $result;
    }

    public function countProbabilitiesStructuralTransformation(): array
    {
        $graph = $this->graph;
        $nodePairs = $this->getAllNodePairs($graph['nodes']);

        $result = [];
        foreach ($nodePairs as $pair) {
            $source = $pair[0];
            $target = $pair[1];
            $probability = $this->countProbabilityForNodePairStructuralTransformation($graph, $source, $target);

            $result[] = ['source' => $source, 'target' => $target, 'probability' => $probability];
        }

        return $result;
    }


    private function hasPathDFSWithContractedNodes($graph, $source, $target, &$visited): bool
    {
        // Проверяем, существует ли вершина $source в графе
        $found = false;
        foreach ($graph['nodes'] as $node) {
            if (str_contains($node, $source) && str_contains($node, $target)) {
                return true;
            }
            if ($node === $source || strpos($node, '|') !== false && in_array($source, explode('|', $node))) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return false;
        }

        // Проверяем, достигли ли мы целевой вершины
        if ($source === $target) {
            return true; // Найден путь
        }

        // Проверяем, есть ли соседи у текущей вершины
        if (!isset($graph['edges']) || empty($graph['edges'])) {
            return false;
        }

        // Помечаем текущую вершину как посещенную
        $visited[$source] = true;

        // Перебираем все рёбра графа
        foreach ($graph['edges'] as $edge) {
            $edgeSource = $edge['source'];
            $edgeTarget = $edge['target'];

            // Проверяем, инцидентно ли текущее ребро нашей вершине
            if (strpos($edgeSource, $source) !== false || strpos($edgeTarget, $source) !== false) {
                // Определяем вершину, к которой ведет текущее ребро
                $neighbor = ($edgeSource === $source) ? $edgeTarget : $edgeSource;

                // Проверяем, посещали ли мы уже эту вершину
                if (!isset($visited[$neighbor])) {
                    // Если мы еще не посещали эту вершину, рекурсивно ищем путь от нее до целевой вершины
                    if ($this->hasPathDFSWithContractedNodes($graph, $neighbor, $target, $visited)) {
                        return true; // Найден путь
                    }
                }
            }
        }

        // Если мы дошли до этой точки, значит, путь не найден
        return false;
    }

    private function countProbabilityForNodePairStructuralTransformation(
        array $graph,
        string $source,
        string $target,
    ): float {
        $probability = 0;
        $edgeIndex = 0;
        $edge = $graph['edges'][$edgeIndex];

        $graphWithContractedEdge = $this->contractEdge($graph, $edgeIndex);
        if (count($graphWithContractedEdge['edges']) > 0) {
            $probability += ($edge["successChance"] * $this->countProbabilityForNodePairStructuralTransformation($graphWithContractedEdge, $source, $target));
        } else {
            $probability += $edge["successChance"];
        }

        $graphWithRemovedEdge = $this->removeEdge($graph, $edgeIndex);
        $visited = [];
        if ($this->hasPathDFSWithContractedNodes($graphWithRemovedEdge, $source, $target, $visited)) {
            if (count($graphWithRemovedEdge['edges']) > 0) {
                $probability += ((1 - $edge["successChance"]) * $this->countProbabilityForNodePairStructuralTransformation($graphWithRemovedEdge, $source, $target));
            } else {
                $probability += (1 - $edge["successChance"]);
            }
        }

        return $probability;
    }

    private function contractEdge(array $graph, int $edgeIndex): array
    {
        // Получаем ребро по индексу
        $edge = $graph['edges'][$edgeIndex];
        $source = $edge['source'];
        $target = $edge['target'];

        // Создаем новую вершину, которая объединяет вершины source и target
        $newNode = $source . '|' . $target;

        // Удаляем вершины source и target из списка вершин
        $nodes = array_diff($graph['nodes'], [$source, $target]);

        // Добавляем новую вершину в список вершин
        $nodes[] = $newNode;

        // Создаем новые ребра, соединяющие новую вершину с вершинами, смежными source и target
        $edges = [];
        foreach ($graph['edges'] as $currentEdgeIndex => $currentEdge) {
            $currentSource = $currentEdge['source'];
            $currentTarget = $currentEdge['target'];

            // Исключаем ребро, по которому осуществлялось стягивание
            if ($currentEdgeIndex === $edgeIndex) {
                continue;
            }

            // Если текущее ребро инцидентно одной из стягиваемых вершин, заменяем её новой вершиной
            if ($currentSource === $source || $currentSource === $target) {
                $currentEdge['source'] = $newNode;
            }
            if ($currentTarget === $source || $currentTarget === $target) {
                $currentEdge['target'] = $newNode;
            }

            // Добавляем ребро в список, если оно не было исключено
            $edges[] = $currentEdge;
        }

        // Удаляем дубликаты рёбер, чтобы избежать нескольких рёбер между одними и теми же вершинами
        $edges = array_unique($edges, SORT_REGULAR);

        return [
            "nodes" => $nodes,
            "edges" => $edges
        ];
    }

    private function removeEdge(array $graph, int $index): array
    {
        // Проверяем, существует ли ребро с указанным индексом в списке рёбер
        if (isset($graph['edges'][$index])) {
            // Удаляем ребро с указанным индексом из списка рёбер
            unset($graph['edges'][$index]);

            // Переиндексируем массив рёбер, чтобы индексы начинались с 0
            $graph['edges'] = array_values($graph['edges']);
        }

        // Возвращаем новый граф с удаленным ребром
        return $graph;
    }

    public function isConnectedGraph(array $graph = []): bool
    {
        if (!$graph) {
            $graph = $this->graph;
        }
        $nodes = $graph['nodes'];
        $edges = $graph['edges'];
        $n = count($nodes);

        if (count($nodes) < 1 || count($edges) < 1) {
            return false;
        }

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

    private function dfs($adjMatrix, $source, &$visited): void
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

    public function countXG(array $graph = []): int
    {
        $xG = 1;
        if (!$graph) {
            $graph = $this->graph;
        }

        for ($i = 0; $i < count($graph['nodes']); $i++) {
            $tempGraph = $this->removeNodeFromGraph($graph, $i);
            if (!count($tempGraph['nodes']) > 1 || !$this->isConnectedGraph($tempGraph)) {
                return $xG;
            }
        }

        return $xG + $this->countXG($this->removeNodeFromGraph($graph, 0));
    }

    private function removeNodeFromGraph(array $graph, int $nodeIndex): array
    {
        $vertexToRemove = $graph['nodes'][$nodeIndex];

        // Удаляем все ребра, инцидентные заданной вершине
        $edgesToRemove = [];
        foreach ($graph['edges'] as $key => $edge) {
            if ($edge['source'] === $vertexToRemove || $edge['target'] === $vertexToRemove) {
                $edgesToRemove[] = $key;
            }
        }
        foreach ($edgesToRemove as $key) {
            unset($graph['edges'][$key]);
        }
        $graph['edges'] = array_values($graph['edges']); // Перенумеруем ключи

        // Удаляем саму заданную вершину из списка вершин графа
        unset($graph['nodes'][$nodeIndex]);
        $graph['nodes'] = array_values($graph['nodes']); // Перенумеруем ключи

        return $graph;
    }

    public function countAlphaG(array $graph = [])
    {
        $alphaG = 1;
        if (!$graph) {
            $graph = $this->graph;
        }

        for ($i = 0; $i < count($graph['edges']); $i++) {
            $tempGraph = $this->removeEdgeFromGraph($graph, $i);
            if (!count($tempGraph['edges']) > 1 || !$this->isConnectedGraph($tempGraph)) {
                return $alphaG;
            }
        }

        return $alphaG + $this->countAlphaG($this->removeEdgeFromGraph($graph, 0));
    }

    private function removeEdgeFromGraph(array $graph, int $edgeIndex): array
    {
        unset($graph['edges'][$edgeIndex]);
        $graph['edges'] = array_values($graph['edges']); // Перенумеруем ключи
        return $graph;
    }
}
