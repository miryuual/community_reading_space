<?php
/**
 * Plugin Name: Community Reading Space
 * Plugin URI: https://github.com/miryuual/community_reading_space/releases
 * Description: Ruang diskusi koleksi yang terintegrasi dengan OPAC dan administrasi SLiMS.
 * Version: 1.2.2
 * Author: Community Reading Space
 * Author URI: https://github.com/miryuual/community_reading_space/releases
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

$plugin = \SLiMS\Plugins::getInstance();

/*
 * Registrasi otomatis menu OPAC melalui API plugin resmi SLiMS.
 * Menu akan muncul ketika plugin diaktifkan; tidak perlu mengedit file tema.
 * Halaman juga dapat dibuka langsung melalui:
 * {SWB}/index.php?p=community_reading_space
 */
// Daftarkan path OPAC: index.php?p=community_reading_space
$plugin->registerMenu(
    'opac',
    'community_reading_space',
    __DIR__ . '/index.php'
);

// Tautan navigasi global OPAC. Pengaturan aktif/nonaktif dikendalikan dari dashboard CRS.
require_once __DIR__ . '/includes/functions.php';
try {
    $crsNavEnabled = crs_setting('opac_nav_enabled', '1') === '1';
} catch (Throwable $e) {
    // Bila tabel pengaturan belum dibuat, tampilkan menu secara default.
    $crsNavEnabled = true;
}

if ($crsNavEnabled) {
    $plugin->register('after_content_load', function ($opac) {
        $crsUrl = rtrim(SWB, '/') . '/index.php?p=community_reading_space';
        $crsUrlJson = json_encode($crsUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $script = '<script>
        (function () {
            var targetUrl = ' . $crsUrlJson . ';
            function findLanguageItem(nav) {
                var candidates = nav.querySelectorAll(
                    "li, a, button, .dropdown, [class*=language], [class*=lang], [id*=language], [id*=lang]"
                );
                for (var i = 0; i < candidates.length; i++) {
                    var el = candidates[i];
                    var signature = ((el.className || "") + " " + (el.id || "") + " " +
                        (el.getAttribute("aria-label") || "") + " " +
                        (el.getAttribute("title") || "") + " " +
                        (el.innerText || el.textContent || "")).toLowerCase();
                    if (signature.indexOf("language") !== -1 ||
                        signature.indexOf("bahasa") !== -1 ||
                        signature.indexOf("lang") !== -1 ||
                        el.querySelector("img[src*=flag], img[alt*=flag]")) {
                        return el.closest("li") || el;
                    }
                }
                return null;
            }

            function insertLink() {
                if (document.querySelector("[data-crs-global-nav]")) return;
                var navs = document.querySelectorAll(
                    "header nav, .navbar nav, nav, .navbar-nav, #mainMenu, #main-menu, .main-menu, .menu"
                );
                var nav = null;
                for (var i = 0; i < navs.length; i++) {
                    if (navs[i].querySelector("a")) { nav = navs[i]; break; }
                }
                if (!nav) return;

                var list = nav.matches("ul") ? nav : nav.querySelector("ul");
                var link = document.createElement("a");
                link.href = targetUrl;
                link.textContent = "Community Reading Space";
                link.setAttribute("data-crs-global-nav", "1");
                link.setAttribute("aria-label", "Community Reading Space");
                link.className = "crs-global-nav-link";

                var languageItem = findLanguageItem(list || nav);
                if (list) {
                    var item = document.createElement("li");
                    item.className = "nav-item crs-global-nav-item";
                    link.classList.add("nav-link");
                    item.appendChild(link);
                    if (languageItem && languageItem.parentNode === list) {
                        list.insertBefore(item, languageItem);
                    } else {
                        list.appendChild(item);
                    }
                } else {
                    var parent = languageItem && languageItem.parentNode ? languageItem.parentNode : nav;
                    if (languageItem && parent === nav) parent.insertBefore(link, languageItem);
                    else nav.appendChild(link);
                }
            }

            function boot() {
                insertLink();
                var observer = new MutationObserver(function () { insertLink(); });
                observer.observe(document.documentElement, {childList: true, subtree: true});
                window.setTimeout(function () { observer.disconnect(); }, 8000);
            }
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", boot);
            } else {
                boot();
            }
        })();
        </script>';
        $opac->js = (string)($opac->js ?? '') . $script;
    });
}

/*
 * Menu pengelolaan pada modul System di administrasi SLiMS.
 */
$plugin->registerMenu(
    'system',
    'Community Reading Space',
    __DIR__ . '/admin/index.php'
);
