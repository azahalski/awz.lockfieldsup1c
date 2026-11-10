<?php
$MESS['AWZ_LOCKFIELDSUP1C_OPT_TITLE'] = "AWZ: Блокировщик свойств";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_L_BTN_SAVE'] = "Сохранить настройки";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_SECT2'] = "Права доступа";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_SECT1'] = "Общие настройки";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_SECT3'] = "Защита полей от обновления";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_FIELDS_DESC'] = "Настройка защиты свойств инфоблоков от обновления.<br>После добавления правила сохраните настройки.";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_IBLOCK'] = "Инфоблок";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_PROPERTY'] = "Свойство";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_TYPE'] = "Тип блокировки";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_TYPE_ALL'] = "Запретить любое обновление";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_TYPE_EMPTY'] = "Разрешить обновлять только пустые значения";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_ADD'] = "Добавить правило";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_DELETE'] = "Удалить";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_SELECT_IBLOCK'] = "Выберите инфоблок";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_SELECT_PROPERTY'] = "Выберите свойство";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_NO_PROPERTIES'] = "Нет доступных свойств с символьным кодом";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_NAME'] = "Название";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_CODE'] = "Код";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_NAME_PLACEHOLDER'] = "Введите название";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_LOCK_CODE_PLACEHOLDER'] = "Введите код";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_MODULE_ENABLED'] = "Включить модуль защиты полей";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_REQUEST_PARAMS'] = "Параметры запроса";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_REQUEST_PARAMS_DESC'] = "Параметры через &, которые должны быть в URL для срабатывания правила. <br><b>Пример</b>: type=catalog&mode=import (для 1с), или /bitrix/admin/iblock_section_admin.php?IBLOCK_ID=1,type=catalog&mode=import (запрет редактирования кода раздела в списке для инфоблока 1 и для 1с)";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_ENABLE_JOURNAL_LOG'] = "Включить логирование в журнал событий";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_ENABLE_PSR_LOG'] = "Включить PSR-лог в файл (ротация 20Мб, /local/logs/awz.lockfieldsup1c.log)";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_PSR_LOG_DESC'] = <<<'LANGSTR'
Пишет PSR-лог модуля в файл /local/logs/awz.lockfieldsup1c.log с ротацией при достижении 20Мб. Изменяет секцию 'loggers' в bitrix/.settings.php (constructorParams сохраняется массивом, без closure). Если секция 'loggers' помечена readonly, включение через галочку не сработает — добавьте вручную по примеру ниже.<br><br><b>Пример ручной настройки в bitrix/.settings.php</b>:<br><pre style='overflow:auto;max-width:600px;border:1px solid #ccc;padding:8px;white-space:pre-wrap;'>'loggers' => [
    'readonly'=>false,
    'value'=>[
        'awz.lockfieldsup1c'=>[
            'className' => '\Bitrix\Main\Diag\FileLogger',
            'constructorParams' => [$_SERVER['DOCUMENT_ROOT'].'/local/logs/awz.lockfieldsup1c.log', 20*1024*1024],
            'level' => \Psr\Log\LogLevel::DEBUG
        ]
    ]
],</pre>
LANGSTR;
$MESS['AWZ_LOCKFIELDSUP1C_OPT_JOURNAL_LOG_DESC'] = <<<'LANGSTR'
Записи появляются в журнале событий (Журнал событий) с типами AWZ_LOCKFIELDSUP1C_INFO / AWZ_LOCKFIELDSUP1C_WARNING / AWZ_LOCKFIELDSUP1C_ERROR. Логирование самого логгера (PSR-логгер) включается в настройках модуля (PSR-лог в файл) либо вручную в .settings.php (loggers).
LANGSTR;
$MESS['AWZ_LOCKFIELDSUP1C_OPT_JOURNAL_LOG_LINK'] = "Открыть журнал событий модуля";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_USER_IDS'] = "ID пользователей";
$MESS['AWZ_LOCKFIELDSUP1C_OPT_USER_IDS_DESC'] = "ID через запятую (сработает для всех перечисленных пользователей). Добавьте ! в начало для инверсии (правило сработает для всех, кроме указанных). <br><b>Пример</b>: 1,2,3 или !1,2, пустое значение - без фильтрации пользователей";

