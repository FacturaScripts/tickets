<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\ApiAccess;
use FacturaScripts\Dinamic\Model\ApiKey;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
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

    /** @var string */
    public $footer;

    /** @var string */
    public $head;

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

    /** bool */
    public $receipts;

    public function clear()
    {
        parent::clear();
        $this->creationdate = date(self::DATE_STYLE);
        $this->linelen = 48;
        $this->receipts = false;
    }

    public function delete(): bool
    {
        return parent::delete() && $this->getApiKey()->delete();
    }

    public function getApiKey(): ApiKey
    {
        $apikey = new ApiKey();
        $apikey->loadFromCode($this->idapikey);
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

    public function isActive(): bool
    {
        return time() - strtotime($this->lastactivity) < self::MAX_INACTIVITY;
    }

    public static function primaryColumn(): string
    {
        return "id";
    }

    public function save(): bool
    {
        if (empty($this->idapikey) && false === $this->newApiKey()) {
            return false;
        }

        $this->apikey = $this->getApiKey()->apikey;
        $this->cutcommand = $this->toolBox()->utils()->noHtml($this->cutcommand);
        $this->name = $this->toolBox()->utils()->noHtml($this->name);
        $this->opencommand = $this->toolBox()->utils()->noHtml($this->opencommand);

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
            $apiaccess = new ApiAccess();
            $apiaccess->allowdelete = false;
            $apiaccess->allowget = true;
            $apiaccess->allowpost = false;
            $apiaccess->allowput = true;
            $apiaccess->idapikey = $apikey->id;
            $apiaccess->resource = $resource;
            if (false === $apiaccess->save()) {
                return false;
            }
        }

        $this->idapikey = $apikey->id;
        return true;
    }
}