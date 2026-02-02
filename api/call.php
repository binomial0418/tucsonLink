<?php
// 設定回傳格式為 JSON
header('Content-Type: application/json; charset=utf-8');

// 禁止瀏覽器快取
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

/* ========== MQTT 設定 ========== */
define('MQTT_HOST', '220.132.203.243');
define('MQTT_PORT', 50883);
define('MQTT_USER', 'esp32');
define('MQTT_PASS', '0988085240');
define('MQTT_TOPIC', 'owntracks/mt/cmd');

/* ========== MQTT 函數 ========== */
function mqtt_encode_length($length) {
    $encoded = '';
    do {
        $digit = $length % 128;
        $length = intval($length / 128);
        if ($length > 0) $digit = $digit | 0x80;
        $encoded .= chr($digit);
    } while ($length > 0);
    return $encoded;
}

function mqtt_connect_and_publish($host, $port, $user, $pass, $topic, $message, $timeout = 4) {
    $client_id = 'api_' . substr(md5(uniqid('', true)), 0, 10);
    $errno = 0; $errstr = '';
    $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$sock) return false;
    stream_set_timeout($sock, $timeout);

    // Variable header for CONNECT
    $protocol = "MQTT";
    $protoName = pack('n', strlen($protocol)) . $protocol;
    $protoLevel = chr(4); // MQTT 3.1.1
    $connectFlags = chr(0xC2);
    $keepAlive = pack('n', 60);
    $varHeader = $protoName . $protoLevel . $connectFlags . $keepAlive;

    // Payload: ClientID, Username, Password
    $payload = pack('n', strlen($client_id)) . $client_id;
    if ($user !== null) $payload .= pack('n', strlen($user)) . $user;
    if ($pass !== null) $payload .= pack('n', strlen($pass)) . $pass;

    $remaining = strlen($varHeader) + strlen($payload);
    $packet = chr(0x10) . mqtt_encode_length($remaining) . $varHeader . $payload;
    fwrite($sock, $packet);

    // Read CONNACK
    $connack = fread($sock, 4);
    if (strlen($connack) < 4) { fclose($sock); return false; }
    $returnCode = ord($connack[3]);
    if ($returnCode !== 0) { fclose($sock); return false; }

    // Publish (QoS 0)
    $topicPart = pack('n', strlen($topic)) . $topic;
    $payloadPub = $topicPart . $message;
    $remainingPub = strlen($payloadPub);
    $packetPub = chr(0x30) . mqtt_encode_length($remainingPub) . $payloadPub;
    fwrite($sock, $packetPub);

    fclose($sock);
    return true;
}

/* ========== 主要邏輯 ========== */

// 獲取參數 (支援 GET 或 POST)
$cmd = isset($_REQUEST['cmd']) ? $_REQUEST['cmd'] : '';

// 驗證 cmd
if (empty($cmd)) {
    http_response_code(400);
    echo json_encode(array('status' => 'error', 'message' => 'Parameter cmd is required'));
    exit;
}

// 執行 MQTT 發送
$result = mqtt_connect_and_publish(MQTT_HOST, MQTT_PORT, MQTT_USER, MQTT_PASS, MQTT_TOPIC, $cmd);

if ($result) {
    echo json_encode(array(
        'status' => 'success',
        'message' => 'Payload sent',
        'data' => array(
            'topic' => MQTT_TOPIC,
            'payload' => $cmd
        )
    ));
} else {
    http_response_code(502);
    echo json_encode(array('status' => 'error', 'message' => 'Failed to send MQTT message'));
}
?>
