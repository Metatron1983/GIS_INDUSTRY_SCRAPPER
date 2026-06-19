<?php
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

function getSeleniumDriver() {
    return RemoteWebDriver::create('http://selenium:4444', DesiredCapabilities::chrome());
}

function getPageHtmlWithSelenium($url, $waitSelector = null, $timeout = 10) {
    try {
        $driver = getSeleniumDriver();
        $driver->get($url);
        if ($waitSelector) {
            $driver->wait($timeout)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector($waitSelector))
            );
        }
        usleep(500000);
        $html = $driver->getPageSource();
        $driver->quit();
        return $html;
    } catch (Exception $e) {
        error_log("Selenium error: " . $e->getMessage());
        return null;
    }
}

function fetchHtml($url, $useSelenium = false, $waitSelector = null) {
    if ($useSelenium) {
        return getPageHtmlWithSelenium($url, $waitSelector);
    }
    $client = new \GuzzleHttp\Client(['timeout' => 30, 'headers' => ['User-Agent' => 'Mozilla/5.0']]);
    try {
        return (string) $client->get($url)->getBody();
    } catch (Exception $e) {
        error_log("Guzzle error: " . $e->getMessage());
        return null;
    }
}