<?php
defined('B_PROLOG_INCLUDED') || die;


use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Main\UserTable;
use Bitrix\Main\Grid;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Web\Uri;
use Bitrix\Main\DB;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\Service\Container;
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;

class CPonyExMonitoringListComponent extends CBitrixComponent implements Bitrix\Main\Engine\Contract\Controllerable
{

    const LIMIT_WO_FILTER = 500;
    const SUPPORTED_SERVICE_ACTIONS = array('GET_ROW_COUNT');
    const arrStatusText = [
        0 => '',
        1 => 'В работе',
        2 => 'Выполнено',
        3 => 'Просрочено',
    ];
    const TYPE_ID = [
        0 => '',
        1 => 'Встреча',
        2 => 'Звонок',
        3 => 'Задача',
        4 => 'Письмо',
        5 => 'Действие',
        6 => 'Пользовательское действие',
    ];
    /**
     * @var PonyEx\Monitoring\Model\Service\BaseService\CustomGridBase $service
     */
    private $service;

    public $arResult = [];
    public $exportFlag = false;

    public function __construct(CBitrixComponent $component = null)
    {
        parent::__construct($component);

        if (!Loader::includeModule('ponyex.monitoring')) {
            ShowError('Can`t load module ponyex.monitoring');
            return;
        }

        // Тут загрузка принудительная
        Loc::loadMessages(__FILE__);

//        $hlblock           = HLBT::getById($HlBlockId)->fetch();
//        $entity            = HLBT::compileEntity($hlblock);
//        $entity_data_class = $entity->getDataClass();


    }

    private function getTitle()
    {
        return 'Отчёт по активностям';
    }

    private function getGridId()
    {
        return 'ADVINA_GRID_ACTIVITY_REPORT';
    }

//	Поля по которым можно сортировать
    private function getSortableFields()
    {
        return ['RESPONSIBLE_ID', 'START_TIME', 'CONTACT', 'COMPANY', 'ASSOCIATED_ENTITY_ID', 'TYPE_ID', 'STATUS', 'CREATED', 'ROP'];
    }

//	private function getFilterableFields() {
//		return ['STATUS', 'TYPE_ID' ,'PRIORITY', 'RESPONSIBLE_ID', 'DEADLINE', 'CREATED', 'ID', 'END_DATE', 'DEAL', 'COMPANY', 'START_TIME'];
//	}

    public function getFilterFields()
    {
        return [
            [
                'id' => 'RESPONSIBLE_ID',
                'name' => 'Ответственный',
                'default' => true,
                'type' => 'dest_selector',
                'params' => array(
                    'context' => 'CRM_ACTIVITY_FILTER_RESPONSIBLE_ID',
                    'multiple' => 'Y',
                    'contextCode' => 'U',
                    'enableAll' => 'N',
                    'enableSonetgroups' => 'N',
                    'allowEmailInvitation' => 'N',
                    'allowSearchEmailUsers' => 'N',
                    'departmentSelectDisable' => 'Y',
                    'isNumeric' => 'Y',
                    'prefix' => 'U',
                )
            ],
            [
                'id' => 'ROP',
                'name' => 'РОП',
//                'default' => true,
                'type' => 'dest_selector',
                'params' => array(
                    'context' => 'CRM_ACTIVITY_FILTER_RESPONSIBLE_ID',
                    'multiple' => 'Y',
                    'contextCode' => 'U',
                    'enableAll' => 'N',
                    'enableSonetgroups' => 'N',
                    'allowEmailInvitation' => 'N',
                    'allowSearchEmailUsers' => 'N',
                    'departmentSelectDisable' => 'Y',
                    'isNumeric' => 'Y',
                    'prefix' => 'U',
                )
            ],
            [
                'id' => 'STATUS',
                'name' => 'Статус',
                'type' => 'list',
                'items' => [
                    1 => 'В работе',
                    2 => 'Выполнено',
                    3 => 'Просрочено',
                ],
                'params' => [
                    'multiple' => 'N',
                ],
            ],
            [
                'id' => 'TYPE_ID',
                'name' => 'Тип',
                'type' => 'list',
                'items' => [
                    0 => '',
                    1 => 'Встреча',
                    2 => 'Звонок',
                    3 => 'Задача',
                    4 => 'Письмо',
                    5 => 'Действие',
                    6 => 'Пользовательское действие',
                ],
                'params' => [
                    'multiple' => 'Y',
                ],
            ],
            [
                'id' => 'PRIORITY',
                'name' => 'Важность',
                'type' => 'list',
                'items' => [
                    2 => 'Средняя',
                    3 => 'Высокая',
                ],
                'params' => [
                    'multiple' => 'N',
                ],
            ],
            [
                'id' => 'DEADLINE',
                'name' => 'Срок',
                'type' => 'date'
            ],
            [
                'id' => 'CREATED',
                'name' => 'Дата создания',
                'type' => 'date'
            ],
            [
                'id' => 'ID',
                'name' => 'ID',
            ],
            [
                'id' => 'END_TIME',
                'name' => 'Конец',
                'type' => 'date'
            ],
            [
                'id' => 'ASSOCIATED_ENTITY_ID',
                'name' => 'Сделка/Лид',
                'type' => 'dest_selector',
                'params' => [
                    'apiVersion' => 3,
                    'context' => 'CRM_ACTIVITY_FILTER_REFERENCE',
                    'multiple' => 'Y',
                    'contextCode' => 'CRM',
                    'useClientDatabase' => 'N',
                    'enableAll' => 'N',
                    'enableDepartments' => 'N',
                    'enableUsers' => 'N',
                    'enableSonetgroups' => 'N',
                    'allowEmailInvitation' => 'N',
                    'allowSearchEmailUsers' => 'N',
                    'departmentSelectDisable' => 'Y',
                    'enableCrm' => 'Y',
                    'enableCrmLeads' => 'Y',
                    'enableCrmDeals' => 'Y',
                    'addTabCrmLeads' => 'Y',
                    'addTabCrmDeals' => 'Y',
                    'convertJson' => 'Y'
                ],
            ],
            [
                'id' => 'COMPANY',
                'name' => 'Компания',
                'type' => 'dest_selector',
                'params' => [
                    'apiVersion' => 3,
                    'context' => 'CRM_ACTIVITY_FILTER_REFERENCE',
                    'multiple' => 'Y',
                    'contextCode' => 'CRM',
                    'useClientDatabase' => 'N',
                    'enableAll' => 'N',
                    'enableDepartments' => 'N',
                    'enableUsers' => 'N',
                    'enableSonetgroups' => 'N',
                    'allowEmailInvitation' => 'N',
                    'allowSearchEmailUsers' => 'N',
                    'departmentSelectDisable' => 'Y',
                    'enableCrm' => 'Y',
                    'enableCrmContacts' => 'Y',
                    'enableCrmCompanies' => 'Y',
                    'addTabCrmCompanies' => 'Y',
                    'addTabCrmContacts' => 'Y',
                    'convertJson' => 'Y',
                ],
            ],
            [
                'id' => 'START_TIME',
                'name' => 'Дата начала',
                'type' => 'date'
            ],
        ];
    }

