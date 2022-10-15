<?php

use wcf\system\database\table\column\NotNullVarchar255DatabaseTableColumn;
use wcf\system\database\table\DatabaseTable;
use wcf\system\database\table\index\DatabaseTableIndex;

return [
    DatabaseTable::create('wcf1_minecraft')
        ->columns([
            NotNullVarchar255DatabaseTableColumn::create('auth')
                ->drop()
        ])
        ->indices([
            DatabaseTableIndex::create('user')
                ->type(DatabaseTableIndex::UNIQUE_TYPE)
                ->columns(['user'])
        ])
];
