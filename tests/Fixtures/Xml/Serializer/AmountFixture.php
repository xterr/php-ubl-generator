<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Fixtures\Xml\Serializer;

use Xterr\UBL\Xml\Mapping\XmlAttribute;
use Xterr\UBL\Xml\Mapping\XmlType;
use Xterr\UBL\Xml\Mapping\XmlValue;

#[XmlType(localName: 'AmountType', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2')]
final class AmountFixture
{
    #[XmlValue]
    private ?string $value = null;

    #[XmlAttribute(name: 'currencyID')]
    private ?string $currencyId = null;

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getCurrencyId(): ?string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(?string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }
}
