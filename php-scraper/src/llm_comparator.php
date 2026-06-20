<?php
use Tigusigalpa\YandexGptPhp\YandexGpt;
use Tigusigalpa\YandexGptPhp\Completion\Completion;

class LLMComparator {
    private $client;
    private $modelUri;
    
    public function __construct() {
        $config = require 'config.php';
        $this->client = new YandexGpt(
            apiKey: $config['yandex']['api_key'],
            folderId: $config['yandex']['folder_id']
        );
        $this->modelUri = "gpt://" . $config['yandex']['folder_id'] . "/" . $config['yandex']['model'] . "/latest";
    }
    
    public function compare($cardText, $docText, $title = '') {
        if (empty(trim($cardText))) return ['error' => 'Пустая карточка'];
        if (empty(trim($docText))) return ['error' => 'Пустой документ'];
        
        $cardText = mb_substr($cardText, 0, 2500);
        $docText = mb_substr($docText, 0, 4000);
        
        $prompt = "Сравни тексты и найди расхождения:\n\n";
        $prompt .= "### Карточка меры поддержки (назначение, требования, документы, порядок):\n{$cardText}\n\n";
        $prompt .= "### Текст нормативного документа:\n{$docText}\n\n";
        $prompt .= "Ответь строго в формате JSON:\n";
        $prompt .= "{\"discrepancies\":[{\"type\":\"missing_in_document\"|\"missing_in_card\"|\"contradiction\",\"fragment\":\"...\",\"description\":\"...\"}],\"summary\":\"...\"}";
        
        $completion = (new Completion())
            ->setModelUri($this->modelUri)
            ->addText([
                ['role' => Completion::SYSTEM, 'text' => 'Ты — эксперт по анализу нормативных документов. Отвечай только JSON.'],
                ['role' => Completion::USER, 'text' => $prompt]
            ])
            ->setTemperature(0.1)
            ->setMaxTokens(4000);
        
        $result = json_decode($this->client->request($completion), true);
        $text = $result['result']['alternatives'][0]['message']['text'] ?? '';
        
        preg_match('/\{[\s\S]*\}/', $text, $matches);
        if ($matches) {
            return json_decode($matches[0], true);
        }
        
        return ['raw' => $text, 'discrepancies' => [], 'summary' => 'Ошибка парсинга ответа'];
    }
}