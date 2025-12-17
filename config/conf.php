<?php
/**
 * ==========================================
 * 系統配置檔
 * ==========================================
 * 集中管理系統參數設定
 */

// 自動更新間隔設定（單位：秒）
define('AUTO_UPDATE_INTERVAL', 30);

// 救援文件圖檔名稱
define('DUTY_IMAGE', 'duty01.png');
define('DUTY_IMAGE2', 'duty02.png');
define('DUTY_IMAGE3', 'duty03.png');
define('DUTY_IMAGE4', 'duty04.png');

// 地圖預設縮放等級（1-20，數字越大越近）
define('MAP_DEFAULT_ZOOM', 15);

// 車輛控制 API 基礎 URL（使用 MQTT publish）
define('VEHICLE_API_BASE_URL', '/api/call.php');

// 按鈕長按持續時間（單位：毫秒）
define('BUTTON_PRESS_DURATION', 1000);

// 油量警示值（低於此百分比時顯示警示，單位：%）
define('FUEL_LIMIT', 20);

// 胎壓警示值（低於此值時顯示警示，單位：PSI）
define('TPMS_LIMIT', 30);

// 其他系統配置可在此新增
// define('OTHER_CONFIG', 'value');
?>