    private function getFilterPresets()
    {
        global $USER;
        return [
            'my_activities' => [
                'name' => 'Мои задачи',
                'fields' => [
                    'RESPONSIBLE_ID' => $USER->GetID(),
                    'RESPONSIBLE_ID_name' => $USER->GetFullName(),
                ],
            ],
            'call_meeting_test' => [
                'name' => 'Текущий месяц',
                'default' => true,
                'fields' => [
                    'RESPONSIBLE_ID' => $USER->GetID(),
                    'RESPONSIBLE_ID_name' => $USER->GetFullName(),
                    'START_TIME_datesel' => 'CURRENT_MONTH',
                    'START_TIME_from' => '',
                    'START_TIME_to' => '',
                    'START_TIME_days' => '',
                    'START_TIME_month' => '',
                    'START_TIME_quarter' => '',
                    'START_TIME_year' => '',
                ]
            ],
//            'call_meeting' => [
//                'name' => 'За последние 30 дней',
//                'sort' => '7',
//                'default' => true,
//                'fields' => [
//                    'STATUS' => '',
//                    'CREATED_datesel' => 'LAST_30_DAYS',
//                    'CREATED_from' => '',
//                    'CREATED_to' => '',
//                    'CREATED_days' => '',
//                    'CREATED_month' => '',
//                    'CREATED_quarter' => '',
//                    'CREATED_year' => '',
//                    'START_TIME_datesel' => 'NONE',
//                    'START_TIME_from' => '',
//                    'START_TIME_to' => '',
//                    'START_TIME_days' => '',
//                    'START_TIME_month' => '',
//                    'START_TIME_quarter' => '',
//                    'START_TIME_year' => '',
//                    'END_TIME_datesel' => 'NONE',
//                    'END_TIME_from' => '',
//                    'END_TIME_to' => '',
//                    'END_TIME_days' => '',
//                    'END_TIME_month' => '',
//                    'END_TIME_quarter' => '',
//                    'END_TIME_year' => '',
//                    'TYPE_ID' =>
//                        array (
//                            0 => '1',
//                            1 => '2',
//                        ),
//                    'PRIORITY' => '',
//                ],
//                'filter_rows' => 'RESPONSIBLE_ID,STATUS,CREATED,START_TIME,END_TIME,TYPE_ID,PRIORITY,COMPANY,ASSOCIATED_ENTITY_ID',
//                'for_all' => true,
//            ],
        ];
    }

    private function getHeaders()
    {
        return [
            [
                'id' => 'RESPONSIBLE_ID',
                'name' => 'Ответственный',
                'sort' => 'RESPONSIBLE_ID',
                'default' => true,
                'shift' => true,
            ],
            [
                'id' => 'START_TIME',
                'name' => 'Дата начала',
                'sort' => 'START_TIME',
                'default' => true,
            ],
            [
                'id' => 'THEME',
                'name' => 'Тема',
                'sort' => false,
                'default' => true,
            ],
            [
                'id' => 'DESCRIPTION',
                'name' => 'Описание',
                'sort' => false,
                'default' => true,
            ],
            [
                'id' => 'COMPANY',
                'name' => 'Компания',
                'sort' => 'COMPANY',
                'default' => true,
            ],
            [
                'id' => 'CONTACT',
                'name' => 'Контакт',
                'sort' => 'CONTACT',
                'default' => true,
            ],
            [
                'id' => 'ASSOCIATED_ENTITY_ID',
                'name' => 'Сделка/Лид',
                'sort' => 'ASSOCIATED_ENTITY_ID',
                'default' => true,
            ],
            [
                'id' => 'STATUS',
                'name' => 'Статус',
                'sort' => 'STATUS',
                'default' => true,
            ],
            [
                'id' => 'TYPE_ID',
                'name' => 'Тип активности',
                'sort' => 'TYPE_ID',
                'default' => true,
            ],
            [
                'id' => 'CREATED',
                'name' => 'Дата создания',
                'sort' => 'CREATED',
                'default' => true,
            ],
            [
                'id' => 'ROP',
                'name' => 'РОП',
                'sort' => 'ROP',
                'default' => true,
            ],
        ];
    }

    public function getInitialDbData ($sort = [], $filter = [], $arGroupBy = false, $arNavStartParams = false, $arSelect = [])
    {
        $items = [];
        $finalItems = [];
        $currentUserPermissions = CCrmPerms::GetCurrentUserPermissions();
        $dbResult = CCrmActivity::GetList($sort, $filter, $arGroupBy, $arNavStartParams, $arSelect);
        if (is_object($dbResult)) {
            while ($arActivity = $dbResult->Fetch()) {
                $items[$arActivity['ID']] = $arActivity;
            }
            //        endregion
//        region Собираем инфо о клиенте
            $clientInfos = CCrmActivity::PrepareClientInfos(array_keys($items));
//          endregion
            foreach ($items as &$item) {
//                    region Продолжение по клиенту
                if (!isset($clientInfos[$item['ID']])) {
                    $item['CLIENT_ENTITY_ID'] = '';
                    $item['CLIENT_ENTITY_TYPE_ID'] = '';
                    $item['CLIENT_TITLE'] = '';
                    $item['CLIENT_URL'] = '';
                    $item['CLIENT_PATH'] = '';
                } else {
                    $clientInfo = $clientInfos[$item['ID']];
                    $item['CLIENT_ENTITY_ID'] = $clientInfo['ENTITY_ID'];
                    $item['CLIENT_ENTITY_TYPE_ID'] = $clientInfo['ENTITY_TYPE_ID'];
                    $item['CLIENT_TITLE'] = $clientInfo['TITLE'];
                    $item['CLIENT_URL'] = $clientInfo['SHOW_URL'];
                    $item['CLIENT_PATH'] = '<a href=' . $item['CLIENT_URL'] . '>' . $item['CLIENT_TITLE'] . '</a>';
                }
//                    endregion
                $checkDealPerm = CCrmActivity::CheckReadPermission($item["OWNER_TYPE_ID"], $item["OWNER_ID"], $currentUserPermissions);
                $checkClientPerm = CCrmActivity::CheckReadPermission($item["CLIENT_ENTITY_TYPE_ID"], $item["CLIENT_ENTITY_ID"], $currentUserPermissions);
//                self::setLog([
//                    '$checkDealPerm' => $checkDealPerm,
//                    '$checkClientPerm' => $checkClientPerm,
//                ], 'activity_report_permition');
                if ($checkDealPerm and $checkClientPerm) {
                    $finalItems[$item['ID']] = $item;
                }
            }
        }
        return $finalItems;
    }

