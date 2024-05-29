<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use Behat\Behat\Context\Context;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Serializer\SerializerInterface;

class FunctionalStabilityContext implements Context
{
    private array $nodes;
    private array $edges;
    private float $targetProbability;
    private Response $simpleSearchResponse;
    private Response $structuralTransformationResponse;

    public function __construct(
        private readonly KernelInterface $kernel,
        private SerializerInterface $serializer
    ) {
        $this->nodes = [];
        $this->edges = [];
    }


    /**
     * @Given node with name :node
     */
    public function addNode(string $node)
    {
        $this->nodes[] = $node;
    }

    /**
     * @Given edge with source :source target :target and success chance :successChance
     */
    public function addEdge(string $source, string $target, float $successChance)
    {
        $this->edges[] = [
            'source' => $source,
            'target' => $target,
            'successChance' => $successChance
        ];
    }

    /**
     * @Given target probability :targetProbability
     */
    public function addTargetProbability(float $targetProbability): void
    {
        $this->targetProbability = $targetProbability;
    }

    /**
     * @When graph is send to Simple Search endpoint
     */
    public function requestSendTo(): void
    {
        $this->simpleSearchResponse = $this->kernel->handle(Request::create(
            '/api/functional_stability/simple_search',
            'POST',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ],
            $this->serializer->serialize(
                [
                    'targetProbability' => $this->targetProbability,
                    'nodes' => $this->nodes,
                    'edges' => $this->edges
                ],
                'json'
            )
        ));
    }

    /**
     * @When graph is send to Structural Transformation endpoint
     */
    public function requestSendToStructuralTransformation(): void
    {
        $this->structuralTransformationResponse = $this->kernel->handle(Request::create(
            '/api/functional_stability/structural_transformation',
            'POST',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ],
            $this->serializer->serialize(
                [
                    'targetProbability' => $this->targetProbability,
                    'nodes' => $this->nodes,
                    'edges' => $this->edges
                ],
                'json'
            )
        ));
    }

    /**
     * @Then the error message should be :errorMessage
     */
    public function theErrorMessageShouldBe(string $errorMessage): void
    {
        $data = json_decode($this->response->getContent(), true);
        Assert::assertEquals($errorMessage, $data['detail']);
    }

    /**
     * @Then validation error should be :error
     */
    public function validationErrorShouldBe(string $error): void
    {
        Assert::assertEquals(
            json_decode($this->simpleSearchResponse->getContent(), true)['detail'],
            $error
        );
    }


    /**
     * @Then the results should be equal
     */
    public function theResultsShouldBeEqual(): void
    {
        $simpleSearchResponseData =
            json_decode($this->simpleSearchResponse->getContent(), true)['content'];
        $structuralTransformationResponseData =
            json_decode($this->structuralTransformationResponse->getContent(), true)['content'];

        $precision = 0.0001;
        Assert::assertEqualsWithDelta(
            $simpleSearchResponseData['probabilityMatrix'],
            $structuralTransformationResponseData['probabilityMatrix'],
            $precision
        );
        Assert::assertEquals(
            $simpleSearchResponseData['xG'],
            $structuralTransformationResponseData['xG']
        );
        Assert::assertEquals(
            $simpleSearchResponseData['λG'],
            $structuralTransformationResponseData['λG']
        );
    }
}
