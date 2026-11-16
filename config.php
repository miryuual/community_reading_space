<?php
/**
 * Konfigurasi Community Reading Space.
 * File hanya boleh dimuat melalui bootstrap SLiMS.
 */
if (!defined('INDEX_AUTH')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

if (!defined('CRS_APP_NAME')) {
    define('CRS_APP_NAME', 'Community Reading Space');
}
if (!defined('CRS_PLUGIN_DIR')) {
    define('CRS_PLUGIN_DIR', __DIR__);
}
if (!defined('CRS_PLUGIN_URL')) {
    define('CRS_PLUGIN_URL', rtrim(SWB, '/') . '/plugins/community_reading_space/');
}
if (!defined('CRS_OPAC_URL')) {
    define('CRS_OPAC_URL', rtrim(SWB, '/') . '/index.php?p=community_reading_space');
}
