<?php
// ==========================================
// 1. 引入認證設定
// ==========================================
require_once 'config/auth.php';

// 檢查登入狀態 (不強制重定向)
$isLoggedIn = isUserLoggedIn();
$currentUser = getCurrentUser();

// 處理登出請求
if (isset($_POST['logout_action']) && $_POST['logout_action'] === '1') {
    handleLogoutRequest();
    // 重新啟動會話以生成新的 CSRF 令牌
    session_start();
    $newCsrfToken = generateCSRFToken();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'csrf_token' => $newCsrfToken]);
    exit;
}

// 處理登入表單提交
$loginResult = null;
$showError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
    require_once 'config/db.php';
    $loginResult = handleLoginRequest();
    if ($loginResult['success']) {
        $isLoggedIn = true;
        $currentUser = getCurrentUser();
        $loginResult = null;
    } else {
        $showError = true;
    }
}

// 生成 CSRF 令牌
$csrfToken = generateCSRFToken();

// ==========================================
// 2. 初始化資料為空 (由 JavaScript 調用 API 獲取)
// ==========================================
$payload = [
    'config' => ['fuelLimit' => 15, 'tpmsLimit' => 30],
    'data' => [
        'name' => 'Tucson L',
        'fuel' => 0, 'range' => 0, 'odometer' => 0, 'trip_distance_km' => 0, 'avgFuel' => 0,
        'tpms' => [0, 0, 0, 0], 'engine' => false,
        'recorded_at' => date('Y-m-d H:i:s'),
        'lat' => 25.033964, 'lng' => 121.564468,
        'cabin_temp' => 0
    ]
];

