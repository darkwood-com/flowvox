<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\VoiceWorkerSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VoiceWorkerSession>
 */
class VoiceWorkerSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VoiceWorkerSession::class);
    }

    /**
     * @return list<VoiceWorkerSession>
     */
    public function findActiveSessions(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.lastHeartbeatAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function removeStaleSessions(\DateTimeImmutable $before): int
    {
        $qb = $this->createQueryBuilder('s')
            ->delete(VoiceWorkerSession::class, 's')
            ->where('s.lastHeartbeatAt < :before')
            ->setParameter('before', $before);

        return (int) $qb->getQuery()->execute();
    }
}
