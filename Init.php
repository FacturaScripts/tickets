<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Model\ApiAccess;
use FacturaScripts\Dinamic\Model\ApiKey;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Init extends InitClass
{
    public function init()
    {
        ExportManager::addOption('Ticket', 'ticket', 'fas fa-receipt');
    }

    public function update()
    {
        // activamos y creamos la API
        $this->setAPI();

        // renombramos la tabla de tickets de antiguas versiones
        $this->renameTicketsTable('tickets', 'tickets_docs');
    }

    private function renameTicketsTable(string $oldTable, string $newTable)
    {
        $dataBase = new DataBase();
        if (false === $dataBase->tableExists($oldTable)) {
            return;
        }

        // comprobamos las columnas de la tabla antigua
        $columns = $dataBase->getColumns($oldTable);
        if (isset($columns['id']) && isset($columns['idprinter'])) {
            $dataBase->exec("RENAME TABLE " . $oldTable . " TO " . $newTable . ";");
        }
    }

    private function setAPI()
    {
        // si se está usando megacity, no hacemos nada
        if (defined('MC20_INSTALLATION')) {
            return;
        }

        // activamos la API
        $appSettings = ToolBox::appSettings();
        $appSettings->set('default', 'enable_api', true);
        $appSettings->save();

        // creamos una clave de API, si no existe
        $apiKey = new ApiKey();
        $where = [new DataBaseWhere('description', 'tickets')];
        if (false === $apiKey->loadFromCode('', $where)) {
            $apiKey->description = 'tickets';
            $apiKey->nick = $_COOKIE['fsNick'];
            $apiKey->enabled = true;
            if (false === $apiKey->save()) {
                return;
            }

            // asignamos los permisos
            ApiAccess::addResourcesToApiKey($apiKey->id, ['ticketes', 'ticketprinteres'], true);
        }
    }
}