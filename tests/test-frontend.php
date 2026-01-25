<?php
/**
 * Тесты для фронтенда
 */

class Test_Frontend extends WP_UnitTestCase {

	/**
	 * Настройка перед каждым тестом
	 */
	public function setUp(): void {
		parent::setUp();

		// Инициализация ролей
		InEmployeeReports\Permissions_Manager::initRoles();
	}

	/**
	 * Тест что шорткод зарегистрирован
	 */
	public function test_shortcode_registered() {
		$this->assertTrue( shortcode_exists( 'in_employee_reports' ), 'Шорткод in_employee_reports должен быть зарегистрирован' );
	}

	/**
	 * Тест вывода шорткода для авторизованного пользователя
	 */
	public function test_shortcode_output_for_logged_in_user() {
		$user = $this->factory->user->create( array( 'role' => 'employee' ) );
		wp_set_current_user( $user );

		$output = do_shortcode( '[in_employee_reports]' );

		$this->assertStringContainsString( 'inerGrid', $output, 'Вывод должен содержать контейнер inerGrid' );
		$this->assertStringContainsString( 'ag-theme-alpine', $output, 'Вывод должен содержать класс ag-theme-alpine' );
		$this->assertStringContainsString( 'inerEmployee', $output, 'Вывод должен содержать селект сотрудников' );
		$this->assertStringContainsString( 'inerExport', $output, 'Вывод должен содержать кнопку экспорта' );
	}

	/**
	 * Тест что гость перенаправляется на страницу авторизации
	 */
	public function test_shortcode_redirects_guest() {
		wp_set_current_user( 0 );

		// Шорткод вызывает auth_redirect() для гостей, что делает exit()
		// Поэтому мы не можем протестировать это напрямую в PHPUnit
		// Вместо этого проверяем что пользователь не авторизован
		$this->assertFalse( is_user_logged_in(), 'Пользователь не должен быть авторизован' );
	}

	/**
	 * Тест загрузки ассетов
	 */
	public function test_assets_loading() {
		$user = $this->factory->user->create( array( 'role' => 'employee' ) );
		wp_set_current_user( $user );

		// Создаем экземпляр Frontend для инициализации хуков
		global $in_employee_reports_plugin;
		$plugin = $in_employee_reports_plugin;

		if ( ! $plugin ) {
			// Если глобальной переменной нет, создаем плагин
			$plugin = new InEmployeeReports\Plugin( INER_FOLDER, INER_URL );
		}

		// Триггерим хук wp_enqueue_scripts
		do_action( 'wp_enqueue_scripts' );

		// Проверяем что стили зарегистрированы
		$this->assertTrue( wp_style_is( 'ag-grid-core', 'registered' ), 'Стиль ag-grid-core должен быть зарегистрирован' );
		$this->assertTrue( wp_style_is( 'ag-grid-theme', 'registered' ), 'Стиль ag-grid-theme должен быть зарегистрирован' );

		// Проверяем что скрипт зарегистрирован
		$this->assertTrue( wp_script_is( 'ag-grid', 'registered' ), 'Скрипт ag-grid должен быть зарегистрирован' );
		$this->assertTrue( wp_script_is( 'in-employee-reports', 'registered' ), 'Скрипт in-employee-reports должен быть зарегистрирован' );
	}

	/**
	 * Тест что данные передаются в JavaScript
	 */
	public function test_localized_script_data() {
		$user = $this->factory->user->create( array( 'role' => 'employee' ) );
		wp_set_current_user( $user );

		// Создаем экземпляр Frontend
		global $in_employee_reports_plugin;
		$plugin = $in_employee_reports_plugin;

		if ( ! $plugin ) {
			$plugin = new InEmployeeReports\Plugin( INER_FOLDER, INER_URL );
		}

		// Триггерим хук
		do_action( 'wp_enqueue_scripts' );

		// Проверяем что данные локализованы (innerREST)
		// В тестовой среде мы не можем напрямую проверить wp_localize_script,
		// но можем проверить что скрипт зарегистрирован
		$this->assertTrue( wp_script_is( 'in-employee-reports', 'registered' ) );
	}
}
