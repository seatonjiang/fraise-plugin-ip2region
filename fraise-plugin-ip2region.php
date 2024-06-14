<?php

/**
 * Plugin Name: Fraise - 位置服务工具包
 * Description: 使用位置服务所需的工具包，可离线定位 IP 地址所在位置。
 * Version: 2024.06.03
 * Plugin URI: https://github.com/seatonjiang/fraise-plugin-ip2region
 * Author: Seaton Jiang
 * Author URI: https://seatonjiang.com
 * License: MIT License
 * License URI: https://github.com/seatonjiang/fraise-plugin-ip2region/blob/main/LICENSE
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!has_action('fraise-plugin-ip2region')) {
    add_action('fraise-plugin-ip2region', function () {
        require_once plugin_dir_path(__FILE__) . '/vendor/ip2region.php';
    });
}
