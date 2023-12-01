<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditTicket extends EditController
{
    public function getModelClassName(): string
    {
        return "Ticket";
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData["title"] = "Ticket";
        $pageData["icon"] = "fas fa-search";
        return $pageData;
    }

    protected function createViews()
    {
        parent::createViews();

        // desactivamos los botones de nuevo y opciones
        $mvn = $this->getMainViewName();
        $this->setSettings($mvn, 'btnNew', false);
        $this->setSettings($mvn, 'btnOptions', false);
    }
}
