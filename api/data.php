<?php
/**
 * ==========================================
 * Tucson Link API - 資料邏輯處理
 * ==========================================
 * 負責所有資料查詢和業務邏輯
 */

require_once dirname(__DIR__) . '/config/conf.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

// 檢查登入狀態
if (!isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 路由處理
$action = isset($_GET['action']) ? $_GET['action'] : 'get_data';

try {
    switch ($action) {
        case 'get_data':
            getVehicleData();
            break;
        
        case 'logout':
            performLogout();
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * 獲取車輛資料
 */
function getVehicleData() {
    $carData = [
        'name' => 'Tucson L',
        'fuel' => 0, 'range' => 0, 'odometer' => 0, 'trip' => 0, 'avgFuel' => 0,
        'tpms' => [0, 0, 0, 0], 'engine' => false,
        'recorded_at' => date('Y-m-d H:i:s'),
        'lat' => 25.033964, 'lng' => 121.564468,
        'cabin_temp' => 0
    ];

    $dbError = null;
    $dbConnected = false;
    
    try {
        $pdo = getDatabaseConnection();
        $dbConnected = true;
        
        // 1. 車輛基本資訊
        $stmt = $pdo->prepare("SELECT * FROM vehicle_logs WHERE vehicle_id = :vid ORDER BY recorded_at DESC LIMIT 1");
        $stmt->execute(['vid' => 'BVB-7980']);
        $row = $stmt->fetch();

        if ($row) {
            $carData['name'] = $row['vehicle_name'];
            $carData['fuel'] = (int)$row['fuel_level_percent'];
            
            // 獲取最新的 KPL (油耗) 替代平均油耗來計算預估里程
            $stmtKPL = $pdo->prepare("SELECT kpl FROM fuel_log WHERE vehicle_id = :vid ORDER BY id DESC LIMIT 1");
            $stmtKPL->execute(['vid' => 'BVB-7980']);
            $rowKPL = $stmtKPL->fetch();
            
            // 預估里程公式：當『加油後里程』大於100，使用 vehicle_logs 取得的 avg_fuel_consumption 計算
            // 小於等於 100，使用 fuel_log.kpl 計算
            $tripDistance = (float)$row['trip_distance_km'];
            if ($tripDistance > 100) {
                $calcKpl = (float)$row['avg_fuel_consumption'];
            } else {
                $calcKpl = $rowKPL ? (float)$rowKPL['kpl'] : (float)$row['avg_fuel_consumption'];
            }
            
            $carData['avgFuel'] = (float)$row['avg_fuel_consumption'];
            
            // 重新計算預估里程: (油量百分比 / 100) * 油箱容量(52L) * 計算用 KPL
            $carData['range'] = (int)(($carData['fuel'] / 100) * 52 * $calcKpl);
            
            $carData['odometer'] = (float)$row['odometer_km'];
            $carData['trip_distance_km'] = (float)$row['trip_distance_km'];
            $carData['tpms'] = [(int)$row['tpms_fl'], (int)$row['tpms_fr'], (int)$row['tpms_rl'], (int)$row['tpms_rr']];
            $carData['engine'] = (bool)$row['is_engine_on'];
            $carData['key_sts'] = isset($row['key_sts']) ? (int)$row['key_sts'] : 0;
            $carData['recorded_at'] = $row['recorded_at'];
            $carData['cabin_temp'] = isset($row['air_ceil']) ? (float)$row['air_ceil'] : 0;
        }
        
        // 2. GPS 位置
        $stmtGPS = $pdo->prepare("SELECT lat, lng FROM gpslog WHERE dev_id = :did ORDER BY log_tim DESC LIMIT 1");
        $stmtGPS->execute(['did' => 'tucsonl']);
        $rowGPS = $stmtGPS->fetch();
        
        if ($rowGPS) {
            $carData['lat'] = (float)$rowGPS['lat'];
            $carData['lng'] = (float)$rowGPS['lng'];
        }
    } catch (PDOException $e) {
        $dbError = $e->getMessage();
        error_log("DB Error: " . $dbError);
    }

    // 配置資訊
    $config = [
        'fuelLimit' => FUEL_LIMIT,
        'tpmsLimit' => TPMS_LIMIT
    ];

    $response = [
        'success' => true,
        'data' => $carData,
        'config' => $config,
        'debug' => [
            'db_connected' => $dbConnected,
            'db_error' => $dbError,
            'db_host' => DB_HOST
        ]
    ];

    echo json_encode($response);
}

/**
 * 執行登出
 */
function performLogout() {
    handleLogoutRequest();
    
    // 重新啟動會話以生成新的 CSRF 令牌
    session_start();
    $newCsrfToken = generateCSRFToken();
    
    echo json_encode([
        'success' => true,
        'csrf_token' => $newCsrfToken
    ]);
}
