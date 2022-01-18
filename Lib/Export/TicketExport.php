<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Export;

use FacturaScripts\Core\Lib\Export\ExportBase;
use Symfony\Component\HttpFoundation\Response;
use function http_build_query;

/**
 *
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
        $this->sendParams['modelCode'] = $model->primaryColumnValue();
        return false;
    }

    public function addListModelPage($model, $where, $order, $offset, $columns, $title = ''): bool
    {
        return true;
    }

    public function addModelPage($model, $columns, $title = ''): bool
    {
        $this->sendParams['modelClassName'] = $model->modelClassName();
        $this->sendParams['modelCode'] = $model->primaryColumnValue();
        return true;
    }

    public function addTablePage($headers, $rows): bool
    {
        return true;
    }

    public function getDoc()
    {
        return '';
    }

    public function newDoc(string $title, int $idformat, string $langcode)
    {
        // TODO: Implement newDoc() method.
    }

    public function setOrientation(string $orientation)
    {
        // TODO: Implement setOrientation() method.
    }

    public function show(Response &$response)
    {
        $response->headers->set('Refresh', '0; SendTicket?' . http_build_query($this->sendParams));
    }
}