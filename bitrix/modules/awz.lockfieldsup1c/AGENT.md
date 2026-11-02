# AGENT.md — модуль awz.lockfieldsup1c

## Назначение
Модуль блокирует обновление полей и свойств инфоблоков со стороны 1С (обмен через `CIBlockElement::Update` / `1c_admin.php`). Позволяет указать, какие поля/свойства не перезаписывать при обмене: полностью (`all`) или только когда 1С присылает пустое значение (`empty`).

## Структура
- `lib/iblocklockhandler.php` — ядро. События iblock: сохранение старых значений перед обновлением (`OnBeforeIBlockElementUpdate`, `OnBeforeIBlockSectionUpdate`) и восстановление после (`OnAfterIBlockElementUpdate`, `OnAfterIBlockSectionUpdate`).
- `lib/handlers.php` — `OnAdminTabControlBegin`, добавляет вкладку на `1c_admin.php`.
- `lib/access/*` — права модуля (Bitrix Access): `settedit`, `settview`, `rightedit`, `rightview`.
- `options.php`, `admin/menu.php` — страница настроек и меню (выбор инфоблока, полей, типа блокировки, пользователей).
- `install/db/{mysql,pgsql}/access.sql` — права по умолчанию при установке.
- `lang/ru/**` — языковые файлы.

## Ключевые механизмы (`IblockLockHandler`)
- Правила хранятся в опциях модуля: ключ `{IBLOCK_ID}_{CODE}` (например `2_ELEMENT_DETAIL_TEXT`), значение: `lock_type` (`all`|`empty`), `request_params`, `user_ids`.
- Кэш правил: `getProtectedProperties()`, per-iblock выборка `getProtectedPropertiesForIblock($iblockId, $types)`.
- Системные поля задаются константами с префиксом кода поля:
  - разделы: `SECTION_NAME`, `SECTION_CODE`, `SECTION_ACTIVE`, `SECTION_IBLOCK_SECTION_ID` (тип `section`);
  - элементы: `ELEMENT_NAME`, `ELEMENT_CODE`, `ELEMENT_ACTIVE`, `ELEMENT_IBLOCK_SECTION_ID`, `ELEMENT_DETAIL_TEXT`, `ELEMENT_DETAIL_TEXT_TYPE`, `ELEMENT_PREVIEW_TEXT`, `ELEMENT_PREVIEW_TEXT_TYPE` (тип `element`).
- `getSystemFieldType($code)` определяет тип по префиксу `SECTION_`/`ELEMENT_`.
- `isSystemField($code)` — список допустимых системных полей; при добавлении нового поля обновлять: константу, `getSystemFields()` (рус. название), `isSystemField()`.
- Старые значения элемента читаются из `\Bitrix\Iblock\ElementTable`; имя системного поля в правиле (`ELEMENT_XXX`) маппится на реальное поле (`XXX`) через `substr($propCode, strlen(self::SYSTEM_FIELD_ELEMENT_TYPE)+1)` — префикс `ELEMENT_` = 8 символов.
- Восстановление значений: свойства через `CIBlockElement::SetPropertyValuesEx`, системные поля через повторный `CIblockElement::Update` (с защитой от рекурсии через `self::$lockElements`).
- Свойства типа «файл» (`PROPERTY_TYPE === 'F'`) не блокируются/не восстанавливаются.
- Привязка к разделам (множественная) восстанавливается через `IBLOCK_SECTION_ID` + `IBLOCK_SECTION`.

## Конвенции
- PHP, namespace `Awz\Lockfieldsup1c`; классы без autoload-директорий (autoload через `include.php` и namespace).
- Названия полей для UI — на русском, в `getSystemFields()`.
- Проверка прав: `settedit` для изменения настроек, `settview` для просмотра.
- Включение/выключение работы обработчиков — через опцию модуля (`isEnabled()`).

## Типовая задача: добавление нового системного поля
1. Добавить константу `SYSTEM_FIELD_ELEMENT_XXX = 'ELEMENT_XXX'`.
2. Добавить запись в `getSystemFields()` с русским названием.
3. Добавить константу в `isSystemField()`.
4. Убедиться, что поле читается из `ElementTable`/`SectionTable` (`select ["*"]` уже используется) и передаётся в `CIblockElement::Update` без префикса.
