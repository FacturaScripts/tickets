<?php
/**
 * Copyright (C) 2026 Carlos García Gómez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\TicketPrinter;

final class Mc20Printer
{
    private const BASE_URL = 'https://ai.factura.city/mc20printer/';

    public static function printUrl(): string
    {
        return self::BASE_URL . self::channel(Tools::siteUrl()) . '/print';
    }

    public static function channel(string $siteUrl): string
    {
        $normalized = self::normalize($siteUrl);

        if (self::isLocalhost($normalized)) {
            $apikey = self::firstPrinterApiKey();
            if ($apikey !== '') {
                $normalized .= ':' . $apikey;
            }
        }

        return md5($normalized);
    }

    public static function isLocalhost(string $url): bool
    {
        $parts = parse_url(trim($url));
        if ($parts === false || empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        return in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
    }

    public static function normalize(string $url): string
    {
        $url = trim($url);

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';

        $result = $scheme . '://' . $host;

        if (isset($parts['port'])) {
            $defaultPort = ($scheme === 'http' && $parts['port'] === 80)
                || ($scheme === 'https' && $parts['port'] === 443);
            if (!$defaultPort) {
                $result .= ':' . $parts['port'];
            }
        }

        $result .= $path;

        if (substr($result, -1) === '/') {
            $result = substr($result, 0, -1);
        }

        return $result;
    }

    private static function firstPrinterApiKey(): string
    {
        $printers = (new TicketPrinter())->all([], ['id' => 'ASC'], 0, 1);
        if (empty($printers)) {
            return '';
        }

        return (string)$printers[0]->apikey;
    }
}