    public function getFromList () {
        \Bitrix\Main\Loader::includeModule('iblock');
        $arManager = [];
        $arOrder = ["SORT"=>"ASC"];
        $arFilter = [
            'IBLOCK_ID' => 66,
        ];
        $arSelectFields = ['PROPERTY_194', 'PROPERTY_216'];
        $listData = CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelectFields);
        self::setLog([
            '$listData' => $listData,
        ], 'getFromList');
        $test = [];
        while ($arActivity = $listData->Fetch()) {
            $test[] = $arActivity;

            if (!empty($arActivity['PROPERTY_194_VALUE']) and !empty($arActivity['PROPERTY_216_VALUE'])) {
                if (isset($arManager['MOP'][$arActivity['PROPERTY_194_VALUE']])) {
                    continue;
                } else {
                    $arManager['MOP'][$arActivity['PROPERTY_194_VALUE']] = $arActivity['PROPERTY_216_VALUE'];
                }
            }
            if (!empty($arActivity['PROPERTY_216_VALUE']) and isset($arManager['ROP'][$arActivity['PROPERTY_216_VALUE']]) and !empty($arActivity['PROPERTY_194_VALUE'])) {
                $arManager['ROP'][$arActivity['PROPERTY_216_VALUE']][] = $arActivity['PROPERTY_194_VALUE'];
            } elseif (!empty($arActivity['PROPERTY_216_VALUE']) and !empty($arActivity['PROPERTY_194_VALUE'])) {
                $arManager['ROP'][$arActivity['PROPERTY_216_VALUE']][] = $arActivity['PROPERTY_194_VALUE'];
            }
        }
        self::setLog([
            '$test' => $test,
        ], 'getFromList_test');

