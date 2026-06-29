<?php
namespace Awz\Lockfieldsup1c\Access\Tables;

use Bitrix\Main\Access\Permission\AccessPermissionTable;

class PermissionTable extends AccessPermissionTable
{
    public static function getTableName()
    {
        return 'awz_lockfieldsup1c_permission';
    }
}