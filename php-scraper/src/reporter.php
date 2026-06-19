<?php
require_once 'config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$config = require 'config.php';
$db = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4", $config['db']['user'], $config['db']['pass']);

// Сбор ошибок
$errors = [];
$stmt = $db->query("SELECT * FROM measure_errors");
$errors['measure_errors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT measure_id, discrepancies_json, summary FROM comparison_results WHERE has_discrepancies = 1");
$errors['discrepancies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT measure_id, document_name, download_error FROM documents WHERE download_error IS NOT NULL");
$errors['document_errors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Формируем рекомендации
$recommendations = [];
foreach ($errors['measure_errors'] as $e) {
    if ($e['error_type'] === 'no_npa_section') {
        $recommendations[] = "Для меры {$e['measure_id']} отсутствует раздел НПА. Проверьте страницу.";
    }
    if ($e['error_type'] === 'no_valid_documents') {
        $recommendations[] = "Для меры {$e['measure_id']} все ссылки из раздела НПА не ведут к документам. Проверьте ссылки.";
    }
}
foreach ($errors['discrepancies'] as $d) {
    $disc = json_decode($d['discrepancies_json'], true);
    foreach ($disc as $item) {
        $recommendations[] = "Мера {$d['measure_id']}: {$item['description']} (фрагмент: {$item['fragment']})";
    }
}

$report = [
    'generated_at' => date('Y-m-d H:i:s'),
    'errors' => $errors,
    'recommendations' => $recommendations,
];

// Сохраняем JSON
$filename = "report_" . date('Ymd_His') . ".json";
$filepath = $config['reports_path'] . '/' . $filename;
file_put_contents($filepath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "JSON-отчёт сохранён: {$filepath}\n";

// Сохраняем Excel
function generateExcelReport($reportData, $filepath) {
    $spreadsheet = new Spreadsheet();
    
    // Лист 1: Ошибки мер
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('Ошибки мер');
    $sheet1->setCellValue('A1', 'ID меры');
    $sheet1->setCellValue('B1', 'Тип ошибки');
    $sheet1->setCellValue('C1', 'Описание');
    $sheet1->setCellValue('D1', 'Дата');
    $row = 2;
    foreach ($reportData['errors']['measure_errors'] as $e) {
        $sheet1->setCellValue('A' . $row, $e['measure_id']);
        $sheet1->setCellValue('B' . $row, $e['error_type']);
        $sheet1->setCellValue('C' . $row, $e['error_text']);
        $sheet1->setCellValue('D' . $row, $e['created_at']);
        $row++;
    }
    foreach (range('A', 'D') as $col) $sheet1->getColumnDimension($col)->setAutoSize(true);
    
    // Лист 2: Расхождения
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Расхождения');
    $sheet2->setCellValue('A1', 'ID меры');
    $sheet2->setCellValue('B1', 'Тип расхождения');
    $sheet2->setCellValue('C1', 'Фрагмент');
    $sheet2->setCellValue('D1', 'Описание');
    $sheet2->setCellValue('E1', 'Сводка');
    $row = 2;
    foreach ($reportData['errors']['discrepancies'] as $d) {
        $discs = json_decode($d['discrepancies_json'], true);
        foreach ($discs as $disc) {
            $sheet2->setCellValue('A' . $row, $d['measure_id']);
            $sheet2->setCellValue('B' . $row, $disc['type'] ?? '');
            $sheet2->setCellValue('C' . $row, $disc['fragment'] ?? '');
            $sheet2->setCellValue('D' . $row, $disc['description'] ?? '');
            $sheet2->setCellValue('E' . $row, $d['summary'] ?? '');
            $row++;
        }
    }
    foreach (range('A', 'E') as $col) $sheet2->getColumnDimension($col)->setAutoSize(true);
    
    // Лист 3: Ошибки документов
    $sheet3 = $spreadsheet->createSheet();
    $sheet3->setTitle('Ошибки документов');
    $sheet3->setCellValue('A1', 'ID меры');
    $sheet3->setCellValue('B1', 'Название документа');
    $sheet3->setCellValue('C1', 'Ошибка');
    $row = 2;
    foreach ($reportData['errors']['document_errors'] as $e) {
        $sheet3->setCellValue('A' . $row, $e['measure_id']);
        $sheet3->setCellValue('B' . $row, $e['document_name']);
        $sheet3->setCellValue('C' . $row, $e['download_error']);
        $row++;
    }
    foreach (range('A', 'C') as $col) $sheet3->getColumnDimension($col)->setAutoSize(true);
    
    // Лист 4: Рекомендации
    $sheet4 = $spreadsheet->createSheet();
    $sheet4->setTitle('Рекомендации');
    $sheet4->setCellValue('A1', '№');
    $sheet4->setCellValue('B1', 'Рекомендация');
    $row = 2;
    foreach ($reportData['recommendations'] as $idx => $rec) {
        $sheet4->setCellValue('A' . $row, $idx + 1);
        $sheet4->setCellValue('B' . $row, $rec);
        $row++;
    }
    foreach (range('A', 'B') as $col) $sheet4->getColumnDimension($col)->setAutoSize(true);
    
    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);
}

$excelFile = $config['reports_path'] . '/report_' . date('Ymd_His') . '.xlsx';
generateExcelReport($report, $excelFile);
echo "Excel-отчёт сохранён: {$excelFile}\n";