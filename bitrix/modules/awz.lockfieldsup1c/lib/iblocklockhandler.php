<?php
namespace Awz\Lockfieldsup1c;

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;

/**
 * Класс для защиты свойств инфоблоков от обновления из 1С
 */
class IblockLockHandler
{
    // Типы блокировки
    const LOCK_TYPE_ALL = 'all';           // Запретить любое обновление
    const LOCK_TYPE_EMPTY = 'empty';       // Разрешить обновлять только пустые значения

    // Системные поля
    const SYSTEM_FIELD_SECTION_NAME = 'SECTION_NAME';
    const SYSTEM_FIELD_SECTION_CODE = 'SECTION_CODE';
    const SYSTEM_FIELD_SECTION_IBLOCK_SECTION_ID = 'SECTION_IBLOCK_SECTION_ID';
    const SYSTEM_FIELD_ELEMENT_NAME = 'ELEMENT_NAME';
    const SYSTEM_FIELD_ELEMENT_CODE = 'ELEMENT_CODE';

    const SYSTEM_FIELD_SECTION_TYPE = 'section';
    const SYSTEM_FIELD_ELEMENT_TYPE = 'element';

    // Список защищенных свойств с типами блокировки
    private static $protectedProperties = null;

    // Сюда временно сохраняем старые значения свойств для текущего товара
    private static $savedValues = [];

    // Сюда временно сохраняем старые значения свойств для текущего раздела
    private static $savedSectionValues = [];

    private static $lockElements = [];
    private static $lockSections = [];

    /**
     * Получить список защищенных свойств из настроек модуля
     */
    private static function getProtectedProperties()
    {
        if (self::$protectedProperties === null) {
            $moduleId = 'awz.lockfieldsup1c';
            $propsJson = Option::get($moduleId, 'LOCKED_PROPERTIES', '[]');
            self::$protectedProperties = json_decode($propsJson, true) ?: [];
        }
        return self::$protectedProperties;
    }

    /**
     * Получить тип блокировки для свойства
     */
    private static function getLockType($iblockId, $propCode)
    {
        $props = self::getProtectedProperties();
        $key = $iblockId . '_' . $propCode;

        if (isset($props[$key])) {
            return $props[$key]['lock_type'] ?? self::LOCK_TYPE_ALL;
        }

        return self::LOCK_TYPE_ALL;
    }

    /**
     * Проверить, защищено ли свойство
     */
    private static function isPropertyProtected($iblockId, $propCode)
    {
        $props = self::getProtectedProperties();
        $key = $iblockId . '_' . $propCode;
        return isset($props[$key]);
    }

    /**
     * Проверить, включен ли модуль
     */
    public static function isEnabled()
    {
        $moduleId = 'awz.lockfieldsup1c';
        $enabled = Option::get($moduleId, 'enabled', 'Y', '');
        return $enabled === 'Y';
    }

