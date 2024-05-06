<?php

declare(strict_types=1);

namespace App\FunctionalStability\Application\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\State\ProcessorInterface;
use App\FunctionalStability\Application\Validator\GraphMatrixValidator;
use App\FunctionalStability\Domain\DTO\SimpleSearchDTO;
use App\FunctionalStability\Domain\Entity\FunctionalStability;
use App\FunctionalStability\Infrastructure\FunctionalStabilityRepository;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<SimpleSearchDTO, Response>
 */
final readonly class SimpleSearchProcessor implements ProcessorInterface
{
    public function __construct(
        private GraphMatrixValidator $graphMatrixValidator,
        private FunctionalStabilityRepository $repository
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        //                        {
        //                          "nodes": ["1", "2", "3"],
        //                          "edges": [
        //                            {"source": "1", "target": "2", "successChance": 0.9},
        //                            {"source": "2", "target": "3", "successChance": 0.8}
        //                          ]
        //                        }

        //                        $graph = [
        //                            "nodes" => ["1", "2", "3"],
        //                            "edges" => [
        //                                ["source" => "1", "target" => "2", "successChance" => 0.9],
        //                                ["source" => "2", "target" => "3", "successChance" => 0.8]
        //                            ]
        //                        ];

        // TODO
        //1. Число вершинной связности χ(G) – это наименьшее число вершин, удаление которых вместе с инцидентными им ребрами приводит к несвязному или одновершинному графу.
        //2. Число реберной связности λ(G) – это наименьшее число ребер, удаление которых приводит к несвязному графу.
        //3. Вероятность связности Pij(t) – это вероятность того, что сообщение из узла i в узел j будет передано за время не более, чем t.
        //Response : stable: true/false, χ(G), λ(G), Pij(t)(json array for each node pair), timeSpentOnCalculations

        $startTime = $this->getCurrentMicroseconds();

        $graph = [
            "nodes" => $data->nodes,
            "edges" => $data->edges
        ];

        $this->graphMatrixValidator->validate($graph);

        $functionalStability = new FunctionalStability($graph);
        $xG = $functionalStability->countXG();
        $alphaG = $functionalStability->countAlphaG();
        $probabilityMatrix = $functionalStability->countProbabilitiesSimpleSearch();

        $functionalStability->setXG($xG);
        $functionalStability->setAlphaG($alphaG);
        $functionalStability->setProbabilities($probabilityMatrix);

        $functionalStability->setId(Uuid::v7());

        $this->repository->save($functionalStability);

        return new Response(
            content: new \ArrayObject(
                [
                    "execTimeMilliseconds" => $this->getCurrentMicroseconds() - $startTime,
                    "x(G)" => $functionalStability->getXG(),
                    "λ(G)" => $functionalStability->getAlphaG(),
                    "probabilityMatrix" => $functionalStability->getProbabilities()
                ]
            )
        );
    }

    private function getCurrentMicroseconds(): float|int
    {
        return microtime(true) * 1000;
    }
}
