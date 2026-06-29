<?php
namespace Awz\Lockfieldsup1c\Api;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Awz\Lockfieldsup1c\IblockLockHandler;
use Awz\Lockfieldsup1c\Access\AccessController;

/**
 * Контроллер для обработки AJAX-запросов
 */
class Controller extends \Bitrix\Main\Engine\Controller
{

    public function configureActions()
    {
        $config = [
            'getProperties' => [],
        ];

        return $config;
    }
    /**
     * Получить свойства инфоблока
     */
    public function getPropertiesAction(int $iblockId)
    {

        if(!AccessController::isEditSettings()){
            $this->addError(new \Bitrix\Main\Error('Нет прав на настройку модуля'));
            return null;
        }

        try {
            $properties = IblockLockHandler::getIblockProperties($iblockId);
            return $properties;
        } catch (\Exception $e) {
            $this->addError(new \Bitrix\Main\Error('Ошибка при получении свойств: ' . $e->getMessage()));
            return null;
        }

    }

}