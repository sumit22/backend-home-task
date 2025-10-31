<?php

namespace App\Repository;

use App\Entity\Rule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rule>
 */
class RuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rule::class);
    }

    /**
     * Find active rules for a specific repository scope
     * 
     * @param string $scope Scope pattern (e.g., 'repository:uuid')
     * @return Rule[]
     */
    public function findActiveRulesForScope(string $scope): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.enabled = :enabled')
            ->andWhere('r.scope = :scope')
            ->setParameter('enabled', true)
            ->setParameter('scope', $scope)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active global rules (fallback rules)
     * 
     * @return Rule[]
     */
    public function findActiveGlobalRules(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.enabled = :enabled')
            ->andWhere('r.scope = :scope')
            ->setParameter('enabled', true)
            ->setParameter('scope', 'global')
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
