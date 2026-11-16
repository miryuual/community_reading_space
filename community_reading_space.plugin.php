<?php
/**
 * Plugin Name: Community Reading Space
 * Plugin URI: https://slims.web.id/
 * Description: Ruang diskusi koleksi yang terintegrasi dengan OPAC dan administrasi SLiMS.
 * Version: 1.2.0
 * Author: Community Reading Space
 * Author URI: https://slims.web.id/
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

$plugin = \SLiMS\Plugins::getInstance();

/*
 * Menu sisi OPAC/pemustaka.
 * Halaman juga dapat dibuka langsung melalui:
 * {SWB}/index.php?p=community_reading_space
 */
$plugin->registerMenu(
    'opac',
    'Community Reading Space',
    __DIR__ . '/index.php'
);

/*
 * Menu pengelolaan pada modul System di administrasi SLiMS.
 */
$plugin->registerMenu(
    'system',
    'Community Reading Space',
    __DIR__ . '/admin/index.php'
);
