<?php

namespace FacturaScripts\Plugins\Tickets\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\ApiAccess;
use FacturaScripts\Dinamic\Model\ApiKey;
use function date;

class TicketPrinter extends ModelClass
{
    use ModelTrait;

    /**
     * @var string
     */
    public $apikey;

    /**
     * @var string
     */
    public $creationdate;

    /**
     * @var string
     */
    public $cutcommand;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $idapikey;

    /**
     * @var string
     */
    public $lastactivity;

    /**
     * @var int
     */
    public $linelen;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $nick;

    /**
     * @var string
     */
    public $opencommand;

    public function clear()
    {
        parent::clear();
        $this->creationdate = date(self::DATE_STYLE);
        $this->linelen = 48;
    }

    /**
     * @return bool
     */
    public function delete()
    {
        return parent::delete() && $this->getApiKey()->delete();
    }

    /**
     * @return ApiKey
     */
    public function getApiKey(): ApiKey
    {
        $apikey = new ApiKey();
        $apikey->loadFromCode($this->idapikey);
        return $apikey;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return \time() - \strtotime($this->lastactivity) < 300;
    }

    /**
     * @return string
     */
    public static function primaryColumn()
    {
        return "id";
    }

    /**
     * @return bool
     */
    public function save()
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

    /**
     * @return string
     */
    public static function tableName()
    {
        return "tickets_printers";
    }

    /**
     * @return bool
     */
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