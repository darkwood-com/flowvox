<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Transcription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transcription>
 */
class TranscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transcription::class);
    }

    /**
     * @return list<Transcription>
     */
    public function searchByText(string $query, int $limit = 50): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.fullText LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Transcription>
     */
    public function findRecentForSession(string $sessionId, int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.recording', 'r')
            ->innerJoin('r.session', 's')
            ->andWhere('s.id = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
