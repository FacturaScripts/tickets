<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ListTicketPrinter extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData["title"] = "tickets";
        $pageData["menu"] = "admin";
        $pageData["icon"] = "fas fa-print";
        return $pageData;
    }

    protected function createViews()
    {
        $this->createViewsTicketPrinter();
        $this->createViewsTicket();
    }

    protected function createViewsTicket(string $viewName = "ListTicket")
    {
        $this->addView($viewName, "Ticket", "tickets", "fas fa-receipt");
        $this->addOrderBy($viewName, ["id"], "id");
        $this->addOrderBy($viewName, ["title"], "title");
        $this->addOrderBy($viewName, ["creationdate"], "date", 2);
        $this->addSearchFields($viewName, ["title"]);

        // disable new button
        $this->setSettings($viewName, 'btnNew', false);
    }

    protected function createViewsTicketPrinter(string $viewName = "ListTicketPrinter")
    {
        $this->addView($viewName, "TicketPrinter", "printers", "fas fa-print");
        $this->addOrderBy($viewName, ["id"], "id");
        $this->addOrderBy($viewName, ["name"], "name", 1);
        $this->addSearchFields($viewName, ["id", "name"]);
    }
}