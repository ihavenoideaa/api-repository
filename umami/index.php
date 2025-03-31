<?php
// 设置响应头
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

// 从环境变量中获取配置信息
$apiBaseUrl = "https://xxxxxxxxx.com";
$token = "xxxxxxxxxxxxxxxxxxx";
$websiteId = "xxxxxxxxxxxxxx";
$logFile = 'umami_fetch_error.log';

// 验证配置信息
if (!$token || !$websiteId) {
    http_response_code(500);
    echo json_encode(['error' => 'Missing API token or website ID in environment variables.']);
    exit;
}

// 获取当前时间戳（毫秒级）
$currentTimestamp = time() * 1000;

// Umami API 的起始时间戳（毫秒级）
$startTimestampLastMonth = strtotime("-1 month") * 1000;

// 定义 Umami API 请求函数
function fetchUmamiData($apiBaseUrl, $websiteId, $startAt, $endAt, $token, $logFile) {
    $url = "$apiBaseUrl/api/websites/$websiteId/event-data/fields?" . http_build_query([
        'startAt' => $startAt,
        'endAt' => $endAt
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        $errorMessage = "Error fetching data: $error\nURL: $url\n";
        file_put_contents($logFile, $errorMessage, FILE_APPEND);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {
        $errorMessage = "HTTP request failed with code $httpCode\nURL: $url\nResponse: $response\n";
        file_put_contents($logFile, $errorMessage, FILE_APPEND);
        return null;
    }

    // 解析 response 为 PHP 数组
    $arrayData = json_decode($response, true);

    // 过滤出 propertyName 为 link_count_info 的数据
    $filteredData = [];
    foreach ($arrayData as $item) {
        if ($item['propertyName'] === 'link_count_info') {
            // 解析 value 为 JSON 数组
            $valueJson = json_decode($item['value'], true);
            // 将 total 添加到 JSON 数据中
            $valueJson['count'] = $item['total'];
            $filteredData[] = $valueJson;
        }
    }
    
    // 按 total 数值从大到小排序
    usort($filteredData, function ($a, $b) {
        return $b['count'] - $a['count'];
    });

    curl_close($ch);

    $result = ['data' => $filteredData];
    return json_encode($result, JSON_UNESCAPED_UNICODE);
}


    // 获取统计数据
    $monthData = fetchUmamiData($apiBaseUrl, $websiteId, $startTimestampLastMonth, $currentTimestamp, $token, $logFile);

    // 输出 JSON 字符串
    echo $monthData;
?>    
