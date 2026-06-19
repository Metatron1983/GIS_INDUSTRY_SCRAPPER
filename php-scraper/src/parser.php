<?php
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

function parseMeasureList($html, $baseUrl) {
    $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
    $items = [];
    $crawler->filter('a[href*="/nmp/measure/"]')->each(function ($node) use (&$items, $baseUrl) {
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
    
    $data = [
        'purpose' => '',
        'requirements' => '',
        'required_docs' => '',
        'procedure_steps' => '',
        'npa_links' => [],
        'npa_section_exists' => false,
    ];

    // Назначение (основная страница)
    try {
        $driver->get($baseMeasureUrl);
        $driver->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.measure-content, .nmp-measure'))
        );
        $purposeEl = $driver->findElement(WebDriverBy::cssSelector('.purpose-block, .measure-purpose, h2:contains("Назначение") + div'));
        $data['purpose'] = $purposeEl->getText();
    } catch (Exception $e) {
        // не найдено
    }

    // Требования
    $requirementUrl = "{$baseUrl}/nmp/measure/{$measureId}/requirement";
    try {
        $driver->get($requirementUrl);
        $driver->wait(5)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.requirement-content, .tab-content'))
        );
        $data['requirements'] = $driver->findElement(WebDriverBy::cssSelector('.requirement-content, .tab-content'))->getText();
    } catch (Exception $e) {}

    // Порядок получения (этапы)
    $stageUrl = "{$baseUrl}/nmp/measure/{$measureId}/stage";
    try {
        $driver->get($stageUrl);
        $driver->wait(5)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.stage-content, .tab-content'))
        );
        $data['procedure_steps'] = $driver->findElement(WebDriverBy::cssSelector('.stage-content, .tab-content'))->getText();
    } catch (Exception $e) {}

    // Необходимые документы
    $documentUrl = "{$baseUrl}/nmp/measure/{$measureId}/document";
    try {
        $driver->get($documentUrl);
        $driver->wait(5)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.document-content, .tab-content'))
        );
        $data['required_docs'] = $driver->findElement(WebDriverBy::cssSelector('.document-content, .tab-content'))->getText();
    } catch (Exception $e) {}

    // НПА и другие документы (основная страница)
    try {
        $driver->get($baseMeasureUrl);
        $driver->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.measure-content, .nmp-measure'))
        );
        $npaSection = $driver->findElement(WebDriverBy::cssSelector('.npa-section, .documents-section, h2:contains("НПА")'));
        $data['npa_section_exists'] = true;
        $links = $npaSection->findElements(WebDriverBy::cssSelector('a[href]'));
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $text = $link->getText();
            if ($href) {
                $data['npa_links'][] = [
                    'url' => $href,
                    'text' => $text ?: basename($href),
                ];
            }
        }
    } catch (Exception $e) {
        $data['npa_section_exists'] = false;
        $data['npa_links'] = [];
    }

    return $data;
}