    /**
     * Проверить, соответствует ли запрос заданным параметрам
     * @param string $requestParams Строка параметров для проверки
     * @return bool
     */
    public static function isRequestMatches($requestParams = '')
    {
        if (empty($requestParams)) return true;
        $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $curPage = $request->getRequestUri();
        if(substr($curPage,0,1)==='/'){
            $requestParamsAr = explode('?', $requestParams);
            $requestParams = $requestParamsAr[1] ?? '';
            $check = strpos($curPage, $requestParamsAr[0])===false;
            if($check) return false;
        }

        if(empty($requestParams)) return true;

        // Парсим строку параметров
        parse_str($requestParams, $expectedParams);

        // Проверяем, что все ожидаемые параметры присутствуют в запросе
        foreach ($expectedParams as $key => $value) {
            $requestValue = $request->get($key);
            if ($requestValue != $value) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Проверить, входит ли текущий пользователь в список разрешенных
     * @param string $userIds Список ID пользователей через запятую (с ! в начале для инверсии)
     * @return bool
     */
    public static function isUserAllowed($userIds = '')
    {
        global $USER;
        
        // Если список пользователей пуст, правило действует для всех
        if (empty($userIds)) {
            return true;
        }
        
        // Получаем ID текущего пользователя
        $currentUserId = \Bitrix\Main\Engine\CurrentUser::get()?->getId() ? : 0;
        
        if ($currentUserId === 0) {
            return false;
        }
        
        // Проверяем инверсию (символ ! в начале)
        $isInverted = (strpos($userIds, '!') === 0);
        if ($isInverted) {
            $userIds = substr($userIds, 1);
        }
        
        // Парсим список ID
        $ids = array_map('intval', array_filter(explode(',', $userIds)));
        
        // Если инверсия - правило активно для всех, кроме указанных
        // Без инверсии - правило активно только для указанных
        $isInList = in_array($currentUserId, $ids);
        
        return $isInverted ? !$isInList : $isInList;
    }

    /**
     * Проверить, должно ли правило сработать для текущего запроса и пользователя
     * @param array $ruleConfig Конфигурация правила (request_params, user_ids)
     * @return bool
     */
    public static function shouldApplyRule($ruleConfig = [])
    {
        if (!self::isEnabled()) {
            return false;
        }
        
        $requestParams = $ruleConfig['request_params'] ?? '';
        $userIds = $ruleConfig['user_ids'] ?? '';

        $requestParamsAr = explode(',',$requestParams);
        $check = false;
        foreach($requestParamsAr as $row){
            $row = trim($row);
            if(!$row) continue;
            $check = self::isRequestMatches($row);
            if($check) break;
        }
        
        return $check && self::isUserAllowed($userIds);
    }

    /**
     * Шаг 1: Перед обновлением элемента запоминаем старые значения защищенных свойств
     */
    public static function savePropsBeforeUpdate(&$arFields)
    {
        // Проверяем, включен ли модуль
        if (!self::isEnabled()) {
            return true;
        }

        if (empty($arFields['ID']) || !Loader::includeModule('iblock')) {
            return true;
        }
        if(self::$lockElements[$arFields['ID']] ?? false){
            return true;
        }

        // Сбрасываем буфер для нового элемента
        self::$savedValues = [];

        // Получаем ID инфоблока
        $iblockId = isset($arFields['IBLOCK_ID']) ? (int)$arFields['IBLOCK_ID'] : self::getIblockIdByElement($arFields['ID']);
        if (!$iblockId) {
            return true;
        }

        // Получаем список защищенных свойств для этого инфоблока
        $protectedProps = self::getProtectedPropertiesForIblock($iblockId, [self::SYSTEM_FIELD_ELEMENT_TYPE]);

        if (empty($protectedProps)) {
            return true;
        }

        // Проверяем, какие защищенные свойства пришли на обновление
        $currentEl = [];
        foreach ($protectedProps as $propCode => $propConfig) {
            // Проверяем условия срабатывания правила для текущего свойства
            if (!self::shouldApplyRule($propConfig)) {
                continue;
            }
            if(empty($currentEl) && self::isSystemField($propCode)){
                $currentEl = \Bitrix\Iblock\ElementTable::getList(["select"=>["*"], 'filter'=>['IBLOCK_ID'=>$iblockId, 'ID'=>$arFields['ID']]])->fetch() ?? [];
                break;
            }
        }
        foreach ($protectedProps as $propCode => $propConfig) {
            // Проверяем условия срабатывания правила для текущего свойства
            if (!self::shouldApplyRule($propConfig)) {
                continue;
            }

            $lockType = $propConfig['lock_type'] ?? self::LOCK_TYPE_ALL;
            
            if(self::isSystemField($propCode)){
                $currentValue = $currentEl[substr($propCode, strlen(self::SYSTEM_FIELD_ELEMENT_TYPE)+1)] ?? '';
            }else{
                $propId = self::getPropertyIdByCode($iblockId, $propCode);
                if (!$propId) {
                    continue;
                }

                // Исключаем обработку свойств типа файл
                $prop = self::getPropertyByCode($iblockId, $propCode);
                if ($prop['PROPERTY_TYPE'] === 'F') {
                    continue;
                }
                $currentValue = self::getCurrentPropertyValue($iblockId, $arFields['ID'], $propCode);
            }

            // Проверяем, пришло ли свойство пустым
            $isEmpty = false;
            $isPropPassed = false;

            // Определяем, нужно ли сохранять значение
            $needSave = false;

            if ($lockType === self::LOCK_TYPE_ALL) {
                // При типе "all" всегда сохраняем текущее значение
                $needSave = true;
            } elseif ($lockType === self::LOCK_TYPE_EMPTY) {
                // При типе "empty" сохраняем только если значение пустое
                if ($isEmpty) {
                    $needSave = true;
                }
            }

            if ($needSave) {

                if ($currentValue !== null) {
                    self::$savedValues[$propCode] = $currentValue;
                }
            }
        }

        return true;
    }

    /**
     * Шаг 2: После обновления элемента принудительно возвращаем сохраненные значения
     */
    public static function restorePropsAfterUpdate(&$arFields)
    {
        // Проверяем, включен ли модуль
        if (!self::isEnabled()) {
            return true;
        }

        // Если ничего не сохраняли
        if (empty(self::$savedValues)) {
            return true;
        }
        if(self::$lockElements[$arFields['ID']] ?? false){
            return true;
        }
        
        // Проверяем, включен ли модуль
        if (!self::isEnabled()) {
            return true;
        }

        $iblockId = isset($arFields['IBLOCK_ID']) ? (int)$arFields['IBLOCK_ID'] : self::getIblockIdByElement($arFields['ID']);
        if ($iblockId) {
            // Исключаем восстановление значений для свойств типа файл
            $propsToRestore = [];
            $propsToRestoreSys = [];
            foreach (self::$savedValues as $propCode => $value) {
                if(self::isSystemField($propCode)){
                    $propsToRestoreSys[substr($propCode, strlen(self::SYSTEM_FIELD_ELEMENT_TYPE)+1)] = $value;
                }
                $prop = self::getPropertyByCode($iblockId, $propCode);
                if ($prop['PROPERTY_TYPE'] !== 'F') {
                    $propsToRestore[$propCode] = $value;
                }
            }

            // Очищаем статическую память
            self::$savedValues = [];
            if(!empty($propsToRestore)){
                // Восстанавливаем старые значения свойств
                \CIBlockElement::SetPropertyValuesEx(
                    $arFields['ID'],
                    $iblockId,
                    $propsToRestore
                );
            }
            if(!empty($propsToRestoreSys)){
                self::$lockElements[$arFields['ID']] = true;
                $el = new \CIblockElement();
                $el->Update($arFields['ID'], $propsToRestoreSys);
            }
        }

        // Очищаем статическую память
        self::$savedValues = [];

        return true;
    }

    /**
     * Получить список защищенных свойств для конкретного инфоблока
     */
    private static function getProtectedPropertiesForIblock($iblockId, $types = [self::SYSTEM_FIELD_ELEMENT_TYPE, self::SYSTEM_FIELD_SECTION_TYPE])
    {
        $props = self::getProtectedProperties();
        $result = [];
        $prefix = $iblockId . '_';

        foreach ($props as $key => $config) {
            if (strpos($key, $prefix) === 0) {
                $propCode = substr($key, strlen($prefix));
                $sysType = self::getSystemFieldType($propCode);
                if(($sysType == self::SYSTEM_FIELD_SECTION_TYPE) && in_array($sysType, $types)){
                    $result[$propCode] = [
                        'lock_type' => $config['lock_type'] ?? self::LOCK_TYPE_ALL,
                        'request_params' => $config['request_params'] ?? '',
                        'user_ids' => $config['user_ids'] ?? ''
                    ];
                }elseif(($sysType == self::SYSTEM_FIELD_ELEMENT_TYPE) && in_array($sysType, $types)){
                    $result[$propCode] = [
                        'lock_type' => $config['lock_type'] ?? self::LOCK_TYPE_ALL,
                        'request_params' => $config['request_params'] ?? '',
                        'user_ids' => $config['user_ids'] ?? ''
                    ];
                }elseif(!$sysType && in_array(self::SYSTEM_FIELD_ELEMENT_TYPE, $types)){
                    $result[$propCode] = [
                        'lock_type' => $config['lock_type'] ?? self::LOCK_TYPE_ALL,
                        'request_params' => $config['request_params'] ?? '',
                        'user_ids' => $config['user_ids'] ?? ''
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Проверка значения свойства на пустоту
     */
    private static function isPropertyValueEmpty($value)
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                if (is_array($v) && isset($v['VALUE']) && trim((string)$v['VALUE']) !== '') {
                    return false;
                } elseif (!is_array($v) && trim((string)$v) !== '') {
                    return false;
                }
            }
            return true;
        }
        return trim((string)$value) === '';
    }

    /**
     * Получение текущего значения свойства из базы данных
     */
    private static function getCurrentPropertyValue($iblockId, $elementId, $propCode)
    {
        $res = \CIBlockElement::GetProperty($iblockId, $elementId, [], ['CODE' => $propCode]);
        $values = [];
        while ($prop = $res->Fetch()) {
            if ($prop['VALUE'] !== null && $prop['VALUE'] !== '') {
                $values[] = $prop['VALUE'];
            }
        }

        if (empty($values)) {
            return null;
        }

        $prop = self::getPropertyByCode($iblockId, $propCode);

        // Если свойство множественное — возвращаем массив, если одиночное — строку
        return ($prop['MULTIPLE'] === 'N') ? $values[0] : $values;
    }

    /**
     * Получение ID свойства по символьному коду
     */
    private static function getPropertyIdByCode($iblockId, $code)
    {
        $prop = self::getPropertyByCode($iblockId, $code);
        return (int)($prop['ID'] ?? 0);
    }

    /**
     * Получение свойства по коду с кешированием
     */
    private static function getPropertyByCode($iblockId, $code)
    {
        static $cache_prop = [];
        $cacheKey = $iblockId . '_' . $code;

        if (!isset($cache_prop[$cacheKey])) {
            $res = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code]);
            if ($prop = $res->Fetch()) {
                $cache_prop[$cacheKey] = $prop;
            } else {
                $cache_prop[$cacheKey] = [];
            }
        }
        return $cache_prop[$cacheKey];
    }

    /**
     * Определение ID инфоблока по ID элемента
     */
    private static function getIblockIdByElement($elementId)
    {
        $res = \CIBlockElement::GetByID($elementId);
        if ($el = $res->Fetch()) {
            return (int)$el['IBLOCK_ID'];
        }
        return 0;
    }

    /**
     * Получить список инфоблоков
     */
    public static function getIblocks()
    {
        $iblocks = [];
        if (!Loader::includeModule('iblock')) {
            return $iblocks;
        }

        $res = \CIBlock::GetList(['SORT' => 'ASC', 'NAME' => 'ASC'], ['ACTIVE' => 'Y']);
        while ($ib = $res->Fetch()) {
            $iblocks[] = $ib;
        }

        return $iblocks;
    }

    /**
     * Получить свойства инфоблока
     */
    public static function getIblockProperties(int $iblockId)
    {
        $properties = [];
        if (!Loader::includeModule('iblock')) {
            return $properties;
        }

        $sysProps = IblockLockHandler::getSystemFields($iblockId);
        foreach($sysProps as $code=>$prop){
            $properties[] = [
                'CODE'=>$code,
                'NAME'=>$prop,
                'MULTIPLE'=>'N',
                'TYPE'=>'SYSTEM_'.IblockLockHandler::getSystemFieldType($code)
            ];
        }

        $res = \CIBlockProperty::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y']
        );

        while ($prop = $res->Fetch()) {
            if (!empty($prop['CODE'])) {
                $properties[] = $prop;
            }
        }

        return $properties;
    }

    /**
     * Сохранить настройки защищенных свойств
     */
    public static function saveLockedProperties($properties)
    {
        $moduleId = 'awz.lockfieldsup1c';
        Option::set($moduleId, 'LOCKED_PROPERTIES', json_encode($properties), '');
        self::$protectedProperties = null; // Сбрасываем кеш
    }

    /**
     * Получить все настроенные защищенные свойства
     */
    public static function getLockedProperties()
    {
        $moduleId = 'awz.lockfieldsup1c';
        $propsJson = Option::get($moduleId, 'LOCKED_PROPERTIES', '[]', '');
        return json_decode($propsJson, true) ?: [];
    }

    /**
     * Шаг 1: Перед обновлением раздела запоминаем старые значения защищенных свойств
     */
    public static function saveSectionPropsBeforeUpdate(&$arFields)
    {
        // Проверяем, включен ли модуль
        if (!self::isEnabled()) {
            return true;
        }

        if (empty($arFields['ID']) || !Loader::includeModule('iblock')) {
            return true;
        }
        if(self::$lockSections[$arFields['ID']] ?? false){
            return true;
        }

        // Сбрасываем буфер для нового раздела
        self::$savedSectionValues = [];

        // Получаем ID инфоблока
        $iblockId = isset($arFields['IBLOCK_ID']) ? (int)$arFields['IBLOCK_ID'] : self::getIblockIdBySection($arFields['ID']);
        if (!$iblockId) {
            return true;
        }

        // Получаем список защищенных свойств для этого инфоблока
        $protectedProps = self::getProtectedPropertiesForIblock($iblockId, [self::SYSTEM_FIELD_SECTION_TYPE]);

        if (empty($protectedProps)) {
            return true;
        }

        // Проверяем, какие защищенные свойства пришли на обновление
        $currentEl = \Bitrix\Iblock\SectionTable::getList(["select"=>["*"], 'filter'=>['IBLOCK_ID'=>$iblockId, 'ID'=>$arFields['ID']]])->fetch() ?? [];
        foreach ($protectedProps as $propCode => $propConfig) {
            // Проверяем условия срабатывания правила для текущего свойства
            if (!self::shouldApplyRule($propConfig)) {
                continue;
            }
            
            $lockType = $propConfig['lock_type'] ?? self::LOCK_TYPE_ALL;
            $currentValue = $currentEl[substr($propCode, strlen(self::SYSTEM_FIELD_SECTION_TYPE)+1)] ?? '';

            // Проверяем, пришло ли свойство пустым
            $isEmpty = false;
            $isPropPassed = false;

            // Определяем, нужно ли сохранять значение
            $needSave = false;

            if ($lockType === self::LOCK_TYPE_ALL) {
                // При типе "all" всегда сохраняем текущее значение
                $needSave = true;
            } elseif ($lockType === self::LOCK_TYPE_EMPTY) {
                // При типе "empty" сохраняем только если значение пустое
                if ($isEmpty) {
                    $needSave = true;
                }
            }

            if ($needSave) {
                if ($currentValue !== null) {
                    self::$savedSectionValues[$propCode] = $currentValue;
                }
            }
        }

        return true;
    }

    /**
     * Шаг 2: После обновления раздела принудительно возвращаем сохраненные значения
     */
    public static function restoreSectionPropsAfterUpdate(&$arFields)
    {
        // Проверяем, включен ли модуль
        if (!self::isEnabled()) {
            return true;
        }

        // Если ничего не сохраняли
        if (empty(self::$savedSectionValues)) {
            return true;
        }
        if(self::$lockSections[$arFields['ID']] ?? false){
            return true;
        }

        $iblockId = isset($arFields['IBLOCK_ID']) ? (int)$arFields['IBLOCK_ID'] : self::getIblockIdBySection($arFields['ID']);
        if ($iblockId) {
            // Исключаем восстановление значений для свойств типа файл
            $propsToRestoreSys = [];
            foreach (self::$savedSectionValues as $propCode => $value) {
                $propsToRestoreSys[substr($propCode, strlen(self::SYSTEM_FIELD_SECTION_TYPE)+1)] = $value;
            }

            // Очищаем статическую память
            self::$savedSectionValues = [];

            if(!empty($propsToRestoreSys)){
                self::$lockSections[$arFields['ID']] = true;
                $sect = new \CIblockSection();
                $sect->Update($arFields['ID'], $propsToRestoreSys);
            }
        }

        // Очищаем статическую память
        self::$savedSectionValues = [];

        return true;
    }

    /**
     * Определение ID инфоблока по ID раздела
     */
    private static function getIblockIdBySection($sectionId)
    {
        $res = \CIBlockSection::GetByID($sectionId);
        if ($section = $res->Fetch()) {
            return (int)$section['IBLOCK_ID'];
        }
        return 0;
    }

    /**
     * Получить системные поля для инфоблока
     * @param int $iblockId ID инфоблока
     * @return array Список системных полей
     */
    public static function getSystemFields(int $iblockId = 0)
    {
        return [
            self::SYSTEM_FIELD_SECTION_NAME => 'Раздел: Название',
            self::SYSTEM_FIELD_SECTION_CODE => 'Раздел: Символьный код',
            self::SYSTEM_FIELD_SECTION_IBLOCK_SECTION_ID => 'Раздел: Родительский раздел',
            self::SYSTEM_FIELD_ELEMENT_NAME => 'Элемент: Название',
            self::SYSTEM_FIELD_ELEMENT_CODE => 'Элемент: Символьный код'
        ];
    }

    /**
     * Проверить, является ли поле системным
     * @param string $fieldCode Код поля
     * @return bool
     */
    public static function isSystemField($fieldCode)
    {
        return in_array($fieldCode, [
            self::SYSTEM_FIELD_SECTION_NAME,
            self::SYSTEM_FIELD_SECTION_CODE,
            self::SYSTEM_FIELD_SECTION_IBLOCK_SECTION_ID,
            self::SYSTEM_FIELD_ELEMENT_NAME,
            self::SYSTEM_FIELD_ELEMENT_CODE
        ]);
    }

    /**
     * Получить тип системного поля
     * @param string $fieldCode Код поля
     * @return string 'section' или 'element'
     */
    public static function getSystemFieldType($fieldCode)
    {
        if (strpos($fieldCode, 'SECTION_') === 0) {
            return 'section';
        } elseif (strpos($fieldCode, 'ELEMENT_') === 0) {
            return 'element';
        }
        return '';
    }
}
