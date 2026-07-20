<?php
/**
 * Copyright (C) 2019-2025 Carlos García Gómez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Agente;

/**
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class Ticket extends ModelClass
{
    use ModelTrait;

    /** @var float Versión de la app que ha generado el ticket. */
    public $appversion;

    /** @var bool Indica si el cuerpo del ticket viene codificado en base64. */
    public $base64;

    /** @var string Contenido del ticket a imprimir. */
    public $body;

    /** @var string Código del agente que ha generado el ticket. */
    public $codagente;

    /** @var string Fecha y hora de creación del ticket. */
    public $creationdate;

    /** @var int Identificador del ticket. */
    public $id;

    /** @var int Identificador de la impresora asociada al ticket. */
    public $idprinter;

    /** @var string Nick del usuario que ha generado el ticket. */
    public $nick;

    /** @var int Segundos transcurridos hasta que el ticket se ha impreso. */
    public $printdelay;

    /** @var bool Indica si el ticket ya se ha impreso. */
    public $printed;

    /** @var string Título del ticket. */
    public $title;

    public function clear(): void
    {
        parent::clear();
        $this->appversion = 0.0;
        $this->base64 = false;
        $this->creationdate = Tools::dateTime();
        $this->printed = false;
    }

    public function getPrinter(): TicketPrinter
    {
        $printer = new TicketPrinter();
        $printer->load($this->idprinter);
        return $printer;
    }

    public function install(): string
    {
        // dependencias
        new Agente();
        new TicketPrinter();

        return parent::install();
    }

    public function save(): bool
    {
        $this->body = $this->base64 ? $this->body : $this->sanitize($this->body);
        $this->title = Tools::noHtml($this->title);

        if ($this->printed && empty($this->printdelay)) {
            // calculamos cuantos segundos ha tardado en imprimir
            $this->printdelay = time() - strtotime($this->creationdate);
        }

        return parent::save();
    }

    public static function tableName(): string
    {
        return "tickets_docs";
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return $type === 'list' ? $this->getPrinter()->url() : parent::url($type, $list);
    }

    protected function sanitize(?string $txt): string
    {
        $changes = ['/à/' => 'a', '/á/' => 'a', '/â/' => 'a', '/ã/' => 'a', '/ä/' => 'a',
            '/å/' => 'a', '/æ/' => 'ae', '/ç/' => 'c', '/è/' => 'e', '/é/' => 'e', '/ê/' => 'e',
            '/ë/' => 'e', '/ì/' => 'i', '/í/' => 'i', '/î/' => 'i', '/ï/' => 'i', '/ð/' => 'd',
            '/ñ/' => 'n', '/ò/' => 'o', '/ó/' => 'o', '/ô/' => 'o', '/õ/' => 'o', '/ö/' => 'o',
            '/ő/' => 'o', '/ø/' => 'o', '/ù/' => 'u', '/ú/' => 'u', '/û/' => 'u', '/ü/' => 'u',
            '/ű/' => 'u', '/ý/' => 'y', '/þ/' => 'th', '/ÿ/' => 'y',
            '/&quot;/' => '-', '/´/' => '/\'/', '/€/' => 'EUR', '/º/' => '.',
            '/À/' => 'A', '/Á/' => 'A', '/Â/' => 'A', '/Ä/' => 'A',
            '/Ç/' => 'C', '/È/' => 'E', '/É/' => 'E', '/Ê/' => 'E',
            '/Ë/' => 'E', '/Ì/' => 'I', '/Í/' => 'I', '/Î/' => 'I', '/Ï/' => 'I',
            '/Ñ/' => 'N', '/Ò/' => 'O', '/Ó/' => 'O', '/Ô/' => 'O', '/Ö/' => 'O',
            '/Ù/' => 'U', '/Ú/' => 'U', '/Û/' => 'U', '/Ü/' => 'U',
            '/Ý/' => 'Y', '/Ÿ/' => 'Y'
        ];

        $txtNoHtml = Tools::noHtml($txt) ?? '';
        return preg_replace(array_keys($changes), $changes, $txtNoHtml);
    }
}
