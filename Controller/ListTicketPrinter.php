<?php
/**
 * Copyright (C) 2019-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
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
        $this->addView($viewName, "Ticket", "tickets", "fas fa-receipt")
            ->addOrderBy(["id"], "id")
            ->addOrderBy(["title"], "title")
            ->addOrderBy(["creationdate"], "date", 2)
            ->addSearchFields(["title"])
            ->setSettings('btnNew', false);
    }

    protected function createViewsTicketPrinter(string $viewName = "ListTicketPrinter")
    {
        $this->addView($viewName, "TicketPrinter", "printers", "fas fa-print")
            ->addOrderBy(["id"], "id")
            ->addOrderBy(["name"], "name", 1)
            ->addSearchFields(["id", "name"]);
    }
}