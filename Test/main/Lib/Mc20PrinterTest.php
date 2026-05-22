<?php
/**
 * Copyright (C) 2025 Carlos García Gómez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Test\Plugins\Tickets\Lib;

use FacturaScripts\Plugins\Tickets\Lib\Mc20Printer;
use PHPUnit\Framework\TestCase;

final class Mc20PrinterTest extends TestCase
{
    public function testTrimsSurroundingWhitespace(): void
    {
        $this->assertSame(
            'https://example.com',
            Mc20Printer::normalize("  https://example.com  \n")
        );
    }

    public function testLowercasesSchemeAndHost(): void
    {
        $this->assertSame(
            'https://example.com',
            Mc20Printer::normalize('HTTPS://Example.COM')
        );
    }

    public function testPreservesPathCase(): void
    {
        $this->assertSame(
            'https://example.com/MyPath/CaseSensitive',
            Mc20Printer::normalize('HTTPS://EXAMPLE.com/MyPath/CaseSensitive')
        );
    }

    public function testStripsDefaultHttpPort(): void
    {
        $this->assertSame(
            'http://example.com/api',
            Mc20Printer::normalize('http://example.com:80/api')
        );
    }

    public function testStripsDefaultHttpsPort(): void
    {
        $this->assertSame(
            'https://example.com',
            Mc20Printer::normalize('https://example.com:443/')
        );
    }

    public function testKeepsNonDefaultPort(): void
    {
        $this->assertSame(
            'http://example.com:8080/api',
            Mc20Printer::normalize('http://example.com:8080/api')
        );
    }

    public function testKeepsHttpsOnNonDefaultPort(): void
    {
        $this->assertSame(
            'https://example.com:8443',
            Mc20Printer::normalize('https://example.com:8443')
        );
    }

    public function testDropsQueryAndFragment(): void
    {
        $this->assertSame(
            'https://example.com/path',
            Mc20Printer::normalize('https://example.com/path?foo=bar&x=1#section')
        );
    }

    public function testRemovesTrailingSlashOnPath(): void
    {
        $this->assertSame(
            'https://example.com/api',
            Mc20Printer::normalize('https://example.com/api/')
        );
    }

    public function testRemovesTrailingSlashWhenPathIsOnlySlash(): void
    {
        $this->assertSame(
            'https://example.com',
            Mc20Printer::normalize('https://example.com/')
        );
    }

    public function testNoTrailingSlashIsKept(): void
    {
        $this->assertSame(
            'https://example.com',
            Mc20Printer::normalize('https://example.com')
        );
    }

    public function testCombinedRules(): void
    {
        $this->assertSame(
            'https://example.com/Path',
            Mc20Printer::normalize('  HTTPS://Example.COM:443/Path/?q=1#frag  ')
        );
    }

    public function testIsLocalhostDetectsLocalhost(): void
    {
        $this->assertTrue(Mc20Printer::isLocalhost('http://localhost'));
        $this->assertTrue(Mc20Printer::isLocalhost('https://LOCALHOST/'));
        $this->assertTrue(Mc20Printer::isLocalhost('http://localhost:8080/api'));
    }

    public function testIsLocalhostDetectsIpv4Loopback(): void
    {
        $this->assertTrue(Mc20Printer::isLocalhost('http://127.0.0.1'));
        $this->assertTrue(Mc20Printer::isLocalhost('http://127.0.0.1:80/'));
    }

    public function testIsLocalhostDetectsIpv6Loopback(): void
    {
        $this->assertTrue(Mc20Printer::isLocalhost('http://[::1]/'));
        $this->assertTrue(Mc20Printer::isLocalhost('http://[::1]:8080/api'));
    }

    public function testIsLocalhostFalseForPublicHost(): void
    {
        $this->assertFalse(Mc20Printer::isLocalhost('https://example.com'));
        $this->assertFalse(Mc20Printer::isLocalhost('https://127.0.0.2'));
        $this->assertFalse(Mc20Printer::isLocalhost('https://my-localhost.com'));
    }

    public function testIsLocalhostFalseForInvalidUrl(): void
    {
        $this->assertFalse(Mc20Printer::isLocalhost(''));
        $this->assertFalse(Mc20Printer::isLocalhost('not-a-url'));
    }

    public function testMd5MatchesNormalizedForm(): void
    {
        $a = md5(Mc20Printer::normalize('HTTPS://Example.COM:443/'));
        $b = md5(Mc20Printer::normalize('https://example.com'));
        $this->assertSame($a, $b);
    }
}
