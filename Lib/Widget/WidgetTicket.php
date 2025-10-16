<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Widget;

use FacturaScripts\Core\Lib\Widget\WidgetTextarea;

class WidgetTicket extends WidgetTextarea
{
    protected function setValue($model): void
    {
        $value = $model->{$this->fieldname} ?? null;

        if ($value !== null && $model->base64) {
            $value = base64_decode($value) ?? null;
        }

        $this->value = $value;
    }
}
