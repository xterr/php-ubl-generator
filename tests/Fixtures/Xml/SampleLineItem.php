<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Fixtures\Xml;

use Xterr\UBL\Xml\Mapping\XmlElement;
use Xterr\UBL\Xml\Mapping\XmlType;

#[XmlType(localName: 'InvoiceLineType', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2')]
class SampleLineItem
{
    #[XmlElement(name: 'ID', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', required: true)]
    public ?string $id = null;

    #[XmlElement(name: 'LineExtensionAmount', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2')]
    public ?SampleAmount $lineExtensionAmount = null;
}
