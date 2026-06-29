<?php
namespace Awz\Lockfieldsup1c;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);

class Handlers
{
    /**
     * Обработчик события OnTabControlBegin
     * Добавляет таб с активными правилами и ссылку на редактирование
     */
    public static function OnAdminTabControlBegin(&$form)
    {

        // Проверяем, что мы на нужной странице
        $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $curPage = $request->getRequestUri();
        
        if (strpos($curPage, '/bitrix/admin/1c_admin.php') === false) {
            return;
        }

        // Получаем список активных правил
        $activeRules = self::getActiveRules();

        // Формируем HTML с настройками блокировки
        $html = '<tr><td>';
        $html .= '<div>' . Loc::getMessage('AWZ_LOCKFIELDSUP1C_ACTIVE_RULES', ['#LINK#'=>'/bitrix/admin/settings.php?mid=awz.lockfieldsup1c&lang='.LANGUAGE_ID.'&mid_menu=1']) . '</div>';

        $html .= '</td></tr>';

        $form->tabs[] = array(
            "DIV" => "awz_lockfieldsup1c_tab",
            "TAB" => Loc::getMessage('AWZ_LOCKFIELDSUP1C_TAB_TITLE'),
            "ICON" => "",
            "TITLE" => Loc::getMessage('AWZ_LOCKFIELDSUP1C_TAB_TITLE'),
            "CONTENT" => '' . $html . ''
        );
    }

    /**
     * Получает список активных правил
     * @return array
     */
    private static function getActiveRules()
    {
        $rules = [];
        
        try {
            // Получаем настройки из опций
            $rulesData = IblockLockHandler::getLockedProperties();

            foreach ($rulesData as $ruleId => $rule) {
                $rules[] = $rule;
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки при чтении настроек
        }

        return $rules;
    }

}