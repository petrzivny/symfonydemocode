<?php

namespace App\Entity\Report\Vat\ControlStatement;

use App\Entity\Traits\IdTrait;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Core\TaxTariff;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Report\Vat\ControlStatement\ReportItemValueRepository")
 * @ORM\Table("report_vat_control_statement_report_item_value")
 */
class ReportItemValue
{
    use IdTrait;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Report\Vat\ControlStatement\ReportItem", inversedBy="values")
     * @ORM\JoinColumn(nullable=false)
     */
    private $reportItem;

    /**
     * @ORM\Column(type="decimal", precision=15, scale=3)
     */
    private $netValue;

    /**
     * @ORM\Column(type="decimal", precision=15, scale=3)
     */
    private $taxValue;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\TaxTariff")
     * @ORM\JoinColumn(nullable=false)
     */
    private $taxTariff;

    public function __construct()
    {
        if (null == $this->id) {
            $this->generateId();
        }
    }

    public function getReportItem(): ?ReportItem
    {
        return $this->reportItem;
    }

    public function setReportItem(?ReportItem $reportItem): self
    {
        $this->reportItem = $reportItem;

        return $this;
    }

    public function getNetValue()
    {
        return $this->netValue;
    }

    public function setNetValue($netValue): self
    {
        $this->netValue = $netValue;

        return $this;
    }

    public function getTaxValue()
    {
        return $this->taxValue;
    }

    public function setTaxValue($taxValue): self
    {
        $this->taxValue = $taxValue;

        return $this;
    }

    public function getTaxTariff(): ?TaxTariff
    {
        return $this->taxTariff;
    }

    public function setTaxTariff(?TaxTariff $taxTariff): self
    {
        $this->taxTariff = $taxTariff;

        return $this;
    }
}
