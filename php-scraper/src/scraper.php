<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once 'config.php';
require_once 'parser.php';
require_once 'downloader.php';
require_once 'selenium_helper.php';

$config = require 'config.php';
$testLimit = $config['test_limit'];   // ДОБАВЛЕНО

// Ротация User-Agent (ДОБАВЛЕНО)
$userAgents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
];

$userAgent = $userAgents[array_rand($userAgents)];   // ДОБАВЛЕНО
$client = new GuzzleHttp\Client([
    'timeout' => 30,
    'headers' => ['User-Agent' => $userAgent],
]);

$db = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4", $config['db']['user'], $config['db']['pass']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function logMsg($msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL; }

function getMeasureList($client, $config, $page) {
    $url = $config['navigator_url'] . "&page={$page}&per-page={$config['items_per_page']}";   // ИЗМЕНЕНО: используем NAVIGATOR_URL
    logMsg("Загрузка страницы {$page}");
    $html = fetchHtml($url, false);
    if (!$html || !strpos($html, 'measure-item')) {
        logMsg("Пробуем Selenium...");
        $html = fetchHtml($url, true, '.measure-item');
    }
    return $html ? parseMeasureList($html, $config['base_url']) : ['count' => 0, 'items' => []];
}

function saveMeasure($db, $data) {
    $sql = "INSERT INTO measures (
        measure_id, title, purpose, requirements, required_docs, procedure_steps, 
        npa_section_exists, url
    ) VALUES (
        :id, :title, :purpose, :requirements, :required_docs, :procedure_steps,
        :npa_section_exists, :url
    ) ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        purpose = VALUES(purpose),
        requirements = VALUES(requirements),
        required_docs = VALUES(required_docs),
        procedure_steps = VALUES(procedure_steps),
        npa_section_exists = VALUES(npa_section_exists)";
    $stmt = $db->prepare($sql);
    return $stmt->execute($data);
}

$driver = getSeleniumDriver();
$page = 1;
$total = 0;

while (true) {
    $list = getMeasureList($client, $config, $page);
    if (empty($list['items'])) break;
    
    foreach ($list['items'] as $item) {
        logMsg("Обработка меры {$item['id']}: {$item['title']}");
        $detail = parseMeasureDetailWithSelenium($driver, $item['id'], $config['base_url']);
        
        $data = [
            'id' => $item['id'],
            'title' => $item['title'],
            'url' => $item['url'],
            'purpose' => $detail['purpose'] ?? '',
            'requirements' => $detail['requirements'] ?? '',
            'required_docs' => $detail['required_docs'] ?? '',
            'procedure_steps' => $detail['procedure_steps'] ?? '',
            'npa_section_exists' => $detail['npa_section_exists'] ? 1 : 0,
        ];
        
        saveMeasure($db, $data);
        
        if (!$detail['npa_section_exists']) {
            $stmt = $db->prepare("INSERT INTO measure_errors (measure_id, error_type, error_text) VALUES (?, 'no_npa_section', ?)");
            $stmt->execute([$item['id'], 'Раздел "НПА и другие документы" отсутствует на странице']);
        }
        
        if (!empty($detail['npa_links'])) {
            downloadDocuments($client, $config, $item['id'], $detail['npa_links']);
        }
        
        $total++;
        if ($testLimit > 0 && $total >= $testLimit) {   // ДОБАВЛЕНО: остановка по лимиту
            logMsg("Достигнут лимит {$testLimit} мер, остановка.");
            break 2;
        }
        sleep($config['request_delay']);
    }
    $page++;
}

$driver->quit();
logMsg("Собрано мер: {$total}");