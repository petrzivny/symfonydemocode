<?php

namespace App\Repository\Report\Vat\ControlStatement;

use App\Entity\Core\Client;
use App\Entity\Report\Vat\ControlStatement\Report;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Report|null find($id, $lockMode = null, $lockVersion = null)
 * @method Report|null findOneBy(array $criteria, array $orderBy = null)
 * @method Report[]    findAll()
 * @method Report[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class ReportRepository extends ServiceEntityRepository implements ReportRepositoryInterface
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Report::class);
    }

     /**
      * @inheritdoc
      */
    public function findForClientSince(Client $client, \DateTimeInterface $begin): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.client = :client')
            ->andWhere('r.date >= :begin')
            ->setParameter('client', $client)
            ->setParameter('begin', $begin)
            ->orderBy('r.date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @inheritdoc
     */
    public function findNotEmptyForClientAndDate(Client $client, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.client = :client')
            ->andWhere('r.date = :date')
            ->andWhere('r.category != :category')
            ->setParameter('client', $client)
            ->setParameter('date', $date)
            ->setParameter('category', 'empty')
            ->getQuery()
            ->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getByIdJoinItemsValuesAccDocumentPartner($id): Report
    {
        return $this->createQueryBuilder('r')
            ->select('r', 'i', 'v', 'a', 'p')
            ->andWhere('r.id = :id')
            ->leftJoin('r.items', 'i')
            ->leftJoin('i.values', 'v')
            ->leftJoin('i.accDocument', 'a')
            ->leftJoin('a.partner', 'p')
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleResult();
    }
}