        self::setLog([
            '$arManager' => $arManager,
        ], 'getFromList_final');
        return $arManager;
    }

    public function ropFilterTuning($arManager, $filter)
    {
        $mops = [];
        self::setLog([
            '$filter' => $filter,
        ], 'prepareData_$filter_before_update');
        $initiallyHasResponsibleID = false;
        if (isset($filter['ROP'])) {
            foreach ($filter['ROP'] as $rop) {
                if (isset($arManager['ROP'][$rop])) {
                    $mops = array_merge($mops, $arManager['ROP'][$rop]);
                }
            }
            self::setLog([
                '$mops' => $mops,
            ], 'prepareData_$mops');
            if (isset($filter['RESPONSIBLE_ID'])) {
                $initiallyHasResponsibleID = true;
                $filter['RESPONSIBLE_ID'] = array_intersect($filter['RESPONSIBLE_ID'], $mops);
//                foreach ($filter['RESPONSIBLE_ID'] as $key => $responsible) {
//                    if (!in_array($responsible, $mops)) {
//                        unset($filter['RESPONSIBLE_ID'][$key]);
//                    }
//                }
            } elseif(empty($mops)) {
                $initiallyHasResponsibleID = true;
            } else {
                $filter['RESPONSIBLE_ID'] = $mops;
            }
            unset($filter['ROP'], $filter['ROP_label']);
        }
        self::setLog([
            'filter' => $filter,
            'initiallyHasResponsibleID' => $initiallyHasResponsibleID,
        ], 'prepareData_$filter_check');
        return [
            'filter' => $filter,
            'initiallyHasResponsibleID' => $initiallyHasResponsibleID,
        ];
    }

    public function prepareData ($arManager, $sort = [], $filter = [], $arGroupBy = false, $arNavStartParams = false, $arSelect = [], $expandedRows = []) {

        $ownerMap = [];
        $responsibleIDs = [];
        $forCount = [];
        $activityIds = [];
        $activityTask = [];
        $arParams['PATH_TO_USER_PROFILE'] = CrmCheckPath(
            'PATH_TO_USER_PROFILE',
            isset($arParams['PATH_TO_USER_PROFILE']) ? $arParams['PATH_TO_USER_PROFILE'] : '',
            '/company/personal/user/#user_id#/'
        );
        $arParams['PATH_TO_DEAL_DETAILS'] = CrmCheckPath(
            'PATH_TO_DEAL_DETAILS',
            $arParams['PATH_TO_DEAL_DETAILS'] ?? '',
            '/crm/deal/details/#deal_id#/'
        );
        if (array_key_exists('EXPORT_EXCEL', $_REQUEST)) {
            $this->exportFlag = true;
            self::setLog([
                '$exportFlag' => $this->exportFlag,
            ], 'activity_report_$export_1');
        }
        if ($this->exportFlag) {
            $arNavStartParams = false;
//            $arSelect = [];
            self::setLog([
                '$exportFlag' => $this->exportFlag,
            ], 'activity_report_$export_2');
        }

//        $arManager = self::getFromList();
        $items = $this->getInitialDbData($sort, $filter, $arGroupBy, $arNavStartParams, $arSelect);

        self::setLog([
            '$$items' => $items,
        ], 'activity_report_$export_3');

        foreach ($items as &$item) {
//            region Подставляем статус дела (свои статусы), вместо ID статуса
            if ($item['STATUS'] < 2) {
                $deadline = new \DateTime($item['DEADLINE']);
                $today = new \DateTime();
                $statusText = ($deadline < $today) ? self::arrStatusText[3] : self::arrStatusText[1];
            } elseif ($item['STATUS'] >= 2) {
                $statusText = self::arrStatusText[2];
            }
            $item['STATUS_TEXT'] = $statusText;
//            endregion
//            region Подставляем типы активностей
            $item['TYPE_ID_TEXT'] = self::TYPE_ID[$item['TYPE_ID']];
//            endregion
//                region Создаём ссылки на дело
            $item['ACTIVITY_URL'] = CCrmOwnerType::GetEntityShowPath(CCrmOwnerType::Activity, $item['ID']);
//                endregion
//                region Собираем инфо по сделке/лиду
            $ownerTypeID = isset($item['OWNER_TYPE_ID']) ? (int)$item['OWNER_TYPE_ID'] : 0;
            $ownerID = isset($item['OWNER_ID']) ? (int)$item['OWNER_ID'] : 0;

            if ($ownerID > 0 && (
                    $ownerTypeID === CCrmOwnerType::Deal
                    || $ownerTypeID === CCrmOwnerType::Lead
                )) {
                if (!isset($ownerMap[$ownerTypeID])) {
                    $ownerMap[$ownerTypeID] = array();
                }

                if (!isset($ownerMap[$ownerTypeID][$ownerID])) {
                    $ownerMap[$ownerTypeID][$ownerID] = array();
                }
            }
//                endregion
//            region Собираем инфо по ответственному
            $responsibleID = isset($item['RESPONSIBLE_ID']) ? intval($item['RESPONSIBLE_ID']) : 0;
            $item['~RESPONSIBLE_ID'] = $responsibleID;
            if ($responsibleID <= 0) {
                $item['RESPONSIBLE'] = false;
                $item['RESPONSIBLE_FULL_NAME'] = '';
                $item['PATH_TO_RESPONSIBLE'] = '';
            } elseif (!in_array($responsibleID, $responsibleIDs, true)) {
                $responsibleIDs[] = $responsibleID;
            }
            $forCount[] = $responsibleID;
            $countVal = array_count_values($forCount);
            $activityIds[] = $item['ID'];
//            endregion
        }
// region        МОПы и РОПы
        foreach ($arManager['MOP'] as $k => $v) {
            if (!in_array($k, $responsibleIDs, true)) {
                $responsibleIDs[] = $k;
                if (!in_array($v, $responsibleIDs, true)) {
                    $responsibleIDs[] = $v;
                }
            } else {
                if (!in_array($v, $responsibleIDs, true)) {
                    $responsibleIDs[] = $v;
                }
            }
        }
// endregion

//        region Продолжение по сделке/лиду
        $arResult['OWNER_INFOS'] = array();
        foreach ($ownerMap as $ownerTypeID => $ownerInfos) {
            CCrmOwnerType::PrepareEntityInfoBatch($ownerTypeID, $ownerInfos, false);
            $arResult['OWNER_INFOS'][$ownerTypeID] = $ownerInfos;
        }
//  endregion

        self::setLog([
            '$items' => $items,
            '$responsibleIDs' => $responsibleIDs,
            'info' => $arResult['OWNER_INFOS'],
            '$ownerMap' => $ownerMap,
            '$forCount' => $forCount,
            '$countVal' => $countVal,

        ], 'activity_report_Prepare_items');
//        region Продолжение по ответственному
        $responsibleInfos = [];
        if (!empty($responsibleIDs)) {
            $dbUsers = CUser::GetList(
                'ID',
                'ASC',
                ['ID' => implode('||', $responsibleIDs)],
                ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'LOGIN', 'TITLE']]
            );

            $userNameFormat = CSite::GetNameFormat(false);
            while ($arUser = $dbUsers->Fetch()) {
                $userID = intval($arUser['ID']);

                $responsibleInfo = ['USER' => $arUser];
                $responsibleInfo['FULL_NAME'] = CUser::FormatName($userNameFormat, $arUser, true, false);
                $responsibleInfo['HTML_FULL_NAME'] = htmlspecialcharsbx($responsibleInfo['FULL_NAME']);
                $responsibleInfo['PATH'] = CComponentEngine::MakePathFromTemplate($arParams['PATH_TO_USER_PROFILE'], ['user_id' => $userID]);
                $responsibleInfos[$userID] = &$responsibleInfo;
                unset($responsibleInfo);
            }
            self::setLog([
                '$responsibleInfos' => $responsibleInfos,
            ], 'activity_report_$responsibleInfos');
//        endregion
//            region Дополнительная строка для группировки
            self::setLog([
                'items' => $items,
            ], 'proverka dannih');
            if (!$this->exportFlag) {
                self::setLog([
                    '$exportFlag' => $this->exportFlag,
                ], 'activity_report_$export_1');
                $responsibleIdsInItems = array_column($items, 'RESPONSIBLE_ID');
                foreach ($responsibleInfos as $responsible) {
                    if (in_array($responsible['USER']['ID'], $responsibleIdsInItems))
                        $activityTask[] = [
                            'id' => 'group_' . $responsible['USER']['ID'],
                            'data' => $responsible['USER'],
                            'parent_id' => 0,
                            'has_child' => true,
                            'not_count' => true,
                            'actions' => [
                                'className' => 'main-grid-row-expand',
                            ],
                            'attrs' => [
                                'group-row' => 'group-row',
//                    'style' => 'background-color: grey;'
                            ],
                            'columns' => [
                                'RESPONSIBLE_ID' => $responsible['FULL_NAME'] !== '' ?
                                    '<a href="' . htmlspecialcharsbx($responsible['PATH']) . '" id="balloon_' . $arResult['GRID_ID'] . '_' . $responsible['USER']['ID'] . '" bx-tooltip-user-id="' . $responsible['USER']['ID'] . '">' . htmlspecialcharsbx($responsible['FULL_NAME']) . '</a> (' . $countVal[$responsible['USER']['ID']] . ')'
                                    : '',
//                        'ASSOCIATED_ENTITY_ID' => 'Кол-во',
//                        'STATUS' => $countVal[$responsible['USER']['ID']],
                            ],
                        ];
//                    self::setLog([
//                        '$responsibleInfos' => $activityTask,
//                    ], 'activity_report_$activityTask_1');
                }
            }
