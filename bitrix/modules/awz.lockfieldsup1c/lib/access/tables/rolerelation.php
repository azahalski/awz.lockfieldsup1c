<?php
namespace Awz\Lockfieldsup1c\Access\Tables;

use Bitrix\Main\Access\Role\AccessRoleRelationTable;

class RoleRelationTable extends AccessRoleRelationTable
{
    public static function getTableName()
    {
        return 'awz_lockfieldsup1c_role_relation';
    }

}