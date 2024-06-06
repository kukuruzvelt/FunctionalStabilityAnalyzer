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
    private const SEPARATOR = '|';

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

    public function countXG(array $graph = []): int
    {
        $xG = 1;
        if (!$graph) {
            $graph = $this->graph;
        }

        // По черзі прибираємо кожну з вершин, аби пересвідчитись, що граф залишить з'язним
        for ($i = 0; $i < count($graph['nodes']); $i++) {
            $tempGraph = $this->removeNodeFromGraph($graph, $i);
            if (!$this->isConnectedGraph($tempGraph)) {
                return $xG;
            }
        }

        // Якщо так, збільшуємо показник на 1 та повторюємо алгоритм вже без одної вершини
        return $xG + $this->countXG($this->removeNodeFromGraph($graph, 0));
    }

    public function countAlphaG(array $graph = [])
    {
        $alphaG = 1;
        if (!$graph) {
            $graph = $this->graph;
        }

        // По черзі прибираємо кожне з ребер, аби пересвідчитись, що граф залишить з'язним
        for ($i = 0; $i < count($graph['edges']); $i++) {
            $tempGraph = $this->removeEdgeByIndex($graph, $i);
            if (!$this->isConnectedGraph($tempGraph)) {
                return $alphaG;
            }
        }

        // Якщо так, збільшуємо показник на 1 та повторюємо алгоритм вже без одного ребра
        return $alphaG + $this->countAlphaG($this->removeEdgeByIndex($graph, 0));
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

            $result[] = ['source' => $source, 'target' => $target, 'probability' => number_format($probability, 3)];
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

            $result[] = ['source' => $source, 'target' => $target, 'probability' => number_format($probability, 3)];
        }

        return $result;
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

        // Генеруємо всі числа від 0 до 2^numEdges - 1
        for ($i = 0; $i < pow(2, $numEdges); $i++) {
            $combination = [];
            // Перетворимо число на двійковий рядок довжиною numEdges
            $binary = str_pad(decbin($i), $numEdges, '0', STR_PAD_LEFT);
            // Для кожного ребра визначаємо його стан (присутнє чи відсутнє)
            for ($j = 0; $j < $numEdges; $j++) {
                $combination[] = $binary[$j] === '1'; // Перетворюємо '1' на true, '0' на false
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

    // Функція пошуку в глибину (DFS) для перевірки існування шляху між вершинами
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

    // Функція для побудови графа на основі списку ребер та комбінації ребер
    private function buildGraph($edges, $edgeCombination): array
    {
        $graph = [];
        foreach ($edges as $key => $edge) {
            if ($edgeCombination[$key]) { // Перевіряємо, чи є ребро в комбінації
                $source = $edge['source'];
                $target = $edge['target'];
                $graph[$source][] = $target;
                // Враховуємо і зворотний напрямок ребра
                $graph[$target][] = $source;
            }
        }
        return $graph;
    }

    private function hasPathDFSWithContractedNodes($graph, $source, $target, &$visited): bool
    {
        // Перевіряємо, чи існує вершина $source у графі
        $found = false;
        foreach ($graph['nodes'] as $node) {
            if (str_contains($node, $source) && str_contains($node, $target)) {
                return true;
            }
            if ($node === $source || strpos($node, self::SEPARATOR) !== false && in_array($source, explode(self::SEPARATOR, $node))) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return false;
        }

        // Перевіряємо, чи ми досягли цільової вершини
        if ($source === $target) {
            return true; // Найден путь
        }

        // Перевіряємо, чи є сусіди поточної вершини
        if (!isset($graph['edges']) || empty($graph['edges'])) {
            return false;
        }

        // Позначаємо поточну вершину як відвідану
        $visited[$source] = true;

        // Перебираємо всі ребра графа
        foreach ($graph['edges'] as $edge) {
            $edgeSource = $edge['source'];
            $edgeTarget = $edge['target'];

            // Перевіряємо, чи поточне ребро інцидентно нашій вершині
            if (strpos($edgeSource, $source) !== false || strpos($edgeTarget, $source) !== false) {
                // Определяем вершину, к которой ведет текущее ребро
                $neighbor = ($edgeSource === $source) ? $edgeTarget : $edgeSource;

                // Перевіряємо, чи відвідували ми вже цю вершину
                if (!isset($visited[$neighbor])) {
                    // Якщо ми ще не відвідували цю вершину, рекурсивно шукаємо шлях від неї до цільової вершини
                    if ($this->hasPathDFSWithContractedNodes($graph, $neighbor, $target, $visited)) {
                        return true; // Знайдено шлях
                    }
                }
            }
        }

        // Якщо ми дійшли до цієї точки, значить, шлях не знайдено
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

        // Стягуємо ребро графа
        $graphWithContractedEdge = $this->contractEdge($graph, $edgeIndex);
        if (count($graphWithContractedEdge['edges']) > 0) {
            // Якщо залишилось більше одно ребра, рекурсивно повторюємо алгоритм
            $probability += ($edge["successChance"] * $this->countProbabilityForNodePairStructuralTransformation($graphWithContractedEdge, $source, $target));
        } else {
            $probability += $edge["successChance"];
        }

        // Прибираємо ребро графа
        $graphWithRemovedEdge = $this->removeEdgeByIndex($graph, $edgeIndex);
        $visited = [];
        if ($this->hasPathDFSWithContractedNodes($graphWithRemovedEdge, $source, $target, $visited)) {
            if (count($graphWithRemovedEdge['edges']) > 0) {
                // Якщо залишилось більше одно ребра, рекурсивно повторюємо алгоритм
                $probability += ((1 - $edge["successChance"]) * $this->countProbabilityForNodePairStructuralTransformation($graphWithRemovedEdge, $source, $target));
            } else {
                $probability += (1 - $edge["successChance"]);
            }
        }

        return $probability;
    }

    private function contractEdge(array $graph, int $edgeIndex): array
    {
        // Отримуємо ребро за індексом
        $edge = $graph['edges'][$edgeIndex];
        $source = $edge['source'];
        $target = $edge['target'];

        // Створюємо нову вершину, яка об'єднує вершини source та target
        $newNode = $source . self::SEPARATOR . $target;

        // Видаляємо вершини source і target зі списку вершин
        $nodes = array_diff($graph['nodes'], [$source, $target]);

        // Додаємо нову вершину до списку вершин
        $nodes[] = $newNode;

        // Створюємо нові ребра, що з'єднують нову вершину з вершинами, суміжними source та target
        $edges = [];
        foreach ($graph['edges'] as $currentEdgeIndex => $currentEdge) {
            $currentSource = $currentEdge['source'];
            $currentTarget = $currentEdge['target'];

            // Виключаємо ребро, яким здійснювалося стягування
            if ($currentEdgeIndex === $edgeIndex) {
                continue;
            }

            // Якщо поточне ребро інцидентно однією з вершин, що стягуються, замінюємо її новою вершиною
            if ($currentSource === $source || $currentSource === $target) {
                $currentEdge['source'] = $newNode;
            }
            if ($currentTarget === $source || $currentTarget === $target) {
                $currentEdge['target'] = $newNode;
            }

            // Додаємо ребро до списку, якщо воно не було виключено
            $edges[] = $currentEdge;
        }

        return [
            "nodes" => $nodes,
            "edges" => $edges
        ];
    }

    private function removeEdgeByIndex(array $graph, int $index): array
    {
        // Перевіряємо, чи існує ребро із зазначеним індексом у списку ребер
        if (isset($graph['edges'][$index])) {
            // Видаляємо ребро із зазначеним індексом зі списку ребер
            unset($graph['edges'][$index]);

            // Переіндексуємо масив ребер, щоб індекси починалися з 0
            $graph['edges'] = array_values($graph['edges']);
        }

        // Повертаємо новий граф із віддаленим рубом
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

        // Створюємо матрицю суміжності для графа
        $adjMatrix = [];
        foreach ($nodes as $node1) {
            $adjMatrix[$node1] = array_fill(0, $n, false);
        }
        foreach ($edges as $edge) {
            $source = $edge['source'];
            $target = $edge['target'];
            $adjMatrix[$source][$target] = true;
            $adjMatrix[$target][$source] = true; // Враховуємо двосторонні ребра
        }

        // Вибираємо довільну вершину як початкову для обходу
        $startNode = reset($nodes);

        // Перевіряємо зв'язок графа, запускаючи DFS від початкової вершини
        $visited = [];
        $this->dfs($adjMatrix, $startNode, $visited);

        // Якщо всі вершини відвідані, граф зв'язний
        return count($visited) === $n;
    }

    private function dfs($adjMatrix, $source, &$visited): void
    {
        // Позначаємо поточну вершину як відвідану
        $visited[$source] = true;

        // Рекурсивно обходимо всі суміжні вершини
        foreach (array_keys($adjMatrix[$source]) as $neighbor) {
            if ($adjMatrix[$source][$neighbor] && !isset($visited[$neighbor])) {
                $this->dfs($adjMatrix, $neighbor, $visited);
            }
        }
    }

    private function removeNodeFromGraph(array $graph, int $nodeIndex): array
    {
        $vertexToRemove = $graph['nodes'][$nodeIndex];

        // Видаляємо всі ребра, інцидентні заданій вершині
        $edgesToRemove = [];
        foreach ($graph['edges'] as $key => $edge) {
            if ($edge['source'] === $vertexToRemove || $edge['target'] === $vertexToRemove) {
                $edgesToRemove[] = $key;
            }
        }
        foreach ($edgesToRemove as $key) {
            unset($graph['edges'][$key]);
        }
        $graph['edges'] = array_values($graph['edges']); // Перенумеруємо ключі

        // Видаляємо саму задану вершину зі списку вершин графа
        unset($graph['nodes'][$nodeIndex]);
        $graph['nodes'] = array_values($graph['nodes']); // Перенумеруємо ключі

        return $graph;
    }
}
