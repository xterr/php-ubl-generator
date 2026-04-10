<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Fixtures\Xml;

use Xterr\UBL\Xml\Mapping\XmlAttribute;
use Xterr\UBL\Xml\Mapping\XmlType;
use Xterr\UBL\Xml\Mapping\XmlValue;

#[XmlType(localName: 'AmountType', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2')]
class SampleAmount
{
    #[XmlValue(format: 'decimal')]
    public ?float $value = null;

    #[XmlAttribute(name: 'currencyID', required: true)]
    public ?string $currencyID = null;

    #[XmlAttribute(name: 'currencyCodeListVersionID')]
    public ?string $currencyCodeListVersionID = null;
}
