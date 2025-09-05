<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Export;

use FacturaScripts\Core\Lib\Export\ExportBase;
use FacturaScripts\Core\Response;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class TicketExport extends ExportBase
{
    /**
     * @var array
     */
    protected $sendParams = [];

    public function addBusinessDocPage($model): bool
    {
        $this->sendParams['modelClassName'] = $model->modelClassName();
        $this->sendParams['modelCode'] = $model->id();
        return false;
    }

    public function addListModelPage($model, $where, $order, $offset, $columns, $title = ''): bool
    {
        return true;
    }

    public function addModelPage($model, $columns, $title = ''): bool
    {
        $this->sendParams['modelClassName'] = $model->modelClassName();
        $this->sendParams['modelCode'] = $model->id();
        return true;
    }

    public function addTablePage($headers, $rows, $options = [], $title = ''): bool
    {
        return true;
    }

    public function getDoc()
    {
        return '';
    }

    public function newDoc(string $title, int $idformat, string $langcode)
    {
    }

    public function setOrientation(string $orientation)
    {
    }

    public function show(Response &$response)
    {
        $response->headers->set('Refresh', '0; SendTicket?' . http_build_query($this->sendParams));
    }
}
