<?php
/**
 * Created by Petr
 * Date: 2019-02-11 (UTC +2 (+1))
 * Time: 8:55 (UTC +2 (+1))
 */

namespace App\Service;


use App\Entity\Core\AccDocument;
use App\Entity\Core\Client;
use App\Entity\Core\User;
use App\Entity\Report\Vat\ControlStatement\Report;
use App\Entity\Report\Vat\ControlStatement\ReportItem;
use App\Repository\Report\Vat\ControlStatement\ReportRepositoryInterface;
use Doctrine\Common\Persistence\ObjectManager;

class VatControlStatement
{
    /** @var ReportRepositoryInterface */
    private $reportRepository;

    public function __construct(ReportRepositoryInterface $reportRepository)
    {
        $this->reportRepository = $reportRepository;
    }

    /**
     * @param Client $client - limit return to this Client
     * @param \DateTimeImmutable $begin - start to search from this (1.1.YYYY)
     * @param \DateTimeInterface $isClientSince - Since client is active in system
     * @return Report[]
     */
    public function getLastReports(Client $client, \DateTimeImmutable $begin, \DateTimeInterface $isClientSince): array
    {
        do {
            $reports = $this->reportRepository->findForClientSince($client, $begin);
            $begin = $begin->modify('-1 year');
        } while (empty($reports) && $begin->format("Y") >= $isClientSince->format("Y"));

        return $reports;
    }

    /**
     * @param Report[] $dbReports - Previously reported reports, taken from database
     * @param \DateTime $isClientSince
     * @return \DateTimeImmutable
     * @throws \Exception
     */
    public function getFirstFutureReportDate(array $dbReports, \DateTime $isClientSince): \DateTimeImmutable
    {
        if (array_key_last($dbReports) !== null) {
            $dateFromDb = $dbReports[array_key_last($dbReports)]->getDate();
        }

        if (isset($dateFromDb) && $dateFromDb > $isClientSince) {
            $returnDate = new \DateTimeImmutable($dateFromDb->format("Y-m-d"));
        } else {
            $returnDate = (new \DateTimeImmutable($isClientSince->format("Y-m-d")))
                ->modify('last day of previous month');
        }

        return $returnDate;
    }

    /**
     * @return Report[]
     */
    public function generateFutureReports(\DateTimeImmutable $begin, \DateTimeImmutable $end)
    {
        $notReportedDueReports = [];
        while ($begin < $end) {
            $nextReport = new Report();
            $begin = $begin->modify('last day of next month');
            $nextReport->setDate($begin);
            $notReportedDueReports[] = $nextReport;
        }

        return $notReportedDueReports;
    }

    /**
     * @param AccDocument[] $accDocuments
     * @param User $user
     * @param \DateTime $end
     * @param int $deadlineSetting - number of days from end of period till report must be submitted
     * @return Report
     * @throws \Exception - new \DateTime() is never thrown
     */
    public function createReport(array $accDocuments, array $categories, User $user, \DateTime $end, int $deadlineSetting): Report
    {
        $report = new Report();
        $report->setClient($user->getActiveClient());
        $report->setDate($end);
        $report->setCreatedBy($user);
        $report->setCreatedAt(new \DateTime());

        foreach ($accDocuments as $accDocument) {
            if ($accDocument->getLocalTaxValueSum()) {
                $item = new ReportItem();
                $item->setAccDocument($accDocument);
                $item->setDate($accDocument->getTaxDate());
                $item->setSyncNumber($accDocument->getVatSyncNumber());
                $categoryLetter = $accDocument->getVatControlStatementCategoryLetter();
                $item->setCategory($categories[$categoryLetter]);
                $item->setCreatedAt(new \DateTime());
                $item->setCreatedBy($user);
                $item->setValuesIndexedByTaxTariffId($accDocument);
                $report->addItem($item);
            }
        }

        $report->setCategory($this->getReportCategory($report, $deadlineSetting));

        return $report;
    }

    /**
     * @param Report $report - all items, including not selected
     * @param array $items - indexes of selected items (ids of AccDocuments)
     * @param ObjectManager $em
     * @param int $deadlineSetting - number of days from end of period till report must be submitted
     * @throws \Exception - new \DateTime() is never thrown
     */
    public function saveSelectedItemsToDb(Report $report, array $items, ObjectManager $em, int $deadlineSetting): void
    {
        $daysSinceReportEnd = ($report->getDate()->diff($report->getCreatedAt()))->format('%a');
        if ($daysSinceReportEnd > $deadlineSetting) {
            $report->setLate(true);
        } else {
            $report->setLate(false);
        }

        foreach ($report->getItems() as $item) {
            if (array_key_exists($item->getAccDocument()->getId(), $items)) {
                $em->persist($item);
                foreach ($item->getValues() as $value) {
                    $em->persist($value);
                }
            } else {
                $report->removeItem($item);
            }
        }
        $em->persist($report);
        $em->flush();
    }

    /**
     * @param Report $report
     * @param int $deadlineSetting - number of days from end of period till report must be submitted
     * @return string
     *      empty - If there are no items (nothing to report for the period)
     *      proper - First report for current period. No matter if before or after due date.
     *      correctionBeforeDeadline - Fixes before due date.
     *      correctionAfterDeadline - Fixes after due date.
     * @throws \Exception - new \DateTime() is never thrown
     */
    private function getReportCategory(Report $report, int $deadlineSetting): string
    {
        $existingReports =
            $this->reportRepository->findNotEmptyForClientAndDate($report->getClient(), $report->getDate());

        if (!$existingReports) {
            if ($report->getItems()->count()) {
                $category = 'proper';
            } else {
                $category = 'empty';
            }
        } else {
            $periodFromDueDate = ($report->getDate()->diff(new \DateTime()))->format('%a');
            if ($periodFromDueDate <= $deadlineSetting) {
                $category = 'correctionBeforeDeadline';
            } else {
                $category = 'correctionAfterDeadline';
            }
        }

        return $category;
    }
}
