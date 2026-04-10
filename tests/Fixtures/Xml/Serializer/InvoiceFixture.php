<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Fixtures\Xml\Serializer;

use Xterr\UBL\Xml\Mapping\XmlAny;
use Xterr\UBL\Xml\Mapping\XmlElement;
use Xterr\UBL\Xml\Mapping\XmlNamespace as Ns;
use Xterr\UBL\Xml\Mapping\XmlRoot;
use Xterr\UBL\Xml\Mapping\XmlType;

#[XmlRoot(localName: 'Invoice', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2')]
#[XmlType(localName: 'InvoiceType', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2')]
final class InvoiceFixture
{
    #[XmlElement(name: 'ID', namespace: Ns::CBC)]
    private ?string $id = null;

    #[XmlElement(name: 'IssueDate', namespace: Ns::CBC, format: 'Y-m-d')]
    private ?\DateTimeImmutable $issueDate = null;

    #[XmlElement(name: 'TaxAmount', namespace: Ns::CBC, type: AmountFixture::class)]
    private ?AmountFixture $taxAmount = null;

    /** @var list<AmountFixture> */
    #[XmlElement(name: 'Note', namespace: Ns::CBC, type: AmountFixture::class)]
    private array $notes = [];

    #[XmlAny]
    private array $extensions = [];

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getIssueDate(): ?\DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(?\DateTimeImmutable $issueDate): void
    {
        $this->issueDate = $issueDate;
    }

    public function getTaxAmount(): ?AmountFixture
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(?AmountFixture $taxAmount): void
    {
        $this->taxAmount = $taxAmount;
    }

    /** @return list<AmountFixture> */
    public function getNotes(): array
    {
        return $this->notes;
    }

    /** @param list<AmountFixture> $notes */
    public function setNotes(array $notes): void
    {
        $this->notes = $notes;
    }

    public function addToNotes(AmountFixture $note): void
    {
        $this->notes[] = $note;
    }

    /** @return list<string> */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /** @param list<string> $extensions */
    public function setExtensions(array $extensions): void
    {
        $this->extensions = $extensions;
    }
}
