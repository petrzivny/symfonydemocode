<?php

namespace App\Entity\Report\Vat\ControlStatement;

use App\Entity\Traits\ClientTrait;
use App\Entity\Traits\CreatedAtByTrait;
use App\Entity\Traits\IdTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Report\Vat\ControlStatement\ReportRepository")
 * @ORM\Table("report_vat_control_statement_report")
 */
class Report
{
    use IdTrait;
    use ClientTrait;
    use CreatedAtByTrait;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Report\Vat\ControlStatement\ReportItem",
     *     mappedBy="report", orphanRemoval=true)
     */
    private $items;

    /**
     * Last day of a period
     * @ORM\Column(type="date")
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $category;

    /**
     * @ORM\Column(type="boolean")
     */
    private $late;

    public function __construct()
    {
        if (null == $this->id) {
            $this->generateId();
        }
        $this->items = new ArrayCollection();
    }

    /**
     * @return Collection|ReportItem[]
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ReportItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items[] = $item;
            $item->setReport($this);
        }

        return $this;
    }

    public function removeItem(ReportItem $item): self
    {
        if ($this->items->contains($item)) {
            $this->items->removeElement($item);
            // set the owning side to null (unless already changed)
            if ($item->getReport() === $this) {
                $item->setReport(null);
            }
        }

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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getCategorySummaries(array $categories): array
    {
        foreach ($this->getItems() as $item) {
            $categoryCode = $item->getCategory()->getCode();
            foreach ($item->getValuesIndexedByTaxTariffId() as $tariffId => $value) {
                $categories[$categoryCode]->summary[$tariffId]['net']
                    = ($categories[$categoryCode]->summary[$tariffId]['net'] ?? 0) + $value->getNetValue();
                $categories[$categoryCode]->summary[$tariffId]['tax'] =
                    ($categories[$categoryCode]->summary[$tariffId]['tax'] ?? 0) + $value->getTaxValue();
            }
        }

        return $categories;
    }

    public function isLate(): ?bool
    {
        return $this->late;
    }

    public function setLate(bool $late): self
    {
        $this->late = $late;

        return $this;
    }
}
