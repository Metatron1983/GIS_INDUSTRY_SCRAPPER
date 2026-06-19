<?php
function checkDocumentLink($client, $url) {
    try {
        $response = $client->head($url, ['allow_redirects' => true, 'timeout' => 10]);
        if ($response->getStatusCode() !== 200) {
            return ['valid' => false, 'error' => "HTTP {$response->getStatusCode()}"];
        }
        $contentType = $response->getHeaderLine('Content-Type');
        $allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/rtf'];
        $isDoc = false;
        foreach ($allowed as $t) {
            if (strpos($contentType, $t) !== false) { $isDoc = true; break; }
        }
        if (!$isDoc) {
            return ['valid' => false, 'error' => "Не документ (Content-Type: {$contentType})"];
        }
        return ['valid' => true, 'contentType' => $contentType];
    } catch (Exception $e) {
        return ['valid' => false, 'error' => $e->getMessage()];
    }
}

function downloadDocuments($client, $config, $measureId, $documents) {
    $docPath = $config['documents_path'] . '/' . $measureId;
    if (!is_dir($docPath)) mkdir($docPath, 0777, true);
    
    $db = new PDO(
        "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4",
        $config['db']['user'],
        $config['db']['pass']
    );
    
    $validCount = 0;
    foreach ($documents as $doc) {
        $url = $doc['url'];
        if (strpos($url, 'http') !== 0) {
            $url = $config['base_url'] . $url;
        }
        $filename = preg_replace('/[^\w\s.-]/u', '_', $doc['text']) . '.pdf';
        $filepath = $docPath . '/' . $filename;
        
        $check = checkDocumentLink($client, $url);
        if (!$check['valid']) {
            $stmt = $db->prepare("INSERT INTO documents (measure_id, document_name, document_type, download_error) VALUES (?, ?, ?, ?)");
            $stmt->execute([$measureId, $doc['text'], 'pdf', "Ссылка: {$url} | Ошибка: {$check['error']}"]);
            continue;
        }
        
        if (file_exists($filepath)) continue;
        
        try {
            $client->get($url, ['sink' => $filepath, 'timeout' => 60]);
            $hash = hash_file('sha256', $filepath);
            $stmt = $db->prepare("INSERT INTO documents (measure_id, document_name, document_type, file_path, file_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$measureId, $doc['text'], 'pdf', $filepath, $hash]);
            $validCount++;
        } catch (Exception $e) {
            $stmt = $db->prepare("INSERT INTO documents (measure_id, document_name, document_type, download_error) VALUES (?, ?, ?, ?)");
            $stmt->execute([$measureId, $doc['text'], 'pdf', "Ссылка: {$url} | Ошибка скачивания: " . $e->getMessage()]);
        }
    }
    
    if (!empty($documents) && $validCount === 0) {
        $stmt = $db->prepare("INSERT INTO measure_errors (measure_id, error_type, error_text) VALUES (?, 'no_valid_documents', ?)");
        $stmt->execute([$measureId, 'Ни одна ссылка из раздела НПА не ведёт к документу']);
    }
}