//            endregion
            foreach ($items as &$item) {
//                region Продолжение по сделке/лиду
                if (!isset($arResult['OWNER_INFOS'][$item['OWNER_TYPE_ID']][$item['OWNER_ID']])) {
                    $item['OWNER_TITLE'] = '';
                    $item['SHOW_URL'] = '';
                    $item['ENTITY_TYPE_CAPTION'] = '';
                    $item['OWNER_PATH'] = '';
                } else {
                    $ownerInfo = $arResult['OWNER_INFOS'][$item['OWNER_TYPE_ID']][$item['OWNER_ID']];
                    $item['OWNER_TITLE'] = $ownerInfo['TITLE'];
                    $item['SHOW_URL'] = $ownerInfo['SHOW_URL'];
                    $item['ENTITY_TYPE_CAPTION'] = $ownerInfo['ENTITY_TYPE_CAPTION'];
                    $item['OWNER_PATH'] = $item['ENTITY_TYPE_CAPTION'] . ': <a href=' . $item['SHOW_URL'] . '>' . $item['OWNER_TITLE'] . '</a>';
                }
//                endregion
//                region Продолжение по ответственному
                $responsibleID = $item['~RESPONSIBLE_ID'];
                if (isset($responsibleInfos[$responsibleID])) {
                    $responsibleInfo = $responsibleInfos[$responsibleID];

                    $item['RESPONSIBLE'] = $responsibleInfo['USER'];
                    $item['~RESPONSIBLE_FULL_NAME'] = $responsibleInfo['FULL_NAME'];
                    $item['RESPONSIBLE_FULL_NAME'] = $responsibleInfo['HTML_FULL_NAME'];
                    $item['PATH_TO_RESPONSIBLE'] = $responsibleInfo['PATH'];

                    if (isset($arManager['MOP'][$responsibleID])) {
                        $ropInfo = $responsibleInfos[$arManager['MOP'][$responsibleID]];
                        $item['ROP_ID'] = $arManager['MOP'][$responsibleID];
                        $item['ROP'] = $ropInfo['USER'];
                        $item['~ROP_FULL_NAME'] = $ropInfo['FULL_NAME'];
                        $item['ROP_FULL_NAME'] = $ropInfo['HTML_FULL_NAME'];
                        $item['PATH_TO_ROP'] = $ropInfo['PATH'];
                    }
                }

//                endregion
//                region Записываем информацию в строки
                $contactLink = '';
                $companyLink = '';
                if ($item['CLIENT_ENTITY_TYPE_ID'] === CCrmOwnerType::Company) {
                    $companyLink = $item['CLIENT_PATH'];
                } elseif ($item['CLIENT_ENTITY_TYPE_ID'] === CCrmOwnerType::Contact || $item['CLIENT_ENTITY_TYPE_ID'] === CCrmOwnerType::Lead) {
                    $contactLink = $item['CLIENT_PATH'];
                }
                $parentId = 'group_' . $item['RESPONSIBLE_ID'];
                if ((!empty($expandedRows) && in_array($parentId, $expandedRows, true)) || $this->exportFlag) {
                    $activityTask[] = [
                        'id' => $item['ID'],
                        'data' => $item,
                        'parent_id' => $parentId,
                        'has_child' => false,
                        'columns' => [
                            'RESPONSIBLE_ID' => $item['~RESPONSIBLE_FULL_NAME'] !== '' ?
                                '<a href="' . htmlspecialcharsbx($item['PATH_TO_RESPONSIBLE']) . '" id="balloon_' . $arResult['GRID_ID'] . '_' . $item['ID'] . '" bx-tooltip-user-id="' . $item['RESPONSIBLE_ID'] . '">' . htmlspecialcharsbx($item['~RESPONSIBLE_FULL_NAME']) . '</a>'
                                : '',
                            'RESPONSIBLE_NAME' => $item['~RESPONSIBLE_FULL_NAME'],
                            'START_TIME' => $item['START_TIME'],
                            'THEME' => '<a href=' . $item['ACTIVITY_URL'] . ' target="_blank">' . $item['SUBJECT'] . '</a>',
                            'DESCRIPTION' => $item['DESCRIPTION'],
                            'COMPANY' => $companyLink,
                            'CONTACT' => $contactLink,
                            'ASSOCIATED_ENTITY_ID' => $item['OWNER_PATH'],
                            'STATUS' => $item['STATUS_TEXT'],
                            'TYPE_ID' => $item['TYPE_ID_TEXT'],
                            'CREATED' => $item['CREATED'],
                            'ROP' => $item['~ROP_FULL_NAME'] !== '' ?
                                '<a href="' . htmlspecialcharsbx($item['PATH_TO_ROP']) . '" id="balloon' . $arResult['GRID_ID'] . '_' . $item['ID'] . '" bx-tooltip-user-id="' . $item['ROP_ID'] . '">' . htmlspecialcharsbx($item['~ROP_FULL_NAME']) . '</a>'
                                : '',
                        ],
                    ];
                }

//                endregion
            }
        }
        self::setLog([
            '$sort' => $sort,
            'items' => $items,
            '$responsibleInfos' => $activityTask,
        ], 'activity_report_$activityTask_final');

//      region  Сортировка для вычисляемых полей
        $cmpTask = function ($a, $b) use ($sort) {
            $fld = array_key_first($sort);
            $direction = $sort[$fld] === 'asc' ? -1 : 1;

//            if ($fld === 'RESPONSIBLE_ID') $fld = "RESPONSIBLE_NAME";
            $fld = CTextParser::clearAllTags(
                htmlspecialchars_decode($fld, ENT_QUOTES)
            );

            if ($a['columns'][$fld] == $b['columns'][$fld]) {
                return 0;
            }
            return (($a['columns'][$fld] < $b['columns'][$fld]) ? -1 : 1) * $direction;
        };

        switch (array_key_first($sort)) {
            case 'RESPONSIBLE_ID':
            case 'COMPANY':
            case 'CONTACT':
            case 'ASSOCIATED_ENTITY_ID':
            case 'ROP':
                uasort($activityTask, $cmpTask);
        }
