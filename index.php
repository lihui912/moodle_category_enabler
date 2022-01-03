<?php

use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/vendor/autoload.php';

putenv('WEBDRIVER_CHROME_DRIVER=' . __DIR__ . '/chromedriver.exe');

$loginUrl = 'https://www.example.com/moodle/login/index.php';
$logoutUrl = 'https://www.example.com/moodle/login/logout.php';
$manageCourseUrl = 'https://www.example.com/moodle/course/management.php';
$enableLinkTemplate = 'https://www.example.com/moodle/course/management.php?categoryid=%d&sesskey=%s&action=showcategory';

$username = '';
$password = '';
$screenShotPath ='';

$mailerHost = 'smtp.example.com';
$mailerPort = 587;
$mailerAuth = false;
$mailerFrom = '';
$mailerReceiver = '';

// The Xpath of the specific category to enable.
$targetMenubarXpath = '//*[@id="action-menu-11-menubar"]';

$mailer = getMailer();

try {
    $chromeOptions = new ChromeOptions();
    $chromeOptions->addArguments(['--headless']);
    $chromeOptions->addArguments(['--disable-gpu']);
    $chromeOptions->addArguments(['--window-size=1220,1080']);

    $chromeCapabilities = DesiredCapabilities::chrome();
    $chromeCapabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);

    $driver = ChromeDriver::start($chromeCapabilities);

    $driver->get($loginUrl);

    $loginBtn = $driver->wait(10, 500)->until(
        WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('loginbtn'))
    );

    $driver->wait(10, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('username'))
    )->sendKeys($username);

    $driver->wait(10, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('password'))
    )->sendKeys($password);

    $loginBtn->click();

    $bodyTag = $driver->wait(10, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::tagName('body'))
    );

    $bodyClassId = $bodyTag->getAttribute('id');

    if('page-my-index' !== $bodyClassId) {
        // login failed
        throw new Exception('Login failed');
    }

    enableCategory($driver, $targetMenubarXpath);

    $enabled = checkEnabled($driver, $targetMenubarXpath);

    // send status mail
    $now = date('Y-m-d H:i:s');
    $bodyTemplate = 'Time: %s' . PHP_EOL . 'Message: %s';
    if(true === $enabled) {
        $mailer->Subject = 'Successful: Category Enabler: Category is enabled';
        $mailer->Body = sprintf($bodyTemplate, $now, 'Category Enabler: Category is enabled');
    } else {
        $mailer->Subject = 'Failed: Category Enabler: Category is not enabled';
        $mailer->Body = sprintf($bodyTemplate, $now, 'Category Enabler: Category is not enabled');
    }
    $mailer->addAttachment($screenShotPath);

    // logout moodle
    logout($driver);

    // exit chrome
    quit($driver);

} catch (Exception $e) {
    quit($driver);

    // dump stack trace
    $trace = $e->getTraceAsString();
    $messageTemplate = 'Message: %s' . PHP_EOL . 'File: %s' . PHP_EOL . 'Line: %s' . PHP_EOL . 'Trace: %s';
    $message = sprintf($messageTemplate, $e->getMessage(), $e->getFile(), $e->getLine(), $trace);

    $mailer->Subject = 'Failed: Category Enabler: The Category is not enabled';
    $mailer->Body = $message;
}

// send mail
try {
    $mailer->send();
} catch (Exception $e) {
    // dump stack trace to file
    $now = date('Y-m-d H:i:s');
    $trace = $e->getTraceAsString();
    $messageTemplate = 'Time: %s' . PHP_EOL . 'Message: %s' . PHP_EOL . 'File: %s' . PHP_EOL . 'Line: %s' . PHP_EOL . 'Trace: %s' . PHP_EOL;
    $message = sprintf($messageTemplate, $now, $e->getMessage(), $e->getFile(), $e->getLine(), $trace);

    // write message to file
    file_put_contents(__DIR__ . '/mailer-error.txt', $message, FILE_APPEND);
}


function enableCategory(ChromeDriver $driver, string $menubarXpath)
{
    $xpath = './a[1]';
    global $manageCourseUrl, $enableLinkTemplate;
    $driver->get($manageCourseUrl);

    // make sure page is ready
    $targetElement = $driver->wait(10, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath($menubarXpath))
    );

    $enableLink = $targetElement->findElement(WebDriverBy::xpath($xpath))->getAttribute('href');

    $urlElements = parse_url($enableLink);
    parse_str($urlElements['query'], $query);

    $enableLInk = sprintf($enableLinkTemplate, $query['categoryid'], $query['sesskey']);

    $driver->get($enableLInk);

    $driver->wait(10, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath($menubarXpath))
    );
}

function checkEnabled(ChromeDriver $driver, string $menubarXpath): bool
{
    global $screenShotPath;
    $menubar = $driver->findElement(WebDriverBy::xpath($menubarXpath));
    $hideLink = $menubar->findElement(WebDriverBy::xpath('./a[1]'));
//    $showLink = $menubar->findElement(WebDriverBy::xpath('./a[2]'));

    $now = date('Ymd_His');
    $screenShotPath = __DIR__ . '/screenshot.' . $now . '.png';
    $driver->takeScreenshot($screenShotPath);

    return $hideLink->isDisplayed();
}

function quit(ChromeDriver $driver)
{
    $driver->close();
    $driver->quit();
}

function logout(ChromeDriver $driver)
{
    global $logoutUrl;
    $driver->get($logoutUrl);

    $continueBtn = $driver->wait(10, 100)->until(
        WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//button[text()="Continue"]'))
    );

    $continueBtn->click();
}

function getMailer(): PHPMailer
{
    global $mailerHost, $mailerPort, $mailerAuth, $mailerFrom, $mailerReceiver;
    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $mailerHost;
    $mailer->SMTPAuth = $mailerAuth;
    $mailer->SMTPSecure = 'tls';
    $mailer->Port = $mailerPort;
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom($mailerFrom, 'Moodle Category Enabler');
    $mailer->isHTML(false);


    $mailer->addAddress($mailerReceiver);
    $mailer->Subject = 'Category Enabler status';

    return $mailer;
}
