<?php
/*
Plugin Name: Отчеты сотрудников
Plugin URI:  https://github.com/ivannikitin-com/in-employee-reports
Description: Отчеты сотрудников компании
Version:     2.0
Author:      IvanNikitin.com
Author URI:  https://ivannikitin.com/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: in-employee-reports
Domain Path: /lang
Namespace:	INER
*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/* Глобальные константы плагина */
define( 'INER', 		'in-employee-reports' );			// Text Domain
define( 'INER_FOLDER', 	plugin_dir_path( __FILE__ ) );		// Plugin folder
define( 'INER_URL', 	plugin_dir_url( __FILE__ ) );		// Plugin URL

/* Классы плагина */
require( INER_FOLDER . 'classes/base.php' );
require( INER_FOLDER . 'classes/plugin.php' );
require( INER_FOLDER . 'classes/rolemanager.php' );
require( INER_FOLDER . 'classes/report.php' );

/* Активация плагина */
register_activation_hook( __FILE__, 'iner_activation' );
function iner_activation()
{
	// Инициализация ролей пользователей
	INER\RoleManager::initRoles();
}

/* Инициализация плагина */
add_action( 'plugins_loaded', 'iner_init' );
function iner_init()
{
	// Локализация плагина
	load_plugin_textdomain( INER, false, basename( dirname( __FILE__ ) ) . '/lang' );	
	
	// Загрузка плагина
	new INER\Plugin( INER_FOLDER, INER_URL );
}
