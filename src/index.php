<?php
/**
 * Jaddle Web
 * A Web Viewer for Jaddle
 *
 * @author Anael Ollier <nanawel@gmail.com>
 * @since 2011-04-08
 * @version 0.5.0
 * @license See LICENSE.txt
 */
ini_set('error_reporting', E_ALL);
ini_set('display_error', 1);

include __DIR__ . '/config.inc.php';
include JADDLE_LIBS_DIR . '/JaddleWeb.class.php';

$jaddle = new JaddleWeb(JADDLE_DB_CONNECTIONSTRING, JADDLE_DB_USER, JADDLE_DB_PASSWORD, defined('JADDLE_DEV') && constant('JADDLE_DEV'));
$jaddle->handleRequest();