// endregion

        if ($this->exportFlag) {
            $forExcel = [];
            foreach ($activityTask as $task) {
                $forExcel[] = $task['columns'];
            }
            $this->arResult['FOR_EXPORT'] = $forExcel;
        }
        return $activityTask;
    }

    protected static function changeEncoding($data, $encoding = true)
    {
        if (defined('C_REST_CURRENT_ENCODING')) {
            if (is_array($data)) {
                $result = [];
                foreach ($data as $k => $item) {
                    $k = static::changeEncoding($k, $encoding);
                    $result[$k] = static::changeEncoding($item, $encoding);
                }
            } else {
                if ($encoding) {
                    $result = iconv(C_REST_CURRENT_ENCODING, "UTF-8//TRANSLIT", $data);
                } else {
                    $result = iconv("UTF-8", C_REST_CURRENT_ENCODING, $data);
                }
            }
        } else {
            $result = $data;
        }

        return $result;
    }

    protected static function wrapData($data, $debug = false)
    {
        if (defined('C_REST_CURRENT_ENCODING')) {
            $data = static::changeEncoding($data, true);
        }
        $return = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        if ($debug) {
            $e = json_last_error();
            if ($e != JSON_ERROR_NONE) {
                if ($e == JSON_ERROR_UTF8) {
                    return 'Failed encoding! Recommended \'UTF - 8\' or set define C_REST_CURRENT_ENCODING = current site encoding for function iconv()';
                }
            }
        }

        return $return;
    }

    public static function setLog(array $arData, string $type = '', int $level = 99): bool
    {
//        return true;
        if (!defined('C_REST_BLOCK_LOG') || C_REST_BLOCK_LOG !== true) {
            if (defined('C_REST_LOGS_DIR')) {
                $path = C_REST_LOGS_DIR;
            } else {
                $path = __DIR__ . '/logs/';
            }
            $date = new \DateTime();
            $path .= $date->format('Y-m-d/H') . '/';

            if (!file_exists($path)) {
                @mkdir($path, 0775, true);
            }

            try {
                $path .= $date->format('H-i-s-u') . '_' . $type . '_' . random_int(1, 9999999) . 'log';
            } catch (Exception $e) {
                $path .= $date->format('H-i-s-u') . '_' . $type . '_' . 9999999 . 'log';
            }
            if (!(!defined('C_REST_LOG_TYPE_DUMP') || C_REST_LOG_TYPE_DUMP !== true)) {
                $jsonLog = static::wrapData($arData);
                if ($jsonLog === false) {
                    $return = file_put_contents($path . '_backup.txt', var_export($arData, true));
                } else {
                    $return = file_put_contents($path . '.json', $jsonLog);
                }
            } else {
                $return = file_put_contents($path . '.txt', var_export($arData, true));
            }
        }

        return $return;
    }


    public function executeComponent()
    {
        global $APPLICATION;
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        $requestUri = new \Bitrix\Main\Web\Uri($request->getRequestedPage());
        $requestUri->addParams(array('sessid' => bitrix_sessid()));

        $this->arResult = [
            'GRID_ID' => $this->getGridId(),
            'HEADERS' => $this->getHeaders(),
            'FILTER' => $this->getFilterFields(),
            'FILTER_PRESETS' => $this->getFilterPresets(),
            'ENABLE_LIVE_SEARCH' => false,
            'DISABLE_SEARCH' => true,
            'SERVICE_URL' => $requestUri->getUri(),
        ];


        self::setLog([
            'presets' => $this->arResult['FILTER_PRESETS'],
        ], 'activity_report_presets');

        $grid = new \Bitrix\Main\Grid\Options($this->arResult['GRID_ID']);
        $filterOptions = new \Bitrix\Crm\Filter\UiFilterOptions($this->arResult['GRID_ID'], $this->arResult['FILTER_PRESETS']);
        $arDatetimeFields = array('CREATED', 'START_TIME', 'END_TIME', 'DEADLINE');
//		region Сортировка
        $gridSort = $grid->getSorting();
        $collapsedGroups = $grid->getCollapsedGroups();
        $expandedRows = $grid->getExpandedRows();

        self::setLog([
            '$gridSort' => $gridSort,
            'grid_options' => $grid->getOptions(),
            'filter_options' => $filterOptions->getOptions(),
            'expandedOptions' => $grid->getExpandedRows(),
            '$collapsedGroups' => $collapsedGroups,
            'grid_current_options' => $grid->getCurrentOptions(),
        ], 'activity_report_$gridSort');


        $sort = array_filter(
            $gridSort['sort'],
            function ($field) {
                return in_array($field, self::getSortableFields());
            },
            ARRAY_FILTER_USE_KEY
        );
        self::setLog([
            '$sort' => $sort,
        ], 'activity_report_$sort');
//        if (isset($sort['RESPONSIBLE_ID'])) {
//            $sort['RESPONSIBLE_ID'] = mb_strtoupper($sort['RESPONSIBLE_ID']);
//            self::setLog([
//                '$sort' => $sort['RESPONSIBLE_ID'],
//            ], 'activity_report_$test_sort');
//        }
        if (empty($sort)) {
            $sort = ['RESPONSIBLE_ID' => 'asc'];
        }
//		endregion

        //region Получение списка полей для чтения
        $arSelect = $grid->GetVisibleColumns();
        $arSelectMap = array_fill_keys($arSelect, true);

        if (empty($arSelectMap)) {
            foreach ($this->arResult['HEADERS'] as $arHeader) {
                if ($arHeader['default'] ?? false) {
                    $arSelectMap[$arHeader['id']] = true;
                }
            }
        }

        $arSelectMap['ID'] = true;
        $arSelectMap['TYPE_ID'] = true;
        $arSelectMap['DIRECTION'] = true;
        $arSelectMap['OWNER_ID'] = true;
        $arSelectMap['OWNER_TYPE_ID'] = true;
        $arSelectMap['PROVIDER_ID'] = true;
        $arSelectMap['PROVIDER_TYPE_ID'] = true;
        $arSelectMap['ASSOCIATED_ENTITY_ID'] = true;

        if (!isset($arSelectMap['RESPONSIBLE_ID'])) {
            $arSelectMap['RESPONSIBLE_ID'] = true;
        }
        if (!isset($arSelectMap['SUBJECT'])) {
            $arSelectMap['SUBJECT'] = true;
        }
        if (!isset($arSelectMap['PRIORITY'])) {
            $arSelectMap['PRIORITY'] = true;
        }
        if (!isset($arSelectMap['START_TIME'])) {
            $arSelectMap['START_TIME'] = true;
        }
        if (!isset($arSelectMap['END_TIME'])) {
            $arSelectMap['END_TIME'] = true;
        }
        if (!isset($arSelectMap['DEADLINE'])) {
            $arSelectMap['DEADLINE'] = true;
        }
        if (!isset($arSelectMap['COMPLETED'])) {
            $arSelectMap['COMPLETED'] = true;
        }

        if (isset($arSelectMap['DESCRIPTION'])) {
            $arSelectMap['DESCRIPTION_TYPE'] = true;
        }

        if (isset($arSelectMap['RESPONSIBLE_FULL_NAME'])) {
            $arSelectMap['RESPONSIBLE_ID'] = true;
            unset($arSelectMap['RESPONSIBLE_FULL_NAME']);
        }
        $arSelectMap[] = 'BINDINGS';
        $arSelect = array_unique(array_keys($arSelectMap), SORT_STRING);
        self::setLog([
            '$arSelect' => $arSelect,
        ], 'activity_report_$arSelect');
        // endregion


//	    region Фильтры
        $gridFilter = $filterOptions->getFilter(self::getFilterFields());
        self::setLog([
            '$gridFilter' => $gridFilter,
        ], 'activity_report_$gridFilter');
//        region Работа с полями фильтра
        foreach ($gridFilter as $k => $v) {
//            Временные поля
            if (preg_match('/(.*)_from$/i' . BX_UTF_PCRE_MODIFIER, $k, $arMatch)) {
                $fieldID = $arMatch[1];
                self::setLog([
                    '$fieldID' => $fieldID,
                ], 'activity_report_$fieldID_1');
                if ($v <> '' && in_array($fieldID, $arDatetimeFields, true)) {
                    $gridFilter['>=' . $fieldID] = $v;
                }
                unset($gridFilter[$k]);
            } elseif (preg_match('/(.*)_to$/i' . BX_UTF_PCRE_MODIFIER, $k, $arMatch)) {
                $fieldID = $arMatch[1];
                if ($v <> '' && in_array($fieldID, $arDatetimeFields, true)) {
                    if (!preg_match('/\d{1,2}:\d{1,2}(:\d{1,2})?$/' . BX_UTF_PCRE_MODIFIER, $v)) {
                        $v = CCrmDateTimeHelper::SetMaxDayTime($v);
                    }
                    $gridFilter['<=' . $fieldID] = $v;
                }
                unset($gridFilter[$k]);
            } elseif ($k == 'STATUS') {
                if ($v == 2) {
                    $gridFilter['>=' . $k] = $v;
                } elseif ($v == 1) {
                    $v = 1;
                    $gridFilter['<=' . $k] = $v;
                } elseif ($v == 3) {
                    $v = 1;
                    $gridFilter['<=' . $k] = $v;
                    $gridFilter['<=DEADLINE'] = date('d.m.Y H:i:s');
                }
                unset($gridFilter[$k]);
            } elseif ($k === 'COMPANY') {
                $arBindingFilter = array();
                foreach ($v as $pv) {
                    $filterValue = json_decode($pv, true);
                    foreach ($filterValue as $fk => $fv) {
                        $ownerTypeID = CCrmOwnerType::ResolveID($fk);
                        $innerFilter = array(
                            'OWNER_TYPE_ID' => $ownerTypeID,
                            'OWNER_ID' => $fv[0],
                        );
                        $arBindingFilter[] = $innerFilter;
                    }
                }
                if (!empty($arBindingFilter)) {
                    $gridFilter['BINDINGS'] = $arBindingFilter;
                }
                unset($gridFilter[$k]);
            } elseif ($k === 'ASSOCIATED_ENTITY_ID') {
                foreach ($v as $pv) {
                    $filterValue = json_decode($pv, true);
                    foreach ($filterValue as $fk => $fv) {
                        $ownerTypeID = CCrmOwnerType::ResolveID($fk);
                        $gridFilter['OWNER_TYPE_ID'] = $ownerTypeID;
                        $gridFilter['OWNER_ID'] = $fv[0];
                    }
                }
                unset($gridFilter[$k]);
            }
        }

//        $this->processServiceActions($gridFilter);
//        endregion
        $filter = $gridFilter;
//		$filter = array_filter($gridFilter, function ($fieldName) {
//			return in_array($fieldName, self::getFilterableFields());
//		},
//			ARRAY_FILTER_USE_KEY);
        self::setLog([
            '$filter' => $filter,
        ], 'activity_report_$filter');
//	    endregion

//	    region Pagination
        $gridNav = $grid->GetNavParams();
        self::setLog([
            '$gridNav' => $gridNav,
        ], 'activity_report_$gridNav');
        $pager = new PageNavigation('');
        $pager->setPageSize($gridNav['nPageSize']);
        self::setLog([
            '$pager' => $pager,
        ], 'activity_report_$pager_0');
        $pager->setRecordCount(CCrmActivity::GetCount($filter));
        self::setLog([
            '$pager' => $pager,
        ], 'activity_report_$pager_1');
        if ($request->offsetExists('page')) {
            $currentPage = $request->get('page');
            $pager->setCurrentPage($currentPage > 0 ? $currentPage : $pager->getPageCount());
        } else {
            $pager->setCurrentPage(1);
        }
        $limit = $pager->getLimit();
        $offset = $pager->getOffset();
        $navStartParams = [
            'nTopCount' => false,
            'nOffset' => $offset,
            'nPageSize' => $limit,
            'iNumPage' => $currentPage ?? 1,
            'checkOutOfRange' => true,
        ];

        self::setLog([
            '$pager' => $pager,
            '$limit' => $limit,
            '$offset' => $offset,
            '$navStartParams' => $navStartParams,
        ], 'activity_report_$pager_2');

//	    endregion

        $arManager = self::getFromList();
        $filterTuning = self::ropFilterTuning($arManager, $filter);
        $initiallyHasResponsibleID = $filterTuning['initiallyHasResponsibleID'];
        $filter = $filterTuning['filter'];

        if ($initiallyHasResponsibleID && empty($filter['RESPONSIBLE_ID']))
            $items = [];
        else {
            $countInfos = CCrmActivity::GetList([], $filter, [], false);
            $items = $this->prepareData($arManager, $sort, $filter, false, $navStartParams, $arSelect, $expandedRows);
        }
        $this->arResult['ROW_COUNT'] = $countInfos ?? '';
        $this->arResult['ACTIVITY_LIST'] = $items;
        $this->arResult['SORT'] = $sort;
        $this->arResult['PAGINATION'] = [
            'PAGE_NUM' => $pager->getCurrentPage(),
            'ENABLE_NEXT_PAGE' => $pager->getCurrentPage() < $pager->getPageCount(),
            'URL' => $request->getRequestedPage(),
        ];
//        $this->arResult['GRAPH_ARR'] = json_encode($graphArr);
//        $this->display();

        self::setLog([
            'items' => $items,
            'arRes' => $this->arResult,
            'arParam' => $this->arParams,
        ], 'activity_report_arParams');

        $exportAs = (array_key_exists('EXPORT_EXCEL', $_REQUEST) ? $_REQUEST['EXPORT_EXCEL'] : false);
        if($exportAs) {
            $this->includeComponentTemplate('export_excel');
            die();
        }
        else
            $template = '';

        //$this->checkAction();


        $this->includeComponentTemplate($template);
    }

    public function getGraphDataAction($gridId, $filter_presets)
    {
        $filterOptions = new \Bitrix\Crm\Filter\UiFilterOptions($gridId, $filter_presets);
        $arDatetimeFields = array('CREATED', 'START_TIME', 'END_TIME', 'DEADLINE');
        $gridFilter = $filterOptions->getFilter(self::getFilterFields());
        self::setLog([
            '$gridFilter' => $gridFilter,
        ], 'activity_report_$gridFilter');
//        region Работа с полями фильтра
        foreach ($gridFilter as $k => $v) {
//            Временные поля
            if (preg_match('/(.*)_from$/i' . BX_UTF_PCRE_MODIFIER, $k, $arMatch)) {
                $fieldID = $arMatch[1];
                self::setLog([
                    '$fieldID' => $fieldID,
                ], 'activity_report_$fieldID_1');
                if ($v <> '' && in_array($fieldID, $arDatetimeFields, true)) {
                    $gridFilter['>=' . $fieldID] = $v;
                }
                unset($gridFilter[$k]);
            } elseif (preg_match('/(.*)_to$/i' . BX_UTF_PCRE_MODIFIER, $k, $arMatch)) {
                $fieldID = $arMatch[1];
                if ($v <> '' && in_array($fieldID, $arDatetimeFields, true)) {
                    if (!preg_match('/\d{1,2}:\d{1,2}(:\d{1,2})?$/' . BX_UTF_PCRE_MODIFIER, $v)) {
                        $v = CCrmDateTimeHelper::SetMaxDayTime($v);
                    }
                    $gridFilter['<=' . $fieldID] = $v;
                }
                unset($gridFilter[$k]);
            } elseif ($k == 'STATUS') {
                if ($v == 2) {
                    $gridFilter['>=' . $k] = $v;
                } elseif ($v == 1) {
                    $v = 1;
                    $gridFilter['<=' . $k] = $v;
                } elseif ($v == 3) {
                    $v = 1;
                    $gridFilter['<=' . $k] = $v;
                    $gridFilter['<=DEADLINE'] = date('d.m.Y H:i:s');
                }
                unset($gridFilter[$k]);
            } elseif ($k === 'COMPANY') {
                $arBindingFilter = array();
                foreach ($v as $pv) {
                    $filterValue = json_decode($pv, true);
                    foreach ($filterValue as $fk => $fv) {
                        $ownerTypeID = CCrmOwnerType::ResolveID($fk);
                        $innerFilter = array(
                            'OWNER_TYPE_ID' => $ownerTypeID,
                            'OWNER_ID' => $fv[0],
                        );
                        $arBindingFilter[] = $innerFilter;
                    }
                }
                if (!empty($arBindingFilter)) {
                    $gridFilter['BINDINGS'] = $arBindingFilter;
                }
                unset($gridFilter[$k]);
            } elseif ($k === 'ASSOCIATED_ENTITY_ID') {
                foreach ($v as $pv) {
                    $filterValue = json_decode($pv, true);
                    foreach ($filterValue as $fk => $fv) {
                        $ownerTypeID = CCrmOwnerType::ResolveID($fk);
                        $gridFilter['OWNER_TYPE_ID'] = $ownerTypeID;
                        $gridFilter['OWNER_ID'] = $fv[0];
                    }
                }
                unset($gridFilter[$k]);
            }
        }
        $filter = $gridFilter;

        self::setLog([
            '$filter' => $gridFilter,
        ], 'activity_report_$filter_graph_1');

        $arManager = self::getFromList();
        $filterTuning = self::ropFilterTuning($arManager, $filter);
        $initiallyHasResponsibleID = $filterTuning['initiallyHasResponsibleID'];
        $filter = $filterTuning['filter'];

        $navStartParams = false;

        if (empty($filter)) {
            $navStartParams = [
                'nTopCount' => self::LIMIT_WO_FILTER,
//                'nPageSize' => 500,
            ];

            $curDate = date('d.m.Y H:i:s');
            $finishDate = strtotime("-60 days");
            $finishDate = date('d.m.Y H:i:s', $finishDate);

            $filter = [
                '>=START_TIME' => $finishDate,
                '<=START_TIME' => $curDate,
            ];
            self::setLog([
                '$filter' => $gridFilter,
                '$navStartParams' => $navStartParams,
                '$filterTest' => $filter,
            ], 'activity_report_$filter_graph_2');
        }

        if ($initiallyHasResponsibleID && empty($filter['RESPONSIBLE_ID']))
            $graphInfos = [];
        else
            $graphInfos = $this->getInitialDbData([], $filter, false, $navStartParams);

//        if (is_object($graphItems)) {
        $graphItems = [];
        foreach ($graphInfos as $graphInfo) {
            $new['START_TIME'] = strtotime($graphInfo['START_TIME']);
            $new['START_TIME'] = strtotime(date('d.m.Y', $new['START_TIME']));
            $graphItems[] = $new['START_TIME'] * 1000;
        }

        $graphItems = array_count_values($graphItems);
        $graphArr = [];
        foreach ($graphItems as $k => $v) {
            $graphArr[] = [
                'date' => $k,
                'value' => $v,
            ];
        }

        //        }
        self::setLog([
//            '$countInfos' => $countInfos,
            '$graphItems' => $graphItems,
            '$graphArr' => $graphArr,
        ], 'activity_report_$items_3');

        return $graphArr;
    }

    public function configureActions(): array
    {
        return [
            'getGraphData' => [
                'prefilters' => [],
                'postfilters' => []
            ],
        ];
    }

    protected function collapseParents(array $data)
    {
        self::setLog([
            'collapseParents' => $data,
        ], 'activity_report_collapseParents');

    }

    public function display() {
        global $APPLICATION;
        $exportAs = (array_key_exists('EXPORT_EXCEL', $_REQUEST) ? $_REQUEST['EXPORT_EXCEL'] : false);
        if ($exportAs) {
            CPonyExMonitoringListComponent::setLog([
                '$exportAs' => $exportAs,
            ], '$exportAs');
            $APPLICATION->RestartBuffer();

        $this->IncludeComponentTemplate('export_excel');

        }
    }
}

