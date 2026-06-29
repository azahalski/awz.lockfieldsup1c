<?php
namespace Awz\Lockfieldsup1c\Access\Tables;

use Bitrix\Main\Access\Role\AccessRoleTable;

class RoleTable extends AccessRoleTable
{
    public static function getTableName()
    {
        return 'awz_lockfieldsup1c_role';
    }
}