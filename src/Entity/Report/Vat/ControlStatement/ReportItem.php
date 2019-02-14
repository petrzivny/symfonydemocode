<?php

namespace App\Entity\Report\Vat\ControlStatement;

use App\Entity\Traits\CreatedAtByTrait;
use App\Entity\Traits\IdTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Core\AccDocument;
use App\Entity\Core\TaxTariff;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Report\Vat\ControlStatement\ReportItemRepository")
 * @ORM\Table("report_vat_control_statement_report_item")
 */
class ReportItem
{
    use IdTrait;
    use CreatedAtByTrait;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\AccDocument" , inversedBy="vatControlStatementReportedItems")
     * @ORM\JoinColumn(nullable=false)
     */
    private $accDocument;

    /**
     * @ORM\Column(type="date")
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Report\Vat\ControlStatement\Category")
     * @ORM\JoinColumn(nullable=false)
     */
    private $category;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Report\Vat\ControlStatement\ReportItemValue", mappedBy="reportItem")
     * @var ReportItemValue[]
     */
    private $values;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Report\Vat\ControlStatement\Report", inversedBy="items")
     * @ORM\JoinColumn(nullable=false)
     */
    private $report;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $syncNumber;

    public function __construct()
    {
        if (null == $this->id) {
            $this->generateId();
        }
        $this->values = new ArrayCollection();
    }

    public function getAccDocument(): ?AccDocument
    {
        return $this->accDocument;
    }

    public function setAccDocument(?AccDocument $accDocument): self
    {
        $this->accDocument = $accDocument;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection|ReportItemValue[]
     */
    public function getValues(): Collection
    {
        return $this->values;
    }

    /**
     * @return Collection|ReportItemValue[]
     */
    public function getValuesIndexedByTaxTariffId(): Collection
    {
        $result = new ArrayCollection();
        foreach ($this->values as $value) {
            $result->set($value->getTaxTariff()->getId(), $value);
        }

        return $result;
    }

    /**
     * @param AccDocument $accDocument
     */
    public function setValuesIndexedByTaxTariffId(AccDocument $accDocument): void
    {
        foreach ($accDocument->getAccDocumentItems() as $accDocumentItem) {
            if ($vatTariff = $accDocumentItem->getTaxTariff()) {
                if (!isset($values[$vatTariff->getId()])) {
                    /** @var $values ReportItemValue[] */
                    $values[$vatTariff->getId()] = new ReportItemValue();
                    $values[$vatTariff->getId()]->setTaxTariff($vatTariff);
                    $values[$vatTariff->getId()]->setNetValue($accDocument->getLocalNetValueSum($vatTariff));
                    $values[$vatTariff->getId()]->setTaxValue($accDocument->getLocalTaxValueSum($vatTariff));
                }
            }
        }

        if (isset($values)) {
            foreach ($values as $vatTariffId => $value) {
                $this->addReportItemValue($value, $vatTariffId);
            }
        }
    }

    public function addReportItemValue(ReportItemValue $value, string $index = null): self
    {
        if (!$this->values->contains($value)) {
            if ($index) {
                $this->values[$index] = $value;
            } else {
                $this->values[] = $value;
            }
            $value->setReportItem($this);
        }

        return $this;
    }

    public function removeReportItemValue(ReportItemValue $value): self
    {
        if ($this->values->contains($value)) {
            $this->values->removeElement($value);
            // set the owning side to null (unless already changed)
            if ($value->getReportItem() === $this) {
                $value->setReportItem(null);
            }
        }

        return $this;
    }

    public function getNetSum(TaxTariff $tariff = null): float
    {
        $value = 0;
        foreach ($this->values as $item) {
            if (null == $tariff || $item->getTaxTariff() === $tariff) {
                $value += $item->getNetValue();
            }
        }
        return $value;
    }

    public function getTaxSum(TaxTariff $tariff = null): float
    {
        $value = 0;
        foreach ($this->values as $item) {
            if (null == $tariff || $item->getTaxTariff() === $tariff) {
                $value += $item->getTaxValue();
            }
        }
        return $value;
    }

    public function getReport(): ?Report
    {
        return $this->report;
    }

    public function setReport(?Report $report): self
    {
        $this->report = $report;

        return $this;
    }

    public function getSyncNumber(): ?string
    {
        return $this->syncNumber;
    }

    public function setSyncNumber(?string $syncNumber): self
    {
        $this->syncNumber = $syncNumber;

        return $this;
    }
}
