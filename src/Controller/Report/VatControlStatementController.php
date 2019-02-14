<?php

namespace App\Controller\Report;

use App\Entity\Core\AccDocument;
use App\Entity\Core\Client;
use App\Entity\Core\TaxTariff;
use App\Entity\Report\Vat\ControlStatement\Category;
use App\Entity\Report\Vat\ControlStatement\Report;
use App\Service\VatControlStatement;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/report/vat/control-statement")
 * @package App\Controller\Report
 * @IsGranted("ROLE_ACCOUNTANT")
 */
class VatControlStatementController extends AbstractController
{
    /** @var VatControlStatement */
    private $service;

    // TODO replace when client setting will be implemented
    private $clientOption = [];

    // TODO replace when global setting will be implemented
    private $globalSetting = [];

    /** @throws \Exception */
    public function __construct(VatControlStatement $service)
    {
        $this->service = $service;
        $this->clientOption['activeSince'] =  new \DateTime("2018-04-01");
        $this->globalSetting['vatControlStatementReportDeadline'] = 25;
    }

    /**
     * @Route(name="vat_reports_index")
     * @throws \Exception - only when DateTime error occurs
     */
    public function index(): Response
    {
        $lastExpectedReportDate = (new \DateTimeImmutable("last day of previous month"))->setTime(0, 0, 0);

        $reportedReports = $this->service->getLastReports(
            $this->getUser()->getActiveClient(),
            $lastExpectedReportDate->modify('first day of January '),
            $this->clientOption['activeSince']
        );

        $expectedReports = $this->service->generateFutureReports(
            $this->service->getFirstFutureReportDate($reportedReports, $this->clientOption['activeSince']),
            $lastExpectedReportDate
        );

        return $this->render('report/vat_control_statement/index.html.twig', [
            'reportedReports' => $reportedReports,
            'expectedReports' => $expectedReports,
        ]);
    }

    /**
     * @Route("/new/{begin}/{end}", name="vat_control_statement_report_new")
     * @param \DateTime $begin - first day (included) of period
     * @param \DateTime $end - last day (included) of period, this is also used as Report.date
     * @param Request $request - needed to get selected items
     * @return Response
     * @throws NonUniqueResultException
     * @throws \Doctrine\ORM\Query\QueryException
     */
    public function new(\DateTime $begin, \DateTime $end, Request $request): Response
    {
        return $this->renderReportResponse($request, null, $begin, $end);
    }

    /**
     * @Route("/{id}", name="vat_control_statement_report_show")
     * @param Request $request - needed to get selected items
     * @param $id - stored Report id
     * @return Response
     * @throws NonUniqueResultException
     * @throws \Doctrine\ORM\Query\QueryException
     */
    public function show(Request $request, $id): Response
    {
        return $this->renderReportResponse($request, $id);
    }

    /**
     * @throws NonUniqueResultException
     * @throws \Doctrine\ORM\Query\QueryException
     * @throws \Exception
     */
    private function renderReportResponse(
        Request $request,
        string $reportId = null,
        \DateTime $begin = null,
        \DateTime $end = null
    ): Response {
        /** @var $activeClient Client */
        $activeClient = $this->getUser()->getActiveClient();

        $categories = $this->getDoctrine()->getRepository(Category::class)->getAllIndexed('code');

        if (is_null($reportId)) {
            $accDocuments = $this->getDoctrine()->getRepository(AccDocument::class)
                ->prepareListForVatStatement($activeClient, $begin, $end);

            $report = $this->service->createReport(
                $accDocuments,
                $categories,
                $this->getUser(),
                $end,
                $this->globalSetting['vatControlStatementReportDeadline']
            );
        } else {
            try {
                $report = $this->getDoctrine()->getRepository(Report::class)
                    ->getByIdJoinItemsValuesAccDocumentPartner($reportId);
            } catch (NoResultException $e) {
                // TODO return 404 page
            }
        }

        try {
            $vatTariffs = $this->getDoctrine()->getRepository(TaxTariff::class)
                ->getActualVatTariffsIndexed($activeClient->getCountry(), $end ?? $report->getDate());
        } catch (\Exception $e) {
            return $this->render('error/single_error_with_navs.html.twig', ['message' => $e->getMessage()]);
        }

        $categorySummaries = $report->getCategorySummaries($categories);

        $form = $this->createFormBuilder($report, ['allow_extra_fields' => true])
            ->add('category', null, ['attr' => ['readonly' => true]])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->saveSelectedItemsToDb(
                $report,
                $form->getExtraData(),
                $this->getDoctrine()->getManager(),
                $this->globalSetting['vatControlStatementReportDeadline']
            );
            return $this->redirectToRoute('vat_reports_index');
        }

        return $this->render('report/vat_control_statement/report.html.twig', [
            'vatTariffs' => $vatTariffs,
            'report' => $report,
            'categories' => $categorySummaries,
            'form' => $form->createView(),
        ]);
    }
}
