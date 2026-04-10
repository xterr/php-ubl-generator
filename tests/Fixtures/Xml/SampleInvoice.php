<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Fixtures\Xml;

use Xterr\UBL\Xml\Mapping\XmlAny;
use Xterr\UBL\Xml\Mapping\XmlElement;
use Xterr\UBL\Xml\Mapping\XmlRoot;
use Xterr\UBL\Xml\Mapping\XmlType;

#[XmlRoot(localName: 'Invoice', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2')]
#[XmlType(localName: 'InvoiceType', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2')]
class SampleInvoice
{
    #[XmlElement(name: 'ID', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', required: true)]
    public ?string $id = null;

    #[XmlElement(name: 'IssueDate', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', format: 'date')]
    public ?string $issueDate = null;

    #[XmlElement(name: 'InvoiceLine', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', type: SampleLineItem::class)]
    public array $invoiceLines = [];

    #[XmlElement(name: 'CreditNoteLine', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', choiceGroup: 'lineChoice')]
    public ?SampleLineItem $creditNoteLine = null;

    #[XmlAny]
    public array $extensions = [];

    public string $unmappedProperty = '';
}
