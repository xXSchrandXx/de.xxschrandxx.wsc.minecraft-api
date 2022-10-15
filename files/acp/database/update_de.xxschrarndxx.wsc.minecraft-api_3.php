<?php

use wcf\system\database\table\column\NotNullVarchar255DatabaseTableColumn;
use wcf\system\database\table\DatabaseTable;

return [
    DatabaseTable::create('wcf1_minecraft')
        ->columns([
            NotNullVarchar255DatabaseTableColumn::create('auth')
                ->drop()
        ])
];
