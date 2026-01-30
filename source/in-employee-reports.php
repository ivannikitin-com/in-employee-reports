<?php
/*
Plugin Name: Отчеты сотрудников
Plugin URI:  https://github.com/ivannikitin-com/in-employee-reports
Description: Отчеты сотрудников компании
Version:     3.0.2
Author:      IvanNikitin.com
Author URI:  https://ivannikitin.com/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: in-employee-reports
Domain Path: /lang
Namespace:	INER
*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/* Простой автозагрузчик классов плагина */
spl_autoload_register( function ( $class ) {
	// Загружаем только классы из нашего пространства имен
	if ( strpos( $class, 'InEmployeeReports\\' ) !== 0 ) {
		return;
	}

	// Убираем префикс пространства имен
	$class_name = str_replace( 'InEmployeeReports\\', '', $class );

	// Преобразуем имя класса в имя файла:
	// 1. Разделяем по подчеркиваниям на части
	// 2. Для каждой части преобразуем CamelCase в kebab-case
	// 3. Объединяем через дефисы и приводим к нижнему регистру
	$parts = explode( '_', $class_name );
	$file_parts = array();
	foreach ( $parts as $part ) {
		// Преобразуем CamelCase в kebab-case
		$kebab = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $part ) );
		$file_parts[] = $kebab;
	}
	$file_name = implode( '-', $file_parts );

	// Путь к файлу класса
	$file_path = plugin_dir_path( __FILE__ ) . 'classes/' . $file_name . '.php';

	// Загружаем файл, если он существует
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
} );

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
	// Убеждаемся, что права администратору установлены
	InEmployeeReports\Permissions_Manager::ensureAdminCapabilities();

	// Подключаем map_meta_cap для проверки прав доступа
	add_filter(
		'map_meta_cap',
		array( 'InEmployeeReports\\Permissions_Manager', 'map_meta_cap' ),
		10,
		4
	);

	// Подавляем предупреждения WordPress о неправильном вызове map_meta_cap
	// когда WordPress вызывает map_meta_cap без аргументов для проверки общих capabilities
	// Используем высокий приоритет, чтобы перехватить предупреждения до их вывода
	add_filter( 'doing_it_wrong_trigger_error', 'iner_suppress_map_meta_cap_warnings', 999, 3 );
}

/**
 * Подавляет предупреждения WordPress о неправильном вызове map_meta_cap
 * когда WordPress вызывает map_meta_cap без аргументов для наших CPT
 *
 * @param bool   $trigger   Следует ли генерировать предупреждение
 * @param string $function  Имя функции
 * @param string $message   Сообщение об ошибке
 * @return bool
 */
function iner_suppress_map_meta_cap_warnings( $trigger, $function, $message ) {
	// Подавляем предупреждения только для map_meta_cap с edit_post/read_post
	if ( 'map_meta_cap' === $function && ( false !== strpos( $message, 'edit_post' ) || false !== strpos( $message, 'read_post' ) ) ) {
		// Подавляем предупреждение, так как мы правильно обрабатываем вызовы без аргументов
		// в нашей функции map_meta_cap, возвращая исходные caps без изменений
		return false;
	}
	return $trigger;
}
