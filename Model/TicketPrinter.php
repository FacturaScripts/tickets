<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\ApiAccess;
use FacturaScripts\Dinamic\Model\ApiKey;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class TicketPrinter extends ModelClass
{
    use ModelTrait;

    const MAX_INACTIVITY = 600;

    /** @var string */
    public $apikey;

    /** @var string */
    public $creationdate;

    /** @var string */
    public $cutcommand;

    /** @var int */
    public $font_size;

    /** @var string */
    public $footer;

    /** @var int */
    public $footer_font_size;

    /** @var string */
    public $head;

    /** @var int */
    public $head_font_size;

    /** @var int */
    public $id;

    /** @var int */
    public $idapikey;

    /** @var string */
    public $lastactivity;

    /** @var int */
    public $linelen;

    /** @var string */
    public $name;

    /** @var string */
    public $nick;

    /** @var string */
    public $opencommand;

    /** @var bool */
    public $print_comp_shortname;

    /** @var bool */
    public $print_comp_tlf;

    /** @var bool */
    public $print_invoice_receipts;

    /** @var bool */
    public $print_lines_description;

    /** @var bool */
    public $print_lines_discount;

    /** @var bool */
    public $print_lines_net;

    /** @var bool */
    public $print_lines_price;

    /** @var bool */
    public $print_lines_price_tax;

    /** @var bool */
    public $print_lines_price_unitary;

    /** @var bool */
    public $print_lines_quantity;

    /** @var bool */
    public $print_lines_reference;

    /** @var bool */
    public $print_lines_total;

    /** @var bool */
    public $print_payment_methods;

    /** @var bool */
    public $print_stored_logo;

    /** @var int */
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
        $commandStr = '';
        switch ($command) {
            case 'cut':
                if (empty($this->cutcommand)) {
                    break;
                }

                $chars = explode('.', $this->cutcommand);
                foreach ($chars as $char) {
                    $commandStr .= chr($char);
                }
                break;

            case 'open':
                if (empty($this->opencommand)) {
                    break;
                }

                $chars = explode('.', $this->opencommand);
                foreach ($chars as $char) {
                    $commandStr .= chr($char);
                }
                break;
        }

        return $commandStr;
    }

    public function getDashLine(): string
    {
        $line = '';
        while (strlen($line) < $this->linelen) {
            $line .= '-';
        }

        return $line;
    }

    public function install(): string
    {
        // dependencias
        new ApiKey();

        return parent::install();
    }

    public function isActive(): bool
    {
        return time() - strtotime($this->lastactivity) < self::MAX_INACTIVITY;
    }

    public function save(): bool
    {
        if (empty($this->idapikey) && false === $this->newApiKey()) {
            return false;
        }

        $this->apikey = $this->getApiKey()->apikey;
        $this->cutcommand = Tools::noHtml($this->cutcommand);
        $this->footer = Tools::noHtml($this->footer);
        $this->head = Tools::noHtml($this->head);
        $this->name = Tools::noHtml($this->name);
        $this->opencommand = Tools::noHtml($this->opencommand);

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
