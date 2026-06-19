<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once 'config.php';
require_once 'llm_comparator.php';

$config = require 'config.php';
$db = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4", $config['db']['user'], $config['db']['pass']);
$comparator = new LLMComparator();

$measures = $db->query("SELECT measure_id, title, purpose, requirements, required_docs, procedure_steps FROM measures")->fetchAll(PDO::FETCH_ASSOC);

foreach ($measures as $m) {
    $cardText = implode("\n\n", array_filter([
        "Назначение: " . ($m['purpose'] ?? ''),
        "Требования: " . ($m['requirements'] ?? ''),
        "Необходимые документы: " . ($m['required_docs'] ?? ''),
        "Порядок получения: " . ($m['procedure_steps'] ?? ''),
    ]));
    
    $stmt = $db->prepare("SELECT file_path FROM documents WHERE measure_id = ? AND download_error IS NULL");
    $stmt->execute([$m['measure_id']]);
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $docText = '';
    foreach ($docs as $d) {
        $docText .= shell_exec("pdftotext '{$d['file_path']}' - 2>/dev/null");
    }
    
    if (empty($cardText) || empty($docText)) {
        echo "Мера {$m['measure_id']}: недостаточно данных\n";
        continue;
    }
    
    echo "Мера {$m['measure_id']}: отправка в YandexGPT...\n";
    $result = $comparator->compare($cardText, $docText, $m['title']);
    
    $stmt = $db->prepare("INSERT INTO comparison_results (measure_id, has_discrepancies, discrepancies_json, summary) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $m['measure_id'],
        !empty($result['discrepancies']) ? 1 : 0,
        json_encode($result['discrepancies'] ?? [], JSON_UNESCAPED_UNICODE),
        $result['summary'] ?? ''
    ]);
    sleep(2);
}