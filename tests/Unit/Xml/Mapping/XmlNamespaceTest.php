<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Tests\Unit\Xml\Mapping;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xterr\UBL\Xml\Mapping\XmlNamespace;

final class XmlNamespaceTest extends TestCase
{
    #[Test]
    public function prefixForReturnsCbcForCommonBasicComponents(): void
    {
        self::assertSame('cbc', XmlNamespace::prefixFor(XmlNamespace::CBC));
    }

    #[Test]
    public function prefixForReturnsCacForCommonAggregateComponents(): void
    {
        self::assertSame('cac', XmlNamespace::prefixFor(XmlNamespace::CAC));
    }

    #[Test]
    public function prefixForReturnsExtForCommonExtensionComponents(): void
    {
        self::assertSame('ext', XmlNamespace::prefixFor(XmlNamespace::EXT));
    }

    #[Test]
    public function prefixForReturnsSigForCommonSignatureComponents(): void
    {
        self::assertSame('sig', XmlNamespace::prefixFor(XmlNamespace::SIG));
    }

    #[Test]
    public function prefixForReturnsSacForSignatureAggregateComponents(): void
    {
        self::assertSame('sac', XmlNamespace::prefixFor(XmlNamespace::SAC));
    }

    #[Test]
    public function prefixForReturnsSbcForSignatureBasicComponents(): void
    {
        self::assertSame('sbc', XmlNamespace::prefixFor(XmlNamespace::SBC));
    }

    #[Test]
    public function prefixForReturnsDsForXmlDsig(): void
    {
        self::assertSame('ds', XmlNamespace::prefixFor(XmlNamespace::DS));
    }

    #[Test]
    public function prefixForReturnsCctsForCoreComponentType(): void
    {
        self::assertSame('ccts', XmlNamespace::prefixFor(XmlNamespace::CCTS));
    }

    #[Test]
    public function prefixForReturnsUdtForUnqualifiedDataTypes(): void
    {
        self::assertSame('udt', XmlNamespace::prefixFor(XmlNamespace::UDT));
    }

    #[Test]
    public function prefixForReturnsQdtForQualifiedDataTypes(): void
    {
        self::assertSame('qdt', XmlNamespace::prefixFor(XmlNamespace::QDT));
    }

    #[Test]
    public function prefixForReturnsNullForUnknownNamespace(): void
    {
        self::assertNull(XmlNamespace::prefixFor('urn:unknown:namespace'));
    }

    #[Test]
    public function namespaceForReturnsCbcUri(): void
    {
        self::assertSame(XmlNamespace::CBC, XmlNamespace::namespaceFor('cbc'));
    }

    #[Test]
    public function namespaceForReturnsCacUri(): void
    {
        self::assertSame(XmlNamespace::CAC, XmlNamespace::namespaceFor('cac'));
    }

    #[Test]
    public function namespaceForReturnsExtUri(): void
    {
        self::assertSame(XmlNamespace::EXT, XmlNamespace::namespaceFor('ext'));
    }

    #[Test]
    public function namespaceForReturnsSigUri(): void
    {
        self::assertSame(XmlNamespace::SIG, XmlNamespace::namespaceFor('sig'));
    }

    #[Test]
    public function namespaceForReturnsSacUri(): void
    {
        self::assertSame(XmlNamespace::SAC, XmlNamespace::namespaceFor('sac'));
    }

    #[Test]
    public function namespaceForReturnsSbcUri(): void
    {
        self::assertSame(XmlNamespace::SBC, XmlNamespace::namespaceFor('sbc'));
    }

    #[Test]
    public function namespaceForReturnsDsUri(): void
    {
        self::assertSame(XmlNamespace::DS, XmlNamespace::namespaceFor('ds'));
    }

    #[Test]
    public function namespaceForReturnsCctsUri(): void
    {
        self::assertSame(XmlNamespace::CCTS, XmlNamespace::namespaceFor('ccts'));
    }

    #[Test]
    public function namespaceForReturnsUdtUri(): void
    {
        self::assertSame(XmlNamespace::UDT, XmlNamespace::namespaceFor('udt'));
    }

    #[Test]
    public function namespaceForReturnsQdtUri(): void
    {
        self::assertSame(XmlNamespace::QDT, XmlNamespace::namespaceFor('qdt'));
    }

    #[Test]
    public function namespaceForReturnsNullForUnknownPrefix(): void
    {
        self::assertNull(XmlNamespace::namespaceFor('unknown'));
    }
}
