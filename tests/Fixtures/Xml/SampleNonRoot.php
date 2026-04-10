<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Fixtures\Xml;

use Xterr\UBL\Xml\Mapping\XmlElement;
use Xterr\UBL\Xml\Mapping\XmlNamespace as Ns;
use Xterr\UBL\Xml\Mapping\XmlType;

#[XmlType(localName: 'PartyType', namespace: Ns::CAC)]
final class SampleNonRoot
{
    #[XmlElement(name: 'Name', namespace: Ns::CBC)]
    private ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
