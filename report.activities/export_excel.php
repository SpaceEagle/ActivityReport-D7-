<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
{
	die();
}

define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC','Y');
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Tasks\Integration\SocialNetwork\Group;
use Bitrix\Tasks\Util\User;

Loc::loadMessages(__DIR__.'/template.php');
Loc::loadMessages(__DIR__.'/export_excel.php');

/** @var $APPLICATION CMain */
/** @var array $arResult */
/** @var array $arParams */
/** @var $component CBitrixComponent */
/** @var $this CBitrixComponentTemplate */



$APPLICATION->RestartBuffer();

header('Content-Description: File Transfer');
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header('Content-Disposition: attachment; filename="Activity Report.xls"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

$userCache = [];
$groupCache = [];

$columns = [
	'RESPONSIBLE_ID',
	'START_TIME',
	'THEME',
	'DESCRIPTION',
	'COMPANY',
	'CONTACT',
	'ASSOCIATED_ENTITY_ID',
	'STATUS',
	'TYPE_ID',
	'CREATED',
	'ROP'
];

?>

<meta http-equiv="Content-type" content="text/html;charset=<?=LANG_CHARSET ?>"/>

<table border="1">
	<thead>
	<tr>
		<?php foreach ($columns as $field):

			$header = Loc::getMessage("TASKS_EXCEL_{$field}");

			?><th><?=$header?></th>
		<?php endforeach;?>
	</tr>
	</thead>

	<tbody>
	<?php foreach ($arResult['FOR_EXPORT'] as $task):?>
		<tr>
			<?php
			foreach ($columns as $field)
			{
				$prefix = '';
				$columnValue = $task[$field];

				switch ($field)
				{
					case 'RESPONSIBLE_ID':
					case 'THEME':
					case 'COMPANY':
					case 'CONTACT':
					case 'ASSOCIATED_ENTITY_ID':
					case 'ROP':
						$columnValue = CTextParser::clearAllTags(
							htmlspecialchars_decode($task[$field], ENT_QUOTES)
						);
						break;
					case 'START_TIME':
						$columnValue = $task[$field];
						break;
				}

				echo '<td>'.$prefix.htmlspecialcharsbx($columnValue).'</td>';
			}
			?>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>