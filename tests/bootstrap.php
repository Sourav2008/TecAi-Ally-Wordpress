<?php
/**
 * PHPUnit bootstrap for TecAI Ally plugin.
 */

$plugin_root = dirname( __DIR__ );

$autoload = $plugin_root . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
}

$plugin_main = $plugin_root . '/tecai-ally.php';
if ( file_exists( $plugin_main ) ) {
    require_once $plugin_main;
}