if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <!-- iOS Web App 設定 -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Tucson Link">
    <meta name="theme-color" content="#ffffff">
    <link rel="apple-touch-icon" href="icon.png">
    
    <title>Tucson Link 車輛管理</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        :root {
            --bg-color: #f5f5f7;
            --card-bg: #ffffff;
            --text-main: #1d1d1f;
            --text-sub: #86868b;
            --text-light: #b0b0b5;
            --shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            --color-good: #34c759;
            --color-warning: #ff9f0a;
            --color-danger: #ff3b30;
            --accent-blue: #007aff;
            --safe-top: env(safe-area-inset-top, 20px);
            --safe-bottom: env(safe-area-inset-bottom, 20px);
        }

        * {
            -webkit-user-select: none; user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
        }
        input, textarea { -webkit-user-select: text; user-select: text; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            margin: 0; display: flex; justify-content: center; min-height: 100dvh;
            overscroll-behavior-y: none;
        }

        .app-container {
            width: 100%; max-width: 420px; background-color: var(--card-bg); height: 100dvh;
            position: relative; display: flex; flex-direction: column; overflow: hidden;
            box-shadow: var(--shadow);
        }

        /* 毛玻璃遮罩層 */
        .login-overlay {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeInOverlay 0.4s ease-out;
        }

        .login-overlay.show {
            display: flex;
        }

        @keyframes fadeInOverlay {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* 登入容器 */
        .login-modal-content {
            width: 85%;
            max-width: 320px;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            animation: slideUpModal 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes slideUpModal {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-modal-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .login-modal-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .login-modal-header p {
            font-size: 13px;
            color: var(--text-sub);
        }

        .login-modal-form .form-group {
            margin-bottom: 18px;
        }

        .login-modal-form label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .login-modal-form input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            background: rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
            color: var(--text-main);
        }

        .login-modal-form input:focus {
            outline: none;
            background: white;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .login-modal-form input::placeholder {
            color: var(--text-light);
        }

        .login-modal-error {
            display: none;
            padding: 10px 12px;
            background: rgba(255, 59, 48, 0.08);
            border-left: 3px solid var(--color-danger);
            border-radius: 8px;
            color: var(--color-danger);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .login-modal-error.show {
            display: block;
        }

        .login-modal-btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, var(--accent-blue) 0%, #0051d5 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 122, 255, 0.3);
            margin-top: 5px;
        }

        .login-modal-btn:active {
            transform: scale(0.98);
        }

        .login-modal-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .header { 
            padding-top: calc(20px + var(--safe-top)); padding-left: 30px; padding-right: 30px; padding-bottom: 0;
            z-index: 10; flex-shrink: 0; display: flex; justify-content: space-between; align-items: flex-start;
        }
        .header-left { flex: 1; }
        .header-logout {
            opacity: 0.4; cursor: pointer; transition: opacity 0.3s ease; padding: 5px 8px;
            font-size: 14px; color: var(--text-sub); border: none; background: none;
            display: flex; align-items: center; gap: 4px; text-decoration: none;
        }
        .header-logout:hover { opacity: 0.7; }
        .header h1 { font-size: 28px; margin: 0; color: var(--text-main); font-weight: 700; }
        .status-badge { font-size: 14px; color: var(--text-sub); margin-top: 6px; display: flex; align-items: center; gap: 6px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background-color: #ccc; transition: background-color 0.3s; }
        .dot.active { background-color: var(--color-good); box-shadow: 0 0 8px rgba(52, 199, 89, 0.4); }
        .update-info { font-size: 11px; color: var(--text-light); margin-top: 6px; display: flex; align-items: center; gap: 5px; font-weight: 500; opacity: 0.8; animation: fadeIn 1s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 0.8; } }

        .dashboard-main {
            flex: 1; position: relative; display: flex; flex-direction: column; padding: 0 20px;
            overflow-y: auto; overflow-x: hidden;
            padding-bottom: calc(90px + var(--safe-bottom)); 
            -webkit-overflow-scrolling: touch;
        }

        .visual-row { display: flex; width: 100%; height: 340px; position: relative; margin-top: 10px; flex-shrink: 0; }
        .car-visual {
            flex: 1.2; position: relative; background-image: url('car.png'); background-size: contain; background-repeat: no-repeat; background-position: center bottom;
            transform: rotate(0deg); z-index: 1; cursor: pointer; transition: opacity 0.3s ease, transform 0.1s; margin-left: -10px;
        }
        .car-visual:active { transform: scale(0.98); }
        .car-visual.updating { opacity: 0.6; animation: pulse-loading 1s infinite; }
        .stats-container { flex: 0.8; display: flex; flex-direction: column; justify-content: center; gap: 20px; text-align: right; z-index: 2; padding-right: 10px; }

        /* Controls Card */
        .controls-card {
            display: flex; justify-content: space-between; align-items: flex-start;
            background: #fff; border-radius: 16px; padding: 20px 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.02);
            margin-top: 10px; margin-bottom: 10px; flex-shrink: 0; flex-wrap: wrap;
        }
        
        .control-btn {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            background: none; border: none; cursor: pointer; transition: none; 
            -webkit-tap-highlight-color: transparent; position: relative;
            flex: 1; min-width: 60px;
        }
        
        .icon-circle {
            width: 56px; height: 56px; border-radius: 50%; border: 1px solid #f0f0f0;
            display: flex; justify-content: center; align-items: center; font-size: 24px; 
            color: var(--text-sub); background: #fff; position: relative;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: transform 0.2s ease-out;
        }
        .icon-circle i { position: relative; z-index: 2; } 

        .progress-ring {
            position: absolute; top: -2px; left: -2px; width: 60px; height: 60px;
            transform: rotate(-90deg); pointer-events: none; z-index: 1;
        }
        .progress-ring circle {
            fill: transparent; stroke-width: 3; r: 28; cx: 30; cy: 30;
        }
        .bg-ring { stroke: #f0f0f0; }
        .fg-ring {
            stroke: var(--accent-blue); stroke-dasharray: 176; stroke-dashoffset: 176; 
            transition: stroke-dashoffset 1s linear; 
        }

        .control-btn.pressing .icon-circle { transform: scale(0.92); } 
        .control-btn.pressing .fg-ring { stroke-dashoffset: 0; } 
        .control-btn span { font-size: 11px; font-weight: 500; color: var(--text-sub); transition: color 0.1s; }
        
        #btn-start .fg-ring { stroke: var(--color-good); } 
        #btn-start.running .icon-circle { background: var(--color-good); color: white; border-color: var(--color-good); box-shadow: 0 4px 15px rgba(52, 199, 89, 0.3); }
        #btn-start.running .bg-ring { stroke: rgba(255,255,255,0.3); } 
        #btn-start.running .fg-ring { stroke: #fff; } 

        @keyframes phantom-burst {
            0% { transform: scale(0.92); box-shadow: 0 0 0 0 rgba(0, 122, 255, 0.6); }
            40% { transform: scale(1.1); box-shadow: 0 0 20px 10px rgba(0, 122, 255, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0, 122, 255, 0); }
        }
        @keyframes phantom-burst-green {
            0% { transform: scale(0.92); box-shadow: 0 0 0 0 rgba(52, 199, 89, 0.6); }
            40% { transform: scale(1.1); box-shadow: 0 0 20px 10px rgba(52, 199, 89, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(52, 199, 89, 0); }
        }

        .control-btn.triggered .icon-circle { animation: phantom-burst 0.4s ease-out; }
        #btn-start.triggered .icon-circle { animation: phantom-burst-green 0.4s ease-out; }

        /* Bottom Fixed Controls */
        .controls-fixed {
            position: fixed; bottom: 0; left: 50%; transform: translateX(-50%);
            width: 100%; max-width: 320px;
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-top-left-radius: 20px; border-top-right-radius: 20px;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.08); z-index: 100;
            padding: 10px 20px; padding-bottom: calc(5px + var(--safe-bottom));
            display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;
        }
        
        .grid-btn {
            background: #f7f7f9; border-radius: 12px; padding: 12px 5px;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            border: 1px solid rgba(0,0,0,0.02); cursor: pointer; transition: all 0.2s ease;
        }
        .grid-btn i { font-size: 15px; color: var(--accent-blue); transition: color 0.2s; }
        .grid-btn span { font-size: 12px; font-weight: 600; color: var(--text-main); transition: color 0.2s; white-space: nowrap; }
        .grid-btn:active { transform: scale(0.96); background-color: #e5e5ea; }
        .grid-btn.active { background-color: var(--accent-blue); }
        .grid-btn.active i, .grid-btn.active span { color: white; }

        /* Expansion Panel */
        #expansion-panel {
            width: 100%; background: #fff; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            overflow: hidden; max-height: 0; transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 10px; flex-shrink: 0;
        }
        #expansion-panel.open { max-height: 500px; }
        .panel-content { display: none; padding: 10px; }
        .panel-content.active { display: block; animation: fadeIn 0.3s ease; }

        /* Status Snapshot */
        .status-snapshot {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px 5px;
            background: #fff; border-radius: 16px; padding: 15px 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.02);
            margin-top: 5px; flex-shrink: 0;
        }
        .snapshot-item { display: flex; flex-direction: column; align-items: center; gap: 4px; min-width: 0; }
        .snapshot-label { font-size: 10px; color: var(--text-sub); font-weight: 500; display: flex; align-items: center; gap: 3px; }
        .snapshot-label i { font-size: 11px; color: var(--accent-blue); opacity: 0.8; }
        .snapshot-data { display: flex; align-items: baseline; gap: 1px; }
        .snapshot-value { font-size: 15px; font-weight: 600; color: var(--text-main); }
        .snapshot-unit { font-size: 10px; color: var(--text-sub); }
        .sub-val { font-size: 11px; color: #86868b; margin-left: 4px; font-weight: 400; }

        /* Other Styles */
        .mini-map-wrapper { width: 100%; height: 200px; border-radius: 10px; overflow: hidden; cursor: pointer; }
        #mini-map { width: 100%; height: 100%; }
        
        .rescue-info { 
            background: #fff5f5; color: var(--color-danger); 
            padding: 12px; border-radius: 10px; 
            font-weight: 600; font-size: 14px; margin-bottom: 10px; 
            display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap;
        }
        .rescue-info a { color: var(--color-danger); text-decoration: none; border-bottom: 1px dashed var(--color-danger); }
        .rescue-divider { opacity: 0.3; }
        
        .doc-img-wrapper { width: 100%; border-radius: 10px; overflow: hidden; border: 1px solid #eee; cursor: zoom-in; }
        .doc-img-wrapper img { width: 100%; display: block; }

        /* Stats & TPMS */
        .primary-stats { display: flex; flex-direction: column; gap: 25px; }
        .stat-item label { font-size: 12px; color: var(--text-sub); margin-bottom: 2px; font-weight: 600; text-transform: uppercase; }
        .stat-item .value-group { display: flex; align-items: baseline; justify-content: flex-end; }
        .stat-item .value { font-size: 36px; font-weight: 300; color: var(--text-main); line-height: 1; }
        .stat-item .unit { font-size: 13px; color: var(--text-sub); margin-left: 4px; font-weight: 500; }
        .stat-item.alert .value, .stat-item.alert .unit, .stat-item.alert label { color: var(--color-danger); }
        .secondary-stats { display: flex; flex-direction: column; gap: 10px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.05); }
        .mini-stat { display: flex; flex-direction: column; }
        .mini-stat label { font-size: 10px; color: #999; margin-bottom: 1px; }
        .mini-stat .mini-value { font-size: 20px; color: #333; font-weight: 600; }
        .mini-stat .mini-unit { font-size: 10px; color: #999; }
        
        .tpms-tag {
            position: absolute; background: rgba(255, 255, 255, 0.9); width: 44px; height: 44px; border-radius: 50%;
            display: flex; flex-direction: column; align-items: center; justify-content: center; backdrop-filter: blur(5px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); border: 2px solid transparent; transition: all 0.3s ease;
        }
        .tpms-tag span { font-size: 16px; font-weight: 800; color: var(--text-main); line-height: 1; }
        .tpms-tag label { font-size: 8px; font-weight: 600; color: var(--text-sub); margin-top: 1px; }
        .tpms-tag.status-ok { border-color: var(--color-good); color: var(--color-good); }
        .tpms-tag.status-warn { border-color: var(--color-danger); background: #fff5f5; animation: pulse-border 2s infinite; }
        @keyframes pulse-border { 0% { box-shadow: 0 0 0 0 rgba(255, 59, 48, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(255, 59, 48, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 59, 48, 0); } }
        .fl { top: 20%; left: 5px; } .fr { top: 20%; right: 10px; } .rl { bottom: 22%; left: 5px; } .rr { bottom: 22%; right: 10px; }

        /* Toast */
        #toast-box {
            position: absolute; top: calc(20px + var(--safe-top)); left: 50%; transform: translateX(-50%) translateY(-150%);
            background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(10px); color: white;
            padding: 16px 32px; border-radius: 50px; font-size: 18px; font-weight: 600;
            z-index: 200; opacity: 0; pointer-events: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex; align-items: center; gap: 12px; white-space: nowrap;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        }
        #toast-box.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        #toast-box i { color: var(--color-good); font-size: 22px; }

        /* Info Modal (Floating Container) */
        #info-modal {
            display: none; position: fixed; z-index: 9999; left: 0; top: 0;
            width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px);
            justify-content: center; align-items: flex-end; /* 從底部滑出 */
            opacity: 0; transition: opacity 0.3s ease;
        }
        #info-modal.show { display: flex; opacity: 1; }
        
        .info-modal-content {
            width: 100%; max-width: 420px; 
            background: #fff; 
            border-top-left-radius: 20px; border-top-right-radius: 20px;
            box-shadow: 0 -5px 30px rgba(0,0,0,0.1);
            transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            max-height: 80vh; overflow-y: auto;
            padding: 20px; padding-bottom: calc(20px + var(--safe-bottom));
        }
        #info-modal.show .info-modal-content { transform: translateY(0); }
        
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0;
        }
        .modal-title { font-size: 18px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
        .modal-close { font-size: 24px; color: var(--text-sub); cursor: pointer; padding: 5px; }

        /* Image Modal (Full Screen) */
        #img-modal {
            display: none; position: fixed; z-index: 10000; left: 0; top: 0;
            width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); backdrop-filter: blur(5px);
            justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease;
            overflow: hidden; /* 防止背景捲動 */
        }
        #img-modal.show { display: flex; opacity: 1; }
        .img-modal-content {
            max-width: 95%; max-height: 90%; border-radius: 8px; 
            box-shadow: 0 0 30px rgba(0,0,0,0.5);
            /* 預設狀態，JS 會接手 transform */
            transform: scale(1);
            transition: transform 0.1s ease-out; /* 縮放時的順暢度 */
            cursor: grab; /* 提示可拖曳 */
        }
        .img-modal-content:active { cursor: grabbing; }
        .close-modal-hint {
            position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%);
            color: rgba(255,255,255,0.7); font-size: 14px; pointer-events: none;
            z-index: 10001; text-align: center;
            background: rgba(0,0,0,0.5); padding: 5px 10px; border-radius: 15px;
        }

        @keyframes pulse-loading { 0% { opacity: 0.6; } 50% { opacity: 0.8; } 100% { opacity: 0.6; } }
    </style>
</head>

<body>
    <!-- Image Modal (Zoomable) -->
    <div id="img-modal" onclick="if(event.target === this) closeImgModal()">
        <img class="img-modal-content" id="img-modal-src">
        <div class="close-modal-hint">雙指/滾輪縮放・點擊背景關閉</div>
    </div>

    <!-- Info Modal (Bottom Sheet) -->
    <div id="info-modal" onclick="if(event.target === this) closeInfoModal()">
        <div class="info-modal-content">
            <div class="modal-header">
                <div class="modal-title" id="info-modal-title"></div>
                <div class="modal-close" onclick="closeInfoModal()">&times;</div>
            </div>
            <div id="info-modal-body"></div>
        </div>
    </div>
    
    <!-- Hidden Templates -->
    <div id="template-map" style="display:none;">
        <div class="mini-map-wrapper" style="height: 300px;"><div id="mini-map"></div></div>
    </div>
    <div id="template-doc" style="display:none;">
        <div class="rescue-info">
            <div style="display:inline-block;">
                <i class="fas fa-phone-volume"></i> 道路救援：<a href="tel:0800024024">0800-024-024</a>
            </div>
            <span class="rescue-divider">|</span>
            <div style="display:inline-block;">
                <a href="https://www.hyundai-motor.com.tw/location.html" target="_blank">
                    <i class="fas fa-map-marker-alt"></i> 現代據點
                </a>
            </div>
        </div>
        <div class="doc-img-wrapper" onclick="openImgModal('duty01.png?t=' + new Date().getTime())">
            <img src="duty01.png" alt="Duty Schedule" id="duty-img">
        </div>
    </div>

    <div class="app-container">
        <!-- 毛玻璃登入遮罩 -->
        <div class="login-overlay<?php echo !$isLoggedIn ? ' show' : ''; ?>" id="loginOverlay">
            <div class="login-modal-content">
                <div class="login-modal-header">
                    <h2>Tucson Link</h2>
                    <p>車輛遠端控制系統</p>
                </div>

                <div class="login-modal-error<?php echo $showError ? ' show' : ''; ?>" id="loginError">
                    <i class="fas fa-exclamation-circle"></i> <span id="loginErrorText"><?php echo $loginResult ? htmlspecialchars($loginResult['message']) : '帳號或密碼錯誤'; ?></span>
                </div>

                <form id="modalLoginForm" method="POST" class="login-modal-form">
                    <input type="hidden" name="login_action" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                    <div class="form-group">
                        <label for="modal-username">帳號</label>
                        <input 
                            type="text" 
                            id="modal-username" 
                            name="username" 
                            placeholder="輸入帳號"
                            autocomplete="username"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="modal-password">密碼</label>
                        <input 
                            type="password" 
                            id="modal-password" 
                            name="password" 
                            placeholder="輸入密碼"
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <button type="submit" class="login-modal-btn" id="modalSubmitBtn">登入</button>
                </form>
            </div>
        </div>

        <div id="toast-box"><i class="fas fa-check-circle"></i><span id="toast-msg">指令已發送</span></div>

        <div class="header">
            <div class="header-left">
                <h1 id="car-name">Tucson Link</h1>
                <div class="status-badge">
                    <div class="dot" id="engine-dot"></div>
                    <span id="engine-text">...</span>
                </div>
                <div class="update-info"><i class="far fa-clock"></i><span id="val-time">--/-- --:--</span></div>
            </div>
            <a href="javascript:void(0)" onclick="performLogout()" class="header-logout" title="登出">
                <i class="fas fa-sign-out-alt"></i><span>登出</span>
            </a>
        </div>

        <div class="dashboard-main">
            <!-- 1. Visual & Stats -->
            <div class="visual-row">
                <div class="car-visual" onclick="refreshData()" title="點擊更新數據">
                    <div class="tpms-tag fl" id="tag-fl"><span id="tpms-fl">--</span><label>PSI</label></div>
                    <div class="tpms-tag fr" id="tag-fr"><span id="tpms-fr">--</span><label>PSI</label></div>
                    <div class="tpms-tag rl" id="tag-rl"><span id="tpms-rl">--</span><label>PSI</label></div>
                    <div class="tpms-tag rr" id="tag-rr"><span id="tpms-rr">--</span><label>PSI</label></div>
                </div>
                <div class="stats-container">
                    <div class="primary-stats">
                        <div class="stat-item"><label>預估續航里程</label><div class="value-group"><span class="value" id="val-range">--</span><span class="unit">km</span></div></div>
                        <div class="stat-item" id="stat-fuel"><label>剩餘油量</label><div class="value-group"><span class="value" id="val-fuel">--</span><span class="unit">%</span></div></div>
                    </div>
                    <div class="secondary-stats">
                        <div class="mini-stat"><label>總里程</label><div><span class="mini-value" id="val-odo">--</span> <span class="mini-unit">km</span></div></div>
                        <div class="mini-stat"><label>加油後里程</label><div><span class="mini-value" id="val-trip">--</span> <span class="mini-unit">km</span></div></div>
                        <div class="mini-stat"><label>平均油耗</label><div><span class="mini-value" id="val-avg">--</span> <span class="mini-unit">km/L</span></div></div>
                    </div>
                </div>
            </div>

            <!-- 2. Status Snapshot -->
            <div class="status-snapshot">
                <div class="snapshot-item">
                    <div class="snapshot-label"><i class="fas fa-couch"></i>車內氣溫</div>
                    <div class="snapshot-data"><span class="snapshot-value" id="val-cabin-temp">--</span><span class="snapshot-unit">°C</span></div>
                </div>
                <div class="snapshot-item">
                    <div class="snapshot-label"><i id="weather-icon" class="fas fa-temperature-half"></i>車外氣溫</div>
                    <div class="snapshot-data"><span class="snapshot-value" id="val-temp">--</span><span class="snapshot-unit">°C</span></div>
                </div>
                <div class="snapshot-item">
                    <div class="snapshot-label"><i class="fas fa-droplet"></i>車外濕度</div>
                    <div class="snapshot-data"><span class="snapshot-value" id="val-humid">--</span><span class="snapshot-unit">%</span></div>
                </div>
                <div class="snapshot-item">
                    <div class="snapshot-label"><i class="fas fa-cloud-rain"></i>降雨機率</div>
                    <div class="snapshot-data"><span class="snapshot-value" id="val-rain">--</span><span class="snapshot-unit">%</span></div>
                </div>
                <div class="snapshot-item">
                    <div class="snapshot-label"><i class="fas fa-wind"></i>風速</div>
                    <div class="snapshot-data">
                        <i id="wind-dir-icon" class="fas fa-location-arrow" style="font-size: 10px; transform: rotate(0deg); margin-right:2px;"></i>
                        <span class="snapshot-value" id="val-wind">--</span>
                        <span class="snapshot-unit">km/h</span>
                    </div>
                </div>
                <div class="snapshot-item">
                    <div class="snapshot-label"><i class="fas fa-wrench"></i>距下次保養</div>
                    <div class="snapshot-data"><span class="snapshot-value">5,090</span><span class="snapshot-unit">km</span></div>
                </div>
            </div>

            <!-- 3. Controls Card (Moved Here) -->
            <div class="controls-card">
                <button class="control-btn" data-cmd="LOCK">
                    <div class="icon-circle">
                        <i class="fas fa-lock"></i>
                        <svg class="progress-ring"><circle class="bg-ring" cx="30" cy="30" r="28"/><circle class="fg-ring" cx="30" cy="30" r="28"/></svg>
                    </div>
                    <span>上鎖</span>
                </button>
                <button class="control-btn" data-cmd="UNLOCK">
                    <div class="icon-circle">
                        <i class="fas fa-lock-open"></i>
                        <svg class="progress-ring"><circle class="bg-ring" cx="30" cy="30" r="28"/><circle class="fg-ring" cx="30" cy="30" r="28"/></svg>
                    </div>
                    <span>解鎖</span>
                </button>
                <button class="control-btn" data-cmd="WINDOW_OPEN">
                    <div class="icon-circle">
                        <i class="fas fa-arrow-down"></i>
                        <svg class="progress-ring"><circle class="bg-ring" cx="30" cy="30" r="28"/><circle class="fg-ring" cx="30" cy="30" r="28"/></svg>
                    </div>
                    <span>開窗</span>
                </button>
                <button class="control-btn" data-cmd="WINDOW_CLOSE">
                    <div class="icon-circle">
                        <i class="fas fa-arrow-up"></i>
                        <svg class="progress-ring"><circle class="bg-ring" cx="30" cy="30" r="28"/><circle class="fg-ring" cx="30" cy="30" r="28"/></svg>
                    </div>
                    <span>關窗</span>
                </button>
                <button class="control-btn" id="btn-start" data-cmd="ENGINE">
                    <div class="icon-circle">
                        <i class="fas fa-power-off"></i>
                        <svg class="progress-ring"><circle class="bg-ring" cx="30" cy="30" r="28"/><circle class="fg-ring" cx="30" cy="30" r="28"/></svg>
                    </div>
                    <span>啟動/熄火</span>
                </button>
            </div>
        </div>

        <!-- [修改] Fixed Bottom Bar (Moved Grid Buttons Here) -->
        <div class="controls-fixed">
            <div class="grid-btn" onclick="openMapWithLocation()">
                <i class="fas fa-location-dot"></i><span>目前位置</span>
            </div>
            <div class="grid-btn" onclick="openInfoModal('doc')">
                <i class="fas fa-circle-info"></i><span>救援文件</span>
            </div>
            <div class="grid-btn" onclick="window.open('TucsonL-NX4-Book.pdf', '_blank')">
                <i class="fas fa-book"></i><span>車輛手冊</span>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let isEngineOn = false; let appConfig = { fuelLimit: 15, tpmsLimit: 30 };
        let map = null; let marker = null; let currentLat = 0; let currentLng = 0;
        let toastTimer = null;
        let isRefreshing = false; // 防止重複調用
        let dutyImgCache = null; // 快取圖片

        function initData() {
            // 檢查是否已登入
            const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
            
            if (!isLoggedIn) {
                // 未登入時，禁用儀表板的互動和內容
                document.querySelector('.dashboard-main').style.opacity = '0.3';
                document.querySelector('.controls-fixed').style.opacity = '0.3';
                document.querySelector('.dashboard-main').style.pointerEvents = 'none';
                document.querySelector('.controls-fixed').style.pointerEvents = 'none';
                
                // 自動聚焦到帳號輸入框
                setTimeout(() => {
                    modalUsername.focus();
                }, 100);
                return;
            }
            
            // 已登入時，恢復儀表板
            document.querySelector('.dashboard-main').style.opacity = '1';
            document.querySelector('.controls-fixed').style.opacity = '1';
            document.querySelector('.dashboard-main').style.pointerEvents = 'auto';
            document.querySelector('.controls-fixed').style.pointerEvents = 'auto';
            
            const payload = <?php echo json_encode($payload); ?>;
            appConfig = payload.config;
            currentLat = payload.data.lat; currentLng = payload.data.lng;
            updateDashboard(payload.data);
            if(currentLat && currentLng) updateWeather(currentLat, currentLng);
            initLongPress();
            
            // 自動抓取最新資料
            refreshData();
            
            // 在背景預先快取 duty01.png
            preloadDutyImage();
        }
        
        // 預先快取 duty01.png
        function preloadDutyImage() {
            if (dutyImgCache) {
                console.log('Duty image already cached');
                return;
            }
            
            console.log('Preloading duty image...');
            const img = new Image();
            img.onload = () => {
                dutyImgCache = img;
                console.log('Duty image cached successfully');
            };
            img.onerror = () => {
                console.error('Failed to preload duty image');
            };
            // 加上時間戳參數破壞瀏覽器快取
            img.src = 'duty01.png?t=' + new Date().getTime();
        }

        function initLongPress() {
            const buttons = document.querySelectorAll('.control-btn');
            const PRESS_DURATION = 1000; 

            buttons.forEach(btn => {
                // 移除可能存在的舊事件監聽器 (使用標記清除)
                if (btn._longPressInitialized) {
                    // 如果已經初始化過,跳過以避免重複綁定
                    return;
                }
                btn._longPressInitialized = true;
                
                let timer = null;
                let isPressed = false; // 防止重複觸發

                const startPress = (e) => {
                    if (isPressed) return; // 已經在按壓中，忽略
                    
                    if (e.type === 'touchstart') e.preventDefault(); 
                    isPressed = true;
                    btn.classList.add('pressing');
                    if(navigator.vibrate) navigator.vibrate(15); 
                    timer = setTimeout(() => {
                        btn.classList.add('triggered');
                        if(navigator.vibrate) navigator.vibrate([50, 30, 100]);
                        let cmd = btn.dataset.cmd;
                        if (cmd === 'ENGINE') {
                            cmd = isEngineOn ? 'STOP' : 'START';
                            isEngineOn = !isEngineOn;
                            updateEngineUI();
                        }
                        sendCommand(cmd);
                        btn.classList.remove('pressing');
                        setTimeout(() => btn.classList.remove('triggered'), 400); 
                    }, PRESS_DURATION);
                };
                const cancelPress = () => { 
                    if (timer) clearTimeout(timer); 
                    btn.classList.remove('pressing');
                    isPressed = false; // 重置狀態
                };
                btn.addEventListener('touchstart', startPress, {passive: false});
                btn.addEventListener('touchend', cancelPress);
                btn.addEventListener('touchcancel', cancelPress);
                btn.addEventListener('mousedown', startPress);
                btn.addEventListener('mouseup', cancelPress);
                btn.addEventListener('mouseleave', cancelPress);
            });
        }

        // --- Info Modal Logic ---
        function openInfoModal(type, locationData = null) {
            const modal = document.getElementById('info-modal');
            const body = document.getElementById('info-modal-body');
            const title = document.getElementById('info-modal-title');
            
            body.innerHTML = ''; // Clear previous content

            if (type === 'map') {
                title.innerHTML = '<i class="fas fa-location-dot"></i> 目前位置';
                // 如果傳入了新的定位資料,更新全域變數
                if (locationData) {
                    currentLat = locationData.lat;
                    currentLng = locationData.lng;
                    console.log('Updated location from API:', currentLat, currentLng);
                }
                // Clone map template
                const content = document.getElementById('template-map').cloneNode(true);
                content.style.display = 'block';
                body.appendChild(content);
                
                modal.style.display = 'flex';
                setTimeout(() => { 
                    modal.classList.add('show'); 
                    // Initialize Map after modal is shown
                    if (!map) {
                        map = L.map(body.querySelector('#mini-map'), { zoomControl: false, attributionControl: false }).setView([currentLat, currentLng], 15);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
                        marker = L.marker([currentLat, currentLng]).addTo(map);
                        map.on('click', openGoogleMap);
                    } else {
                         // If map already exists, just move it to the new container (Leaflet magic)
                         // But since we clone, it's easier to remove old map instance and create new one if needed, 
                         // or just re-attach the DOM. Here for simplicity, we re-init if container is empty or just re-render
                         // A cleaner way for simple usage:
                         map.remove();
                         map = L.map(body.querySelector('#mini-map'), { zoomControl: false, attributionControl: false }).setView([currentLat, currentLng], 15);
                         L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
                         marker = L.marker([currentLat, currentLng]).addTo(map);
                         map.on('click', openGoogleMap);
                    }
                    setTimeout(() => { map.invalidateSize(); }, 300);
                }, 10);
            } else if (type === 'doc') {
                title.innerHTML = '<i class="fas fa-circle-info"></i> 救援與文件';
                const content = document.getElementById('template-doc').cloneNode(true);
                content.style.display = 'block';
                body.appendChild(content);
                
                // 使用快取的圖片，若快取存在直接使用，否則載入
                const imgEl = content.querySelector('#duty-img');
                if (imgEl) {
                    if (dutyImgCache) {
                        // 使用快取圖片
                        imgEl.src = dutyImgCache.src;
                        console.log('Using cached duty image');
                    } else {
                        // 如果快取還沒完成，使用一般載入 (加上時間戳破壞快取)
                        imgEl.src = 'duty01.png?t=' + new Date().getTime();
                    }
                }
                
                modal.style.display = 'flex';
                setTimeout(() => { modal.classList.add('show'); }, 10);
            }
        }

        function closeInfoModal() {
            const modal = document.getElementById('info-modal');
            modal.classList.remove('show');
            setTimeout(() => { modal.style.display = 'none'; }, 300);
        }
        
        // 打開地圖前先取得最新定位資料
        async function openMapWithLocation() {
            try {
                console.log('Fetching latest location from API...');
                // 調用 API 獲取最新定位
                const response = await fetch('api/data.php?action=get_data&t=' + new Date().getTime());
                
                if (!response.ok) {
                    throw new Error('Failed to fetch location data');
                }
                
                const json = await response.json();
                console.log('Location API response:', json);
                
                if (json.success && json.data) {
                    const locationData = {
                        lat: json.data.lat,
                        lng: json.data.lng
                    };
                    // 傳入最新定位資料打開地圖
                    openInfoModal('map', locationData);
                } else {
                    console.error('Failed to get location data:', json);
                    // 降級處理:使用現有定位資料打開地圖
                    openInfoModal('map');
                }
            } catch (error) {
                console.error('Error fetching location:', error);
                // 降級處理:使用現有定位資料打開地圖
                openInfoModal('map');
            }
        }
        
        function openGoogleMap() {
            if(currentLat && currentLng) {
                const url = `https://www.google.com/maps/search/?api=1&query=${currentLat},${currentLng}`;
                const link = document.createElement('a');
                link.href = url; link.target = '_blank'; link.rel = 'noopener noreferrer';
                document.body.appendChild(link); link.click(); document.body.removeChild(link);
                // [新增] 點擊開啟地圖後，自動關閉浮動視窗
                closeInfoModal();
            } else { alert('無法取得位置資訊'); }
        }

        async function updateWeather(lat, lng) {
            try {
                const url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lng}&current=temperature_2m,relative_humidity_2m,is_day,weather_code,windspeed_10m,winddirection_10m&hourly=precipitation_probability&timezone=auto`;
                const response = await fetch(url);
                const data = await response.json();
                
                if(data.current) {
                    const current = data.current;
                    const temp = Math.round(current.temperature_2m);
                    const humid = current.relative_humidity_2m;
                    const code = current.weather_code;
                    const isDay = current.is_day;
                    const windSpeed = Math.round(current.windspeed_10m);
                    const windDir = current.winddirection_10m;

                    let rainProb = 0;
                    if(data.hourly && data.hourly.time) {
                        const now = new Date();
                        const currentHourStr = now.toISOString().slice(0, 13);
                        const index = data.hourly.time.findIndex(t => t.startsWith(currentHourStr));
                        if(index !== -1) {
                            rainProb = data.hourly.precipitation_probability[index];
                        }
                    }

                    document.getElementById('val-temp').innerText = temp;
                    document.getElementById('val-humid').innerText = humid;
                    document.getElementById('val-rain').innerText = rainProb;
                    document.getElementById('val-wind').innerText = windSpeed;
                    
                    const iconRotation = windDir - 45;
                    document.getElementById('wind-dir-icon').style.transform = `rotate(${iconRotation}deg)`;

                    const config = getWeatherConfig(code, isDay);
                    const iconEl = document.getElementById('weather-icon');
                    iconEl.className = `fas ${config.icon}`;
                    iconEl.style.color = config.color;
                }
            } catch (error) { console.error("Weather error", error); }
        }

        function getWeatherConfig(code, isDay) {
            let icon = 'fa-temperature-half'; let color = 'var(--accent-blue)';
            if (code === 0 || code === 1) { icon = isDay ? 'fa-sun' : 'fa-moon'; color = isDay ? '#ff9f0a' : '#5e5ce6'; } 
            else if (code === 2 || code === 3) { icon = isDay ? 'fa-cloud-sun' : 'fa-cloud-moon'; color = '#8e8e93'; }
            else if (code === 45 || code === 48) { icon = 'fa-smog'; color = '#aeaeb2'; }
            else if ((code >= 51 && code <= 67) || (code >= 80 && code <= 82)) { icon = 'fa-cloud-rain'; color = '#007aff'; }
            else if ((code >= 71 && code <= 77) || (code >= 85 && code <= 86)) { icon = 'fa-snowflake'; color = '#64d2ff'; }
            else if (code >= 95 && code <= 99) { icon = 'fa-bolt'; color = '#ffd60a'; }
            return { icon, color };
        }

        // --- Manual Zoom Logic ---
        let imgScale = 1;
        let imgX = 0, imgY = 0;
        let isDragging = false;
        let isPinching = false;
        let startX, startY;
        let initialDistance = 0;
        let initialScale = 1;

        function calculateDistance(p1, p2) {
            const dx = p2.clientX - p1.clientX;
            const dy = p2.clientY - p1.clientY;
            return Math.sqrt(dx * dx + dy * dy);
        }

        function openImgModal(src) {
            const modal = document.getElementById('img-modal');
            const img = document.getElementById('img-modal-src');
            img.src = src;
            modal.style.display = 'flex';
            setTimeout(() => { modal.classList.add('show'); }, 10);
            
            // Reset Zoom
            imgScale = 1; imgX = 0; imgY = 0;
            updateImgTransform();
            
            // Add listeners
            img.addEventListener('mousedown', startDrag);
            img.addEventListener('touchstart', startDrag, {passive: false});
            img.addEventListener('wheel', handleWheel, {passive: false});
            
            window.addEventListener('mousemove', drag);
            window.addEventListener('touchmove', drag, {passive: false});
            window.addEventListener('mouseup', endDrag);
            window.addEventListener('touchend', endDrag);
        }

        function closeImgModal() {
            const modal = document.getElementById('img-modal');
            modal.classList.remove('show');
            setTimeout(() => { modal.style.display = 'none'; }, 300);
            
            // Cleanup
            window.removeEventListener('mousemove', drag);
            window.removeEventListener('touchmove', drag);
            window.removeEventListener('mouseup', endDrag);
            window.removeEventListener('touchend', endDrag);
        }

        function updateImgTransform() {
            const img = document.getElementById('img-modal-src');
            img.style.transform = `translate(${imgX}px, ${imgY}px) scale(${imgScale})`;
        }

        function handleWheel(e) {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.1 : 0.1;
            imgScale = Math.min(Math.max(0.5, imgScale + delta), 4); // Limit zoom 0.5x to 4x
            updateImgTransform();
        }

        function startDrag(e) {
            // Handle pinch zoom with two fingers
            if (e.touches && e.touches.length === 2) {
                isPinching = true;
                isDragging = false;
                initialDistance = calculateDistance(e.touches[0], e.touches[1]);
                initialScale = imgScale;
                return;
            }
            
            // Single finger drag
            isPinching = false;
            isDragging = true;
            startX = e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
            startY = e.type.includes('mouse') ? e.clientY : e.touches[0].clientY;
        }

        function drag(e) {
            // Handle pinch zoom (two-finger drag)
            if (isPinching && e.touches && e.touches.length === 2) {
                e.preventDefault();
                const currentDistance = calculateDistance(e.touches[0], e.touches[1]);
                const scaleChange = currentDistance / initialDistance;
                imgScale = Math.min(Math.max(0.5, initialScale * scaleChange), 4);
                updateImgTransform();
                return;
            }
            
            // Handle single-finger drag
            if (!isDragging || !e.touches || e.touches.length !== 1) return;
            
            e.preventDefault();
            const clientX = e.clientX || e.touches[0].clientX;
            const clientY = e.clientY || e.touches[0].clientY;
            
            const dx = clientX - startX;
            const dy = clientY - startY;
            
            imgX += dx;
            imgY += dy;
            
            startX = clientX;
            startY = clientY;
            
            updateImgTransform();
        }

        function endDrag() {
            isDragging = false;
            isPinching = false;
        }

        async function refreshData() {
            // 防止重複調用
            if (isRefreshing) {
                console.log('Already refreshing, skipping...');
                return;
            }
            
            isRefreshing = true;
            const carEl = document.querySelector('.car-visual');
            carEl.classList.add('updating');
            
            try {
                console.log('Fetching data from API...');
                // 調用 API 獲取車輛資料
                const response = await fetch('api/data.php?action=get_data&t=' + new Date().getTime());
                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    if (response.status === 401) {
                        // 未授權，可能會話過期
                        console.log('Unauthorized - showing login overlay');
                        loginOverlay.classList.add('show');
                        return;
                    }
                    throw new Error('Network error: ' + response.status);
                }
                
                const json = await response.json();
                console.log('API response:', json);
                
                if (json.success) {
                    updateDashboard(json.data);
                    appConfig = json.config;
                    currentLat = json.data.lat; currentLng = json.data.lng;
                    if(map && marker && currentLat && currentLng) {
                        const newLatLng = [currentLat, currentLng];
                        marker.setLatLng(newLatLng); map.panTo(newLatLng);
                    }
                    if(currentLat && currentLng) updateWeather(currentLat, currentLng);
                    if(navigator.vibrate) navigator.vibrate(50);
                } else {
                    console.error('API returned success=false:', json);
                }
            } catch (error) { 
                console.error('Error in refreshData:', error); 
            } 
            finally { 
                setTimeout(() => { 
                    carEl.classList.remove('updating');
                    isRefreshing = false;
                    console.log('Refresh complete');
                }, 500); 
            }
        }

        function updateDashboard(data) {
            if(data.name) document.getElementById('car-name').innerText = "Tucson Link"; 
            document.getElementById('val-fuel').innerText = data.fuel;
            document.getElementById('val-range').innerText = data.range;
            document.getElementById('val-odo').innerText = data.odometer.toLocaleString();
            document.getElementById('val-trip').innerText = data.trip_distance_km;
            document.getElementById('val-avg').innerText = data.avgFuel;
            if(data.recorded_at) document.getElementById('val-time').innerText = formatDate(data.recorded_at);
            
            if(data.cabin_temp !== undefined) document.getElementById('val-cabin-temp').innerText = data.cabin_temp;

            const elFuelItem = document.getElementById('stat-fuel');
            if (data.fuel < appConfig.fuelLimit) elFuelItem.classList.add('alert');
            else elFuelItem.classList.remove('alert');

            updateTpms('fl', data.tpms[0]); updateTpms('fr', data.tpms[1]);
            updateTpms('rl', data.tpms[2]); updateTpms('rr', data.tpms[3]);
            isEngineOn = data.engine; updateEngineUI();
        }

        function formatDate(dateString) {
            const date = new Date(dateString.replace(/-/g, "/"));
            const M = (date.getMonth()+1).toString().padStart(2,'0');
            const d = date.getDate().toString().padStart(2,'0');
            const h = date.getHours().toString().padStart(2,'0');
            const m = date.getMinutes().toString().padStart(2,'0');
            return `${M}/${d} ${h}:${m}`;
        }
        function updateTpms(p, v) {
            const elTag = document.getElementById(`tag-${p}`);
            document.getElementById(`tpms-${p}`).innerText = (v===0)?'--':v;
            if (v>0 && v<appConfig.tpmsLimit) elTag.className = `tpms-tag ${p} status-warn`;
            else elTag.className = `tpms-tag ${p} status-ok`;
        }
        function toggleEngine() { let a = isEngineOn?"STOP":"START"; sendCommand(a); isEngineOn=!isEngineOn; updateEngineUI(); }
        function updateEngineUI() {
            const b = document.getElementById('btn-start'); const d = document.getElementById('engine-dot'); const t = document.getElementById('engine-text');
            if(isEngineOn){ b.classList.add('running'); d.classList.add('active'); t.innerText="引擎運轉中"; t.style.color="var(--color-good)"; }
            else{ b.classList.remove('running'); d.classList.remove('active'); t.innerText="車輛已熄火"; t.style.color="var(--text-sub)"; }
        }
        
        function showToast(msg) {
            const box = document.getElementById('toast-box');
            document.getElementById('toast-msg').innerText = msg;
            box.classList.add('show');
            if(toastTimer) clearTimeout(toastTimer);
            toastTimer = setTimeout(() => { box.classList.remove('show'); }, 2000);
        }

        function sendCommand(c) {
            console.log("CMD:", c);
            
            let text = "";
            let apiUrl = ""; 

            if (c === 'WINDOW_CLOSE') {
                text = "已發送：關窗";
                apiUrl = "https://gv.inskychen.com/carcmd/api.php?cmd=window_close";
            }
            else if (c === 'WINDOW_OPEN') {
                text = "已發送：開窗";
                apiUrl = "https://gv.inskychen.com/carcmd/api.php?cmd=window_open";
            }
            else if (c === 'LOCK') {
                text = "已發送：上鎖";
                apiUrl = "https://gv.inskychen.com/carcmd/api.php?cmd=lock";
            }
            else if (c === 'UNLOCK') {
                text = "已發送：解鎖";
                apiUrl = "https://gv.inskychen.com/carcmd/api.php?cmd=unlock";
            }
            else if (c === 'START') {
                text = "已發送：啟動引擎";
                apiUrl = "https://gv.inskychen.com/carcmd/api.php?cmd=boot";
            }
            else if (c === 'STOP') {
                text = "已發送：關閉引擎";
                apiUrl = "https://gv.inskychen.com/carcmd/api.php?cmd=stop";
            }
            
            if (apiUrl) {
                fetch(apiUrl)
                    .then(response => console.log('API Triggered', response))
                    .catch(err => console.error('API Failed', err));
            }

            showToast(text);
        }

        // 模態登入表單處理
        const modalLoginForm = document.getElementById('modalLoginForm');
        const modalSubmitBtn = document.getElementById('modalSubmitBtn');
        const loginOverlay = document.getElementById('loginOverlay');
        const loginError = document.getElementById('loginError');
        const modalUsername = document.getElementById('modal-username');
        const modalPassword = document.getElementById('modal-password');

        // 監聽模態登入表單提交
        let isSubmitting = false;
        modalLoginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            if (isSubmitting) {
                console.log('Already submitting, ignoring...');
                return;
            }
            
            isSubmitting = true;
            modalSubmitBtn.disabled = true;
            loginError.classList.remove('show');
            
            const formData = new FormData(modalLoginForm);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                // 隱藏毛玻璃層
                loginOverlay.classList.remove('show');
                
                // 恢復儀表板的可見性和互動性
                document.querySelector('.dashboard-main').style.opacity = '1';
                document.querySelector('.controls-fixed').style.opacity = '1';
                document.querySelector('.dashboard-main').style.pointerEvents = 'auto';
                document.querySelector('.controls-fixed').style.pointerEvents = 'auto';
                
                // 初始化按鈕事件 (登入後首次綁定)
                initLongPress();
                
                // 重新加載資料
                refreshData();
                
                // 重置提交狀態
                isSubmitting = false;
                modalSubmitBtn.disabled = false;
            })
            .catch(err => {
                console.error('Login error:', err);
                loginError.classList.add('show');
                modalSubmitBtn.disabled = false;
                isSubmitting = false;
            });
        });

        // 監聽密碼欄位自動填充 (Face ID/Touch ID)
        modalPassword.addEventListener('change', () => {
            if (modalUsername.value.trim().length >= 3 && modalPassword.value.trim().length >= 3) {
                setTimeout(() => {
                    // 觸發按鈕點擊而不是表單原生提交
                    modalSubmitBtn.click();
                }, 300);
            }
        });

        // 密碼欄位 Enter 鍵提交
        modalPassword.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                // 觸發按鈕點擊而不是表單原生提交
                modalSubmitBtn.click();
            }
        });

        // 執行登出
        function performLogout() {
            // 發送登出請求到伺服器
            const formData = new FormData();
            formData.append('logout_action', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 更新頁面上的 CSRF 令牌
                    if (data.csrf_token) {
                        document.querySelector('input[name="csrf_token"]').value = data.csrf_token;
                    }
                    
                    // 清除所有會話相關的 UI 狀態
                    loginOverlay.classList.add('show');
                    modalUsername.value = '';
                    modalPassword.value = '';
                    loginError.classList.remove('show');
                    
                    // 重設顯示數據
                    document.getElementById('car-name').innerText = 'Tucson Link';
                    document.getElementById('engine-text').innerText = '...';
                    
                    // 隱藏儀表板
                    document.querySelector('.dashboard-main').style.opacity = '0.3';
                    document.querySelector('.controls-fixed').style.opacity = '0.3';
                    document.querySelector('.dashboard-main').style.pointerEvents = 'none';
                    document.querySelector('.controls-fixed').style.pointerEvents = 'none';
                    
                    // 焦點回到登入表單
                    setTimeout(() => {
                        modalUsername.focus();
                    }, 100);
                }
            })
            .catch(err => {
                console.error('Logout error:', err);
                alert('登出失敗，請重試');
            });
        }

        window.onload = initData;
    </script>
</body>
</html>