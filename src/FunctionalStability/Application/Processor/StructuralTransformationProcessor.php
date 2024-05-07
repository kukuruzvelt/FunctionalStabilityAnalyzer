<?php

declare(strict_types=1);

namespace App\FunctionalStability\Application\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\State\ProcessorInterface;
use App\FunctionalStability\Application\Validator\GraphMatrixValidator;
use App\FunctionalStability\Domain\DTO\FunctionalStabilityInputDTO;
use App\FunctionalStability\Domain\Entity\FunctionalStability;
use App\FunctionalStability\Infrastructure\FunctionalStabilityRepository;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<FunctionalStabilityInputDTO, Response>
 */
class StructuralTransformationProcessor implements ProcessorInterface
{
    public function __construct(
        private GraphMatrixValidator $graphMatrixValidator,
        private FunctionalStabilityRepository $repository
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        // Засікаємо час
        $startTime = $this->getCurrentMicroseconds();

        $graph = [
            "nodes" => $data->nodes,
            "edges" => $data->edges
        ];

        // Валідуємо граф
        $this->graphMatrixValidator->validate($graph);

        // Обчислюємо параметри функціональної стійкості
        $functionalStability = new FunctionalStability($graph);
        $xG = $functionalStability->countXG();
        $alphaG = $functionalStability->countAlphaG();
        $probabilityMatrix = $functionalStability->countProbabilitiesStructuralTransformation();

        $functionalStability->setXG($xG);
        $functionalStability->setAlphaG($alphaG);
        $functionalStability->setProbabilities($probabilityMatrix);

        // Генеруємо id
        $id = Uuid::v7();
        $functionalStability->setId($id);

        // Зберігаємо результат в базу даних
        $this->repository->save($functionalStability);

        return new Response(
            content: new \ArrayObject(
                [
                    "id" => (string) $id,
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
