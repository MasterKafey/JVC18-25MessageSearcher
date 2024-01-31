<?php

namespace App\Repository;

use App\Entity\Topic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use function Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Topic>
 *
 * @method Topic|null find($id, $lockMode = null, $lockVersion = null)
 * @method Topic|null findOneBy(array $criteria, array $orderBy = null)
 * @method Topic[]    findAll()
 * @method Topic[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Topic::class);
    }

    public function save(Topic $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Topic $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return Topic[] */
    public function findByUris(array $uris): array
    {
        $queryBuilder = $this->createQueryBuilder('topic');
        $queryBuilder->where(
            $queryBuilder->expr()->in('topic.uri', ':uris')
        )
            ->setParameter('uris', $uris);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findUnreadTopic(): ?Topic
    {
        $queryBuilder = $this->createQueryBuilder('topic');
        $queryBuilder->where(
            $queryBuilder->expr()->gt('topic.messageNumber', 'topic.messageRead')
        );

        $queryBuilder->setMaxResults(1);

        return $queryBuilder->getQuery()->getSingleResult();
    }
}
