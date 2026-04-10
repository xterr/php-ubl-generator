<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Fixtures\Xml;

use Xterr\UBL\Xml\Mapping\XmlElement;
use Xterr\UBL\Xml\Mapping\XmlType;

#[XmlType(localName: 'OrderType', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:Order-2')]
class SampleOrder
{
    /** @var list<SampleLineItem> */
    #[XmlElement(name: 'OrderLine', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2')]
    public array $orderLines = [];
}
