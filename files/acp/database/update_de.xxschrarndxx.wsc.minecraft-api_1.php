<?php

use wcf\system\database\table\column\NotNullVarchar191DatabaseTableColumn;
use wcf\system\database\table\column\NotNullVarchar255DatabaseTableColumn;
use wcf\system\database\table\DatabaseTable;
use wcf\system\database\table\index\DatabaseTableIndex;

return [
    DatabaseTable::create('wcf1_minecraft')
        ->columns([
            NotNullVarchar191DatabaseTableColumn::create('user'),
            NotNullVarchar255DatabaseTableColumn::create('password')
        ])
        ->indices([
            DatabaseTableIndex::create('user')
                ->type(DatabaseTableIndex::UNIQUE_TYPE)
                ->columns(['user'])
        ])
];
