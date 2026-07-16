<?php
/**
 * Copyright (C) 2019-2025 Carlos García Gómez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Model;

use FacturaScripts\Core\Session;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\ApiAccess;
use FacturaScripts\Dinamic\Model\ApiKey;

/**
 * @author Carlos García Gómez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class TicketPrinter extends ModelClass
{
    use ModelTrait;

    const MAX_INACTIVITY = 600;

    /** Clave de la API key asociada a la impresora. @var string */
    public $apikey;

    /** Fecha de creación de la impresora. @var string */
    public $creationdate;

    /** Tamaño de fuente del cuerpo del ticket. @var int */
    public $font_size;

    /** Texto del pie del ticket. @var string */
    public $footer;

    /** Tamaño de fuente del pie del ticket. @var int */
    public $footer_font_size;

    /** Texto de la cabecera del ticket. @var string */
    public $head;

    /** Tamaño de fuente de la cabecera del ticket. @var int */
    public $head_font_size;

    /** Identificador de la impresora. @var int */
    public $id;

    /** Identificador de la API key asociada a la impresora. @var int */
    public $idapikey;

    /** Fecha y hora de la última actividad de la impresora. @var string */
    public $lastactivity;

    /** Longitud de línea (número de caracteres) del ticket. @var int */
    public $linelen;

    /** Nombre de la impresora. @var string */
    public $name;

    /** Nick del usuario propietario de la impresora. @var string */
    public $nick;

    /** Indica si se imprime el nombre corto de la empresa. @var bool */
    public $print_comp_shortname;

    /** Indica si se imprime el teléfono de la empresa. @var bool */
    public $print_comp_tlf;

    /** Indica si se imprimen los datos fiscales del cliente. @var bool */
    public $print_client_fiscal_data;

    /** Indica si se imprimen los recibos de la factura. @var bool */
    public $print_invoice_receipts;

    /** Indica si se imprime la descripción de las líneas. @var bool */
    public $print_lines_description;

    /** Indica si se imprime el descuento de las líneas. @var bool */
    public $print_lines_discount;

    /** Indica si se imprime el neto de las líneas. @var bool */
    public $print_lines_net;

    /** Indica si se imprime el precio de las líneas. @var bool */
    public $print_lines_price;

    /** Indica si se imprime el precio con impuestos de las líneas. @var bool */
    public $print_lines_price_tax;

    /** Indica si se imprime el precio unitario de las líneas. @var bool */
    public $print_lines_price_unitary;

    /** Indica si se imprime la cantidad de las líneas. @var bool */
    public $print_lines_quantity;

    /** Indica si se imprime la referencia de las líneas. @var bool */
    public $print_lines_reference;

    /** Indica si se imprime el total de las líneas. @var bool */
    public $print_lines_total;

    /** Indica si se imprimen las formas de pago. @var bool */
    public $print_payment_methods;

    /** Indica si se imprime el logo almacenado de la empresa. @var bool */
    public $print_stored_logo;

    /** Indica si se imprime la dirección de envío. @var bool */
    public $print_shipping_address;

    /** Tamaño de fuente del título del ticket. @var int */
    public $title_font_size;

    public function clear(): void
    {
        parent::clear();
        $this->creationdate = Tools::date();
        $this->font_size = 1;
        $this->footer_font_size = 1;
        $this->head_font_size = 1;
        $this->linelen = 48;
        $this->print_comp_shortname = false;
        $this->print_comp_tlf = false;
        $this->print_client_fiscal_data = false;
        $this->print_invoice_receipts = false;
        $this->print_lines_description = true;
        $this->print_lines_discount = false;
        $this->print_lines_net = false;
        $this->print_lines_price = false;
        $this->print_lines_price_unitary = false;
        $this->print_lines_price_tax = false;
        $this->print_lines_quantity = true;
        $this->print_lines_reference = false;
        $this->print_lines_total = true;
        $this->print_payment_methods = false;
        $this->print_stored_logo = false;
        $this->print_shipping_address = false;
        $this->title_font_size = 2;
    }

    public function delete(): bool
    {
        return parent::delete() && $this->getApiKey()->delete();
    }

    public function getApiKey(): ApiKey
    {
        $apikey = new ApiKey();
        $apikey->load($this->idapikey);
        return $apikey;
    }

    public function getCommandStr(string $command): string
    {
        return match ($command) {
            'cut' => chr('27') . chr('109'),
            'open' => chr('27') . chr('112') . chr('48') . chr('55') . chr('121'),
            default => '',
        };
    }

    public function getDashLine(): string
    {
        $line = '';
        while (strlen($line) < $this->linelen) {
            $line .= '-';
        }

        return $line;
    }

    /**
     * Abre la caja de una impresora
     * 
     * Esto lo consigue creando un ticket vacío que solo contiene la instrucción de abrir cajón
     * 
     * @return true si se ha enviado la instrucción de abrir cajón y false en cambio.
     */
    public function openDrawer(?string $codagent = null): bool
    {
        $ticket = new Ticket();
        $ticket->idprinter = $this->id;
        $ticket->title = Tools::trans('open-drawer');
        $ticket->body = $this->getCommandStr('open');

        if (!empty($codagent)) {
            $ticket->codagente = $codagent;
        }

        $user = Session::user();
        if ($user) {
            $ticket->nick = $user->nick;
        }

        return $ticket->save();
    }

    public function install(): string
    {
        // dependencias
        new ApiKey();

        return parent::install();
    }

    public function isActive(): bool
    {
        return !empty($this->lastactivity) && time() - strtotime($this->lastactivity) < self::MAX_INACTIVITY;
    }

    public function save(): bool
    {
        if (empty($this->idapikey) && false === $this->newApiKey()) {
            return false;
        }

        $this->apikey = $this->getApiKey()->apikey;

        $this->footer = Tools::noHtml($this->footer);
        $this->head = Tools::noHtml($this->head);
        $this->name = Tools::noHtml($this->name);

        return parent::save();
    }

    public static function tableName(): string
    {
        return "tickets_printers";
    }

    protected function newApiKey(): bool
    {
        $apikey = new ApiKey();
        $apikey->description = $this->name;
        $apikey->enabled = true;
        $apikey->fullaccess = false;
        $apikey->nick = $this->nick;
        if (false === $apikey->save()) {
            return false;
        }

        foreach (['ticketes', 'ticketprinteres'] as $resource) {
            $access = new ApiAccess();
            $access->allowdelete = false;
            $access->allowget = true;
            $access->allowpost = false;
            $access->allowput = true;
            $access->idapikey = $apikey->id;
            $access->resource = $resource;
            if (false === $access->save()) {
                return false;
            }
        }

        $this->idapikey = $apikey->id;
        return true;
    }
}
