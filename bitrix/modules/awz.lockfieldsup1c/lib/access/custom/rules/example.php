<?php
namespace Awz\Lockfieldsup1c\Access\Custom\Rules;

use Bitrix\Main\Access\AccessibleItem;
use Awz\Lockfieldsup1c\Access\Custom\PermissionDictionary;
use Awz\Lockfieldsup1c\Access\Custom\Helper;

class Example extends \Bitrix\Main\Access\Rule\AbstractRule
{

    public function execute(AccessibleItem $item = null, $params = null): bool
    {
        if ($this->user->isAdmin() && !Helper::ADMIN_DECLINE)
        {
            return true;
        }
        if ($this->user->getPermission(PermissionDictionary::MODULE_SETT_VIEW))
        {
            return true;
        }
        return false;
    }

}