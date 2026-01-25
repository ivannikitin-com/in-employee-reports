<?php
/**
 * PHPUnit bootstrap file
 * Загрузка WordPress тестовой среды
 */

// Определяем путь к WordPress тестовой среде
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Проверка существования тестовой среды
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Не удалось найти WordPress test suite по пути: $_tests_dir\n";
	echo "Установите тестовую среду WordPress с помощью:\n";
	echo "bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
	exit( 1 );
}

// Загружаем функции тестовой среды WordPress
require_once $_tests_dir . '/includes/functions.php';

/**
 * Функция загрузки плагина
 */
function _manually_load_plugin() {
	// Путь к главному файлу плагина
	require dirname( __DIR__ ) . '/source/in-employee-reports.php';
}

// Загружаем плагин перед запуском тестов
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Загружаем WordPress тестовую среду
require $_tests_dir . '/includes/bootstrap.php';

// Дополнительная инициализация для тестов
// Активируем плагин
activate_plugin( 'in-employee-reports/source/in-employee-reports.php' );
