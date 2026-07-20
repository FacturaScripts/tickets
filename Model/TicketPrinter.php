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

    /** @var string Clave de la API key asociada a la impresora. */
    public $apikey;

    /** @var string Fecha de creación de la impresora. */
    public $creationdate;

    /** @var int Tamaño de fuente del cuerpo del ticket. */
    public $font_size;

    /** @var string Texto del pie del ticket. */
    public $footer;

    /** @var int Tamaño de fuente del pie del ticket. */
    public $footer_font_size;

    /** @var string Texto de la cabecera del ticket. */
    public $head;

    /** @var int Tamaño de fuente de la cabecera del ticket. */
    public $head_font_size;

    /** @var int Identificador de la impresora. */
    public $id;

    /** @var int Identificador de la API key asociada a la impresora. */
    public $idapikey;

    /** @var string Fecha y hora de la última actividad de la impresora. */
    public $lastactivity;

    /** @var int Longitud de línea (número de caracteres) del ticket. */
    public $linelen;

    /** @var string Nombre de la impresora. */
    public $name;

    /** @var string Nick del usuario propietario de la impresora. */
    public $nick;

    /** @var bool Indica si se imprime el nombre corto de la empresa. */
    public $print_comp_shortname;

    /** @var bool Indica si se imprime el teléfono de la empresa. */
    public $print_comp_tlf;

    /** @var bool Indica si se imprimen los datos fiscales del cliente. */
    public $print_client_fiscal_data;

    /** @var bool Indica si se imprimen los recibos de la factura. */
    public $print_invoice_receipts;

    /** @var bool Indica si se imprime la descripción de las líneas. */
    public $print_lines_description;

    /** @var bool Indica si se imprime el descuento de las líneas. */
    public $print_lines_discount;

    /** @var bool Indica si se imprime el neto de las líneas. */
    public $print_lines_net;

    /** @var bool Indica si se imprime el precio de las líneas. */
    public $print_lines_price;

    /** @var bool Indica si se imprime el precio con impuestos de las líneas. */
    public $print_lines_price_tax;

    /** @var bool Indica si se imprime el precio unitario de las líneas. */
    public $print_lines_price_unitary;

    /** @var bool Indica si se imprime la cantidad de las líneas. */
    public $print_lines_quantity;

    /** @var bool Indica si se imprime la referencia de las líneas. */
    public $print_lines_reference;

    /** @var bool Indica si se imprime el total de las líneas. */
    public $print_lines_total;

    /** @var bool Indica si se imprimen las formas de pago. */
    public $print_payment_methods;

    /** @var bool Indica si se imprime el logo almacenado de la empresa. */
    public $print_stored_logo;

    /** @var bool Indica si se imprime la dirección de envío. */
    public $print_shipping_address;

    /** @var int Tamaño de fuente del título del ticket. */
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
