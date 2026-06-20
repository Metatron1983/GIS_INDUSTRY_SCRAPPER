<?php
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

function parseMeasureList($html, $baseUrl) {
    $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
    $items = [];
    $crawler->filter('a[href*="/nmp/measure/"]')->each(function ($node) use (&$items) {
        $href = $node->attr('href');
        if ($href && strpos($href, '/nmp/measure/') !== false) {
            preg_match('/\/nmp\/measure\/(\d+)/', $href, $matches);
            $id = $matches[1] ?? null;
            if ($id) {
                $items[] = [
                    'id' => $id,
                    'title' => trim($node->text()) ?: "Мера {$id}",
                    'url' => $href,
                ];
            }
        }
    });
    $unique = [];
    $seen = [];
    foreach ($items as $item) {
        if (!in_array($item['id'], $seen)) {
            $seen[] = $item['id'];
            $unique[] = $item;
        }
    }
    return ['count' => count($unique), 'items' => $unique];
}

function parseMeasureDetailWithSelenium($driver, $measureId, $baseUrl) {
    $baseMeasureUrl = "{$baseUrl}/nmp/measure/{$measureId}";
    
    $driver->get($baseMeasureUrl);
    $driver->wait(10)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.measure-content, .nmp-measure'))
    );
    
    $data = [
        'purpose' => '',
        'requirements' => '',
        'required_docs' => '',
        'procedure_steps' => '',
        'npa_links' => [],
        'npa_section_exists' => false,
    ];
    
    // Назначение (ДОБАВЛЕН более точный селектор)
    try {
        $purposeEl = $driver->findElement(WebDriverBy::cssSelector('.purpose-block, .measure-purpose, h2:contains("Назначение") + div'));
        $data['purpose'] = $purposeEl->getText();
    } catch (Exception $e) {}
    
    // Требования (отдельный URL) - ДОБАВЛЕНО
    try {
        $driver->get("{$baseUrl}/nmp/measure/{$measureId}/requirement");
        $driver->wait(5)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.requirement-content, .tab-content')));
        $data['requirements'] = $driver->findElement(WebDriverBy::cssSelector('.requirement-content, .tab-content'))->getText();
    } catch (Exception $e) {}
    
    // Порядок получения (отдельный URL) - ДОБАВЛЕНО
    try {
        $driver->get("{$baseUrl}/nmp/measure/{$measureId}/stage");
        $driver->wait(5)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.stage-content, .tab-content')));
        $data['procedure_steps'] = $driver->findElement(WebDriverBy::cssSelector('.stage-content, .tab-content'))->getText();
    } catch (Exception $e) {}
    
    // Необходимые документы (отдельный URL) - ДОБАВЛЕНО
    try {
        $driver->get("{$baseUrl}/nmp/measure/{$measureId}/document");
        $driver->wait(5)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.document-content, .tab-content')));
        $data['required_docs'] = $driver->findElement(WebDriverBy::cssSelector('.document-content, .tab-content'))->getText();
    } catch (Exception $e) {}
    
    // Возврат на основную страницу для НПА
    $driver->get($baseMeasureUrl);
    $driver->wait(10)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.measure-content, .nmp-measure')));
    try {
        $npaSection = $driver->findElement(WebDriverBy::cssSelector('.npa-section, .documents-section, h2:contains("НПА")'));
        $data['npa_section_exists'] = true;
        $links = $npaSection->findElements(WebDriverBy::cssSelector('a[href]'));
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $text = $link->getText();
            if ($href) {
                $data['npa_links'][] = ['url' => $href, 'text' => $text ?: basename($href)];
            }
        }
    } catch (Exception $e) {
        $data['npa_section_exists'] = false;
        $data['npa_links'] = [];
    }
    
    return $data;
}