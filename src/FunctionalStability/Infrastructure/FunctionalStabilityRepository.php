<?php

declare(strict_types=1);

namespace App\FunctionalStability\Infrastructure;

use App\FunctionalStability\Domain\Entity\FunctionalStability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FunctionalStability>
 *
 * @method FunctionalStability|null find($id, $lockMode = null, $lockVersion = null)
 * @method FunctionalStability|null findOneBy(array $criteria, array $orderBy = null)
 * @method FunctionalStability[]    findAll()
 * @method FunctionalStability[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FunctionalStabilityRepository extends ServiceEntityRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ManagerRegistry $registry
    ) {
        parent::__construct($this->registry, FunctionalStability::class);
    }

    public function save(FunctionalStability $functionalStability): void
    {
        $this->entityManager->persist($functionalStability);
        $this->entityManager->flush();
    }
}
