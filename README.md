Introduction
---
This is a CLI tool to automate the process of enabling a category in the Course and Category Management page in Moodle admin page.

This tool uses the Selenium WebDriver to automate the process, sending an email to the specific receiver to notify the status of the process.


Installation
---
Download the ChromeDriver from [here](https://sites.google.com/chromium.org/driver/). The ChromeDriver version should match the version of the installed Google Chrome browser, download the latest stable release will simply do the job. Extract the `ChromeDriver.exe` to the root directory of this project.

Usage
---
```
php -f index.php
```

This tool is tested on Windows 10, PHP 8.1,  Google Chrome 96.0.4664.110, with Moodle 3.2.




