<?php
/*
Plugin Name: Отчеты сотрудников
Plugin URI:  https://github.com/ivannikitin-com/in-employee-reports
Description: Отчеты сотрудников компании
Version:     3.0
Author:      IvanNikitin.com
Author URI:  https://ivannikitin.com/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: in-employee-reports
Domain Path: /lang
Namespace:	INER
*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/* Composer Autoloader */
require plugin_dir_path( __FILE__ ) . '../vendor/autoload.php';

/* Глобальные константы плагина */
define( 'INER', 'in-employee-reports' );            // Text Domain
define( 'INER_FOLDER', plugin_dir_path( __FILE__ ) );      // Plugin folder
define( 'INER_URL', plugin_dir_url( __FILE__ ) );       // Plugin URL

/* Активация плагина */
register_activation_hook( __FILE__, 'iner_activation' );
function iner_activation() {
	// Инициализация ролей пользователей
	InEmployeeReports\Permissions_Manager::initRoles();

	// Миграция старых ролей
	InEmployeeReports\Permissions_Manager::migrate_old_roles();
}

/* Инициализация плагина */
add_action( 'plugins_loaded', 'iner_init' );
function iner_init() {
	// Локализация плагина
	load_plugin_textdomain( INER, false, basename( dirname( __FILE__ ) ) . '/lang' );

	// Загрузка плагина
	new InEmployeeReports\Plugin( INER_FOLDER, INER_URL );
}

/* Подключение хука map_meta_cap для управления правами доступа */
add_action( 'init', 'iner_setup_capabilities' );
function iner_setup_capabilities() {
	// Подключаем map_meta_cap для проверки прав доступа
	add_filter(
		'map_meta_cap',
		array( 'InEmployeeReports\\Permissions_Manager', 'map_meta_cap' ),
		10,
		4
	);
}
