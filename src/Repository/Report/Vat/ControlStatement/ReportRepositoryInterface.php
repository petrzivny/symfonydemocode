<?php
/**
 * Created by Petr
 * Date: 2019-02-11 (UTC +2 (+1))
 * Time: 10:47 (UTC +2 (+1))
 */

namespace App\Repository\Report\Vat\ControlStatement;


use App\Entity\Core\Client;
use App\Entity\Report\Vat\ControlStatement\Report;

interface ReportRepositoryInterface
{
    /**
     * @param Client $client - only for this client
     * @param \DateTimeInterface $begin - only reports since this date until now
     * @return Report[]
     */
    public function findForClientSince(Client $client, \DateTimeInterface $begin): array;

    /**
     * @param Client $client - only for this client
     * @param \DateTimeInterface $date - only for last day of report period
     * @return Report[]
     */
    public function findNotEmptyForClientAndDate(Client $client, \DateTimeInterface $date): array;

    /**
     * @param $id - Report id (uuid) from DB
     * @return Report
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getByIdJoinItemsValuesAccDocumentPartner($id): Report;
}
