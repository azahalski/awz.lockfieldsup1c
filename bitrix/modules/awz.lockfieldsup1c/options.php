<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\UI\Extension;
use Awz\Lockfieldsup1c\Access\AccessController;
use Awz\Lockfieldsup1c\IblockLockHandler;

Loc::loadMessages(__FILE__);
global $APPLICATION;
$module_id = "awz.lockfieldsup1c";
if(!Loader::includeModule($module_id)) return;
Extension::load('ui.sidepanel-content');
$request = Application::getInstance()->getContext()->getRequest();
$APPLICATION->SetTitle(Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_TITLE'));

if($request->get('IFRAME_TYPE')==='SIDE_SLIDER'){
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
    require_once('lib/access/include/moduleright.php');
    CMain::finalActions();
    die();
}

if(!AccessController::isViewSettings())
    $APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

// Обработка AJAX-запросов
if ($request->get('ajax_action')) {
    \Awz\Lockfieldsup1c\Api\Controller::processAjax();
    return;
}

if ($request->getRequestMethod()==='POST' && AccessController::isEditSettings() && $request->get('Update'))
{
    // Сохранение настроек защиты полей
    $lockedProperties = [];
    $iblockIds = $request->get('lock_iblock_id');
    $propCodes = $request->get('lock_property_code');
    $lockTypes = $request->get('lock_type');
    $requestParamsArr = $request->get('lock_request_params');
    $userIdsArr = $request->get('lock_user_ids');
    $enabled = $request->get('module_enabled') === 'Y';

    if (is_array($iblockIds) && is_array($propCodes) && is_array($lockTypes)) {
        foreach ($iblockIds as $index => $iblockId) {
            $iblockId = (int)$iblockId;
            $propCode = $propCodes[$index] ?? '';
            $lockType = $lockTypes[$index] ?? 'all';
            $requestParams = $requestParamsArr[$index] ?? 'type=catalog&mode=import';
            $userIds = $userIdsArr[$index] ?? '';

            if ($iblockId > 0 && !empty($propCode)) {
                $key = $iblockId . '_' . $propCode;
                $lockedProperties[$key] = [
                    'iblock_id' => $iblockId,
                    'property_code' => $propCode,
                    'lock_type' => $lockType,
                    'request_params' => $requestParams,
                    'user_ids' => $userIds
                ];
            }
        }
    }

    IblockLockHandler::saveLockedProperties($lockedProperties);
    
    // Сохранение опции включения модуля
    Option::set($module_id, 'enabled', $enabled ? 'Y' : 'N', '');
}

// Получаем текущие настройки
$currentLockedProps = IblockLockHandler::getLockedProperties();

$aTabs = array();

$aTabs[] = array(
    "DIV" => "edit2",
    "TAB" => Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_SECT3'),
    "ICON" => "vote_settings",
    "TITLE" => Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_SECT3')
);

$saveUrl = $APPLICATION->GetCurPage(false).'?mid='.htmlspecialcharsbx($module_id).'&lang='.LANGUAGE_ID.'&mid_menu=1';
$tabControl = new CAdminTabControl("tabControl", $aTabs);
$tabControl->Begin();
?>
    <style>.adm-workarea option:checked {background-color: rgb(206, 206, 206);}</style>
    <form method="POST" action="<?=$saveUrl?>" id="FORMACTION">
        <?php
        $tabControl->BeginNextTab();
        Extension::load("ui.alerts");
        ?>

        <tr>
            <td colspan="2" style="padding-bottom:1rem;">
                <label>
                    <input type="checkbox" name="module_enabled" value="Y" <?=Option::get($module_id, 'enabled', 'Y') === 'Y' ? 'checked' : ''?>>
                    <?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_MODULE_ENABLED')?>
                </label>
            </td>
        </tr>

        <tr>
            <td colspan="2">
                <div style="padding:0.5rem 0;display:block;clear:both;">
                <div class="ui-alert ui-alert-info">
                    <span class="ui-alert-message">
                        <?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_FIELDS_DESC')?>
                    </span>
                </div>
                </div>
                <div style="padding:1rem 0;display:block;clear:both;">
                    <b><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_REQUEST_PARAMS')?></b>
                    <br>
                    <?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_REQUEST_PARAMS_DESC')?>
                </div>
                <div style="padding:1rem 0;display:block;clear:both;">
                    <b><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_USER_IDS')?></b>
                    <br>
                    <?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_USER_IDS_DESC')?>
                </div>
            </td>
        </tr>



        <tr>
            <td colspan="2">
                <table class="adm-detail-content-table" id="lock-fields-table">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding-bottom:0.5rem;"><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_IBLOCK')?></th>
                            <th style="text-align:left;padding-bottom:0.5rem;"><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_PROPERTY')?></th>
                            <th style="text-align:left;padding-bottom:0.5rem;"><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_TYPE')?></th>
                            <th style="text-align:left;padding-bottom:0.5rem;"><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_REQUEST_PARAMS')?></th>
                            <th style="text-align:left;padding-bottom:0.5rem;"><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_USER_IDS')?></th>
                            <th style="text-align:left;padding-bottom:0.5rem;"></th>
                        </tr>
                    </thead>
                    <tbody id="lock-fields-rows">
                        <?php if(!empty($currentLockedProps)):?>
                            <?php foreach($currentLockedProps as $key => $config):?>
                                <tr class="lock-field-row">
                                    <td>
                                        <select name="lock_iblock_id[]" class="lock-iblock-select" onchange="loadProperties(this)" style="max-width:160px;">
                                            <option value=""><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_SELECT_IBLOCK')?></option>
                                            <?php foreach(IblockLockHandler::getIblocks() as $ib):?>
                                                <option value="<?=$ib['ID']?>" <?=$ib['ID'] == $config['iblock_id'] ? 'selected' : ''?>>
                                                    <?=$ib['NAME']?> [<?=$ib['ID']?>]
                                                </option>
                                            <?php endforeach;?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="lock_property_code[]" class="lock-property-select" style="max-width:200px;">
                                            <option value=""><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_SELECT_PROPERTY')?></option>
                                            <?php
                                            $properties = IblockLockHandler::getIblockProperties($config['iblock_id']);
                                            ?>
                                            <?php foreach($properties as $prop):?>
                                                <option value="<?=$prop['CODE']?>" <?=$prop['CODE'] == $config['property_code'] ? 'selected' : ''?>>
                                                    <?=$prop['NAME']?> [<?=$prop['CODE']?>]
                                                </option>
                                            <?php endforeach;?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="lock_type[]">
                                            <option value="all" <?=$config['lock_type'] == 'all' ? 'selected' : ''?>>
                                                <?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_TYPE_ALL')?>
                                            </option>
                                            <?php if(false){?>
                                            <option value="empty" <?=$config['lock_type'] == 'empty' ? 'selected' : ''?>>
                                                <?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_TYPE_EMPTY')?>
                                            </option>
                                            <?php }?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="lock_request_params[]" value="<?=htmlspecialcharsbx($config['request_params'] ?? 'type=catalog&mode=import')?>" style="" placeholder="type=catalog&mode=import">
                                    </td>
                                    <td>
                                        <input type="text" name="lock_user_ids[]" value="<?=htmlspecialcharsbx($config['user_ids'] ?? '')?>" style="" placeholder="">
                                    </td>
                                    <td>
                                        <button type="button" class="adm-btn" onclick="removeLockRow(this)" style="margin-left:1rem;">
                                            <?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_DELETE')?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach;?>
                        <?php endif;?>
                    </tbody>
                </table>
                <br>
                <a class="" href="#" onclick="addLockRow();return false;">
                    <?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_ADD')?>
                </a>
            </td>
        </tr>

        <?php $tabControl->Buttons();?>
        <input <?php if (!AccessController::isEditSettings()) echo "disabled" ?> type="submit" class="adm-btn-green" name="Update" value="<?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_L_BTN_SAVE')?>" />
        <input type="hidden" name="Update" value="Y" />
        <?php if(AccessController::isViewRight()){?>
            <button class="adm-header-btn adm-security-btn" onclick="BX.SidePanel.Instance.open('<?=$saveUrl?>');return false;">
                <?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_SECT2')?>
            </button>
        <?php }?>
        <?php $tabControl->End();?>
    </form>

    <script>
    function addLockRow() {
        var tbody = document.getElementById('lock-fields-rows');
        var newRow = document.createElement('tr');
        newRow.className = 'lock-field-row';
        newRow.innerHTML = `
            <td>
                <select name="lock_iblock_id[]" class="lock-iblock-select" onchange="loadProperties(this)">
                    <option value=""><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_SELECT_IBLOCK')?></option>
                    <?php foreach(IblockLockHandler::getIblocks() as $ib):?>
                        <option value="<?=$ib['ID']?>">
                            <?=$ib['NAME']?> [<?=$ib['ID']?>]
                        </option>
                    <?php endforeach;?>
                </select>
            </td>
            <td>
                <select name="lock_property_code[]" class="lock-property-select">
                    <option value=""><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_SELECT_PROPERTY')?></option>
                </select>
            </td>
            <td>
                <select name="lock_type[]">
                    <option value="all"><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_TYPE_ALL')?></option>
                    <?php if(false){?><option value="empty"><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_TYPE_EMPTY')?></option><?php }?>
                </select>
            </td>
            <td>
                <input type="text" name="lock_request_params[]" value="type=catalog&mode=import" style="width:100%;" placeholder="type=catalog&mode=import">
            </td>
            <td>
                <input type="text" name="lock_user_ids[]" value="" style="width:100%;" placeholder="">
            </td>
            <td>
                <button type="button" class="adm-btn" onclick="removeLockRow(this)" style="margin-left:1rem;">
                    <?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_DELETE')?>
                </button>
            </td>
        `;
        tbody.appendChild(newRow);
    }

    function removeLockRow(btn) {
        var row = btn.closest('tr');
        if (row) {
            row.parentNode.removeChild(row);
        }
    }

    function loadProperties(select) {
        var iblockId = select.value;
        var row = select.closest('tr');
        var propSelect = row.querySelector('.lock-property-select');

        // Очищаем текущие опции
        propSelect.innerHTML = '<option value=""><?=Loc::getMessage('AWZ_LOCKFIELDSUP1C_OPT_LOCK_SELECT_PROPERTY')?></option>';

        if (!iblockId) {
            return;
        }

        // AJAX запрос для получения свойств инфоблока через BX.ajax.runAction
        BX.ajax.runAction('awz:lockfieldsup1c.api.controller.getProperties', {
            data: {
                iblockId: iblockId
            }
        }).then(function(response) {
            if (response.data && response.data) {
                response.data.forEach(function(prop) {
                    var option = document.createElement('option');
                    option.value = prop.CODE;
                    option.textContent = prop.NAME + ' [' + prop.CODE + ']';
                    propSelect.appendChild(option);
                });
            }
        }).catch(function(error) {
            // Отображаем ошибку в интерфейсе
            try{
                var errorMessage = error.errors[0].message;
            }catch (e) {
                var errorMessage = 'Ошибка при загрузке свойств';
            }

            var alertDiv = document.createElement('div');
            alertDiv.className = 'ui-alert ui-alert-danger';
            alertDiv.style.marginBottom = '10px';
            alertDiv.innerHTML = '<span class="ui-alert-message">' + errorMessage + '</span>';
            
            // Добавляем alert перед таблицей
            var table = document.getElementById('lock-fields-table');
            if (table) {
                table.parentNode.insertBefore(alertDiv, table);
            } else {
                console.error('Error loading properties:', error);
            }
        });
    }
    </script>
<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
