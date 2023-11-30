<?php
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Web\Uri;
//use Advina\apps\cloudcontact\Utility;

\CJSCore::Init(['jquery']);
//$this->addExternalCss('/local/css/bootstrap3/ponyex.css');
//$this->addExternalCss('/local/css/ponyex.css') ;

//if(!$arResult['HAS_ACCESS']) {
//    echo "НЕТ ДОСТУПА К РЕСУРСУ<br>";
//}
//elseif(!$arResult['IS_ACTIVE']) {
//    echo "РЕСУРС НЕ АКТИВЕН<br>";
//} else

{
    ?>


    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
    <div id="chartdiv" style="min-height: 500px"></div>


    <?
    $gridManagerID = $arResult['GRID_ID'].'_MANAGER';
//    Кнопка "Экспорт в "Эксель"
    $linkButton = new \Bitrix\UI\Buttons\Button([
        "link" => $APPLICATION->GetCurPage() . '?EXPORT_EXCEL=Y',
        "text" => "Экспорт в Excel"
    ]);
    \Bitrix\UI\Toolbar\Facade\Toolbar::addButton($linkButton);

    $APPLICATION->SetTitle("Отчёт по активностям");
    $APPLICATION->IncludeComponent(
        'advina:crm.interface.grid',
        'titleflex',
        array(
            'GRID_ID' => $arResult['GRID_ID'],
            'HEADERS' => $arResult['HEADERS'],
            'ROWS'    => $arResult['ACTIVITY_LIST'],
            'SORT'      => $arResult['SORT'],
            'FILTER'    => $arResult['FILTER'],
            'FILTER_PRESETS' => $arResult['FILTER_PRESETS'],
            'ENABLE_LIVE_SEARCH' => $arResult['ENABLE_LIVE_SEARCH'],
            'DISABLE_SEARCH' => $arResult['DISABLE_SEARCH'],
            'PAGINATION' => $arResult['PAGINATION'],
            'ENABLE_COLLAPSIBLE_ROWS' => true,
            'PAGE_SIZES' => [
                ['NAME' => "5", 'VALUE' => '5'],
                ['NAME' => '10', 'VALUE' => '10'],
                ['NAME' => '20', 'VALUE' => '20'],
                ['NAME' => '50', 'VALUE' => '50'],
//                ['NAME' => '75', 'VALUE' => '75'],
                ['NAME' => '100', 'VALUE' => '100'],
                ['NAME' => '200', 'VALUE' => '200'],
                array("NAME" => "500", "VALUE" => "500"),
            ],
            'SHOW_PAGESIZE'             => true,
//            'ACTION_PANEL' => $controlPanel,
//            'ENABLE_ROW_COUNT_LOADER' => true,
            'AJAX_ID' => '',
            'AJAX_OPTION_JUMP' => 'N',
            'AJAX_OPTION_HISTORY' => 'N',
            'AJAX_LOADER' => null,
            'TOTAL_ROWS_COUNT' => $arResult['ROW_COUNT'],
        ),
        $this->getComponent(),
        array('HIDE_ICONS' => 'Y',)
    );
}
?>

<script>
    BX.ready(function() {

        var root = am5.Root.new("chartdiv");
        root.setThemes([
            am5themes_Animated.new(root)
        ]);

        var chart = root.container.children.push(
            am5xy.XYChart.new(root, {
                // focusable: false,
                panX: false,
                panY: false,
                wheelX: "panX",
                wheelY: "zoomX",

            })
        );


        var cursor = chart.set("cursor", am5xy.XYCursor.new(root, {
            // xAxis: xAxis,
            behavior: "zoomX"
        }));
        cursor.lineY.set("visible", false);

        var xAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
            maxDeviation: 0,
            groupData: false,
            baseInterval: {
                timeUnit: "day",
                count: 1
            },
            renderer: am5xy.AxisRendererX.new(root, {
                minGridDistance: 40
            }),
            tooltipDateFormat: "dd.MM",
            tooltip: am5.Tooltip.new(root, {})
        }));

        xAxis.get("dateFormats")["day"] = "dd.MM";
        xAxis.get("periodChangeDateFormats")["day"] = "dd.MM";
        xAxis.get("dateFormats")["month"] = "MM";
        xAxis.get("periodChangeDateFormats")["month"] = "MM.YYYY";

        var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
            renderer: am5xy.AxisRendererY.new(root, {})
        }));

        var series = chart.series.push(am5xy.ColumnSeries.new(root, {
            name: "Series",
            xAxis: xAxis,
            yAxis: yAxis,
            valueYField: "value",
            valueXField: "date",
            tooltip: am5.Tooltip.new(root, {
                labelText: "{valueY}"
            })
        }));

        series.columns.template.setAll({ strokeOpacity: 0 });

        chart.set("scrollbarX", am5.Scrollbar.new(root, {
            orientation: "horizontal"
        }));

        function updateGraph(event) {
            BX.ajax.runComponentAction('advina:report.activities', 'getGraphData', {
                mode: 'class',
                data: {
                    gridId: "<?= $arResult['GRID_ID'] ?>",
                    filter_presets: <?= CUtil::PhpToJSObject($arResult['FILTER_PRESETS']) ?>
                },
            })
                .then((response) => {
                    console.log('runComponentAction: ', response);
                    // let response_object = JSON.parse(response.data[0]);
                    // console.log('runComponentAction: ', response_object);
                    series.data.setAll(response.data);
                    series.appear(1000);
                    chart.appear(1000, 100);
                }, (response) => {
                    console.log('runComponentAction fail: ', response);
                });

        }

        BX.addCustomEvent("Grid::updated", updateGraph);

        updateGraph();
        });
</script>
