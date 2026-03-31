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
function getVehicleData()
{
    $carData = [
        'name' => 'Tucson L',
        'fuel' => 0,
        'range' => 0,
        'odometer' => 0,
        'trip' => 0,
        'avgFuel' => 0,
        'tpms' => [0, 0, 0, 0],
        'engine' => false,
        'recorded_at' => date('Y-m-d H:i:s'),
        'lat' => 25.033964,
        'lng' => 121.564468,
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
            $carData['fuel'] = (int) $row['fuel_level_percent'];

            // 加油後里程 < 200 km：使用上一筆歷史油耗（實際量測值）
            // 加油後里程 >= 200 km：使用車機即時平均油耗（已行駛足夠距離、數值穩定）
            $tripDistance = (float) $row['trip_distance_km'];
            if ($tripDistance < 200) {
                $stmtKPL = $pdo->prepare("SELECT kpl FROM fuel_log WHERE vehicle_id = :vid ORDER BY id DESC LIMIT 1");
                $stmtKPL->execute(['vid' => 'BVB-7980']);
                $rowKPL = $stmtKPL->fetch();
                $calcKpl = $rowKPL ? (float) $rowKPL['kpl'] : (float) $row['avg_fuel_consumption'];

            } else {
                $calcKpl = (float) $row['avg_fuel_consumption'];
            }

            // avgFuel 顯示實際用於計算的 KPL，與預估里程保持一致
            $carData['avgFuel'] = $calcKpl;

            // 預估里程: (油量% / 100) * 油箱容量 * KPL
            $carData['range'] = (int) (($carData['fuel'] / 100) * FUEL_TANK_CAPACITY * $calcKpl);

            $carData['odometer'] = (float) $row['odometer_km'];
            $carData['trip_distance_km'] = (float) $row['trip_distance_km'];
            $carData['tpms'] = [(int) $row['tpms_fl'], (int) $row['tpms_fr'], (int) $row['tpms_rl'], (int) $row['tpms_rr']];
            $carData['engine'] = (bool) $row['is_engine_on'];
            $carData['key_sts'] = isset($row['key_sts']) ? (int) $row['key_sts'] : 0;
            $carData['recorded_at'] = $row['recorded_at'];
            $carData['cabin_temp'] = isset($row['air_ceil']) ? (float) $row['air_ceil'] : 0;
        }

        // 2. GPS 位置
        $stmtGPS = $pdo->prepare("SELECT lat, lng FROM gpslog WHERE dev_id = :did ORDER BY log_tim DESC LIMIT 1");
        $stmtGPS->execute(['did' => 'tucsonl']);
        $rowGPS = $stmtGPS->fetch();

        if ($rowGPS) {
            $carData['lat'] = (float) $rowGPS['lat'];
            $carData['lng'] = (float) $rowGPS['lng'];
        }

        // 3. 歷史油耗
        $stmtFuel = $pdo->prepare("SELECT log_tim as date, pre_odo_km as odo, add_fuel_percent as percent, kpl FROM fuel_log WHERE vehicle_id = :vid ORDER BY log_tim DESC LIMIT 20");
        $stmtFuel->execute(['vid' => 'BVB-7980']);
        $carData['fuel_history'] = $stmtFuel->fetchAll();

        // 4. 保養記錄
        $stmtMaint = $pdo->prepare("SELECT * FROM car_maintenance_records WHERE car_id = :vid ORDER BY service_date DESC");
        $stmtMaint->execute(['vid' => 'BVB-7980']);
        $maintenanceRecords = $stmtMaint->fetchAll();
        $carData['maintenance_records'] = $maintenanceRecords;

        // 計算距下次保養 (Cd/Dkm)
        if (!empty($maintenanceRecords)) {
            $latestMaint = $maintenanceRecords[0]; // 已按日期降序排列，取第一筆
            $serviceDate = new DateTime($latestMaint['service_date']);
            $currentMileage = (float) $latestMaint['current_mileage'];

            // A = (保養日期＋180天)
            $nextDate = clone $serviceDate;
            $nextDate->modify('+180 days');

            $today = new DateTime();
            // C = (A - today)
            $interval = $today->diff($nextDate);
            $daysLeft = (int) $interval->format('%r%a'); // %r 包含正負號

            // D = (保養里程+10000 - 總里程)
            $totalOdo = (float) $carData['odometer'];
            $kmLeft = ($currentMileage + 10000) - $totalOdo;

            $carData['next_maintenance'] = [
                'days' => $daysLeft,
                'km' => round($kmLeft)
            ];
        } else {
            $carData['next_maintenance'] = null;
        }
    } catch (PDOException $e) {
        $dbError = $e->getMessage();
        error_log("DB Error: " . $dbError);
    }

    // 配置資訊
    $config = [
        'fuelLimit' => FUEL_LIMIT,
        'tpmsLimit' => TPMS_LIMIT,
        'fuelTankCapacity' => FUEL_TANK_CAPACITY,
    ];

    $response = [
        'success' => true,
        'data' => $carData,
        'config' => $config,
    ];

    echo json_encode($response);
}

/**
 * 執行登出
 */
function performLogout()
{
    handleLogoutRequest();

    // 重新啟動會話以生成新的 CSRF 令牌
    session_start();
    $newCsrfToken = generateCSRFToken();

    echo json_encode([
        'success' => true,
        'csrf_token' => $newCsrfToken
    ]);
}
