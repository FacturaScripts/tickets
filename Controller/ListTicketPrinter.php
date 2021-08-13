<?php

namespace FacturaScripts\Plugins\Tickets\Controller;

class ListTicketPrinter extends \FacturaScripts\Core\Lib\ExtendedController\ListController
{
    /**
     * @return array
     */
    public function getPageData()
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

    /**
     * @param string $viewName
     */
    protected function createViewsTicket(string $viewName = "ListTicket")
    {
        $this->addView($viewName, "Ticket", "tickets", "fas fa-receipt");
        $this->addOrderBy($viewName, ["id"], "id");
        $this->addOrderBy($viewName, ["title"], "title");
        $this->addOrderBy($viewName, ["creationdate"], "date", 2);
        $this->addSearchFields($viewName, ["title"]);

        /// disable new button
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * @param string $viewName
     */
    protected function createViewsTicketPrinter(string $viewName = "ListTicketPrinter")
    {
        $this->addView($viewName, "TicketPrinter", "printers", "fas fa-print");
        $this->addOrderBy($viewName, ["id"], "id");
        $this->addOrderBy($viewName, ["name"], "name", 1);
        $this->addSearchFields($viewName, ["id", "name"]);
    }
}