<?php
/**
 * Тесты для REST API
 */

class Test_REST_API extends WP_UnitTestCase {

	protected $employee;
	protected $head;
	protected $server;

	/**
	 * Настройка перед каждым тестом
	 */
	public function setUp(): void {
		parent::setUp();

		// Инициализация ролей
		InEmployeeReports\Permissions_Manager::initRoles();

		// Создаем пользователей
		$this->employee = $this->factory->user->create( array( 'role' => 'employee' ) );
		$this->head     = $this->factory->user->create( array( 'role' => 'head_of_department' ) );

		// Глобальные переменные WordPress
		global $wp_rest_server;
		$this->server = $wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * Очистка после каждого теста
	 */
	public function tearDown(): void {
		parent::tearDown();
		global $wp_rest_server;
		$wp_rest_server = null;
	}

	/**
	 * Тест что GET запрос требует авторизации
	 */
	public function test_get_items_requires_auth() {
		$request  = new WP_REST_Request( 'GET', '/reports/v2/activity' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 401, $response->get_status(), 'REST API должен возвращать 401 для неавторизованных пользователей' );
	}

	/**
	 * Тест что сотрудник может создать запись
	 */
	public function test_employee_can_create_activity() {
		wp_set_current_user( $this->employee );

		$request = new WP_REST_Request( 'POST', '/reports/v2/activity' );
		$request->set_body_params(
			array(
				'date'    => '01.01.2026',
				'project' => 'Test Project',
				'quo'     => 8,
				'rate'    => 1000,
				'comment' => 'Test activity',
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status(), 'Сотрудник должен иметь возможность создать запись' );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data, 'Ответ должен содержать ID записи' );
	}

	/**
	 * Тест что сотрудник НЕ может редактировать чужую запись
	 */
	public function test_employee_cannot_edit_others_activity() {
		$other_user = $this->factory->user->create( array( 'role' => 'employee' ) );

		$post = $this->factory->post->create(
			array(
				'post_type'   => 'activity',
				'post_author' => $other_user,
			)
		);

		wp_set_current_user( $this->employee );

		$request = new WP_REST_Request( 'POST', '/reports/v2/activity/' . $post );
		$request->set_body_params( array( 'quo' => 10 ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status(), 'Сотрудник НЕ должен иметь возможность редактировать чужую запись' );
	}

	/**
	 * Тест что руководитель может редактировать любую запись
	 */
	public function test_head_can_edit_any_activity() {
		$post = $this->factory->post->create(
			array(
				'post_type'   => 'activity',
				'post_author' => $this->employee,
			)
		);

		update_post_meta( $post, '_activity_quo', 5 );

		wp_set_current_user( $this->head );

		$request = new WP_REST_Request( 'POST', '/reports/v2/activity/' . $post );
		$request->set_body_params( array( 'quo' => 10 ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status(), 'Руководитель должен иметь возможность редактировать любую запись' );
	}

	/**
	 * Тест удаления записи
	 */
	public function test_delete_activity() {
		$post = $this->factory->post->create(
			array(
				'post_type'   => 'activity',
				'post_author' => $this->employee,
			)
		);

		wp_set_current_user( $this->employee );

		$request = new WP_REST_Request( 'DELETE', '/reports/v2/activity/' . $post );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status(), 'Сотрудник должен иметь возможность удалить свою запись' );

		$data = $response->get_data();
		$this->assertTrue( $data['deleted'], 'Ответ должен указывать что запись удалена' );
	}

	/**
	 * Тест получения списка записей
	 */
	public function test_get_items() {
		// Создаем несколько записей
		for ( $i = 0; $i < 3; $i++ ) {
			$this->factory->post->create(
				array(
					'post_type'   => 'activity',
					'post_author' => $this->employee,
				)
			);
		}

		wp_set_current_user( $this->employee );

		$request  = new WP_REST_Request( 'GET', '/reports/v2/activity' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status(), 'GET запрос должен быть успешным' );

		$data = $response->get_data();
		$this->assertGreaterThanOrEqual( 3, count( $data ), 'Должно быть возвращено минимум 3 записи' );
	}

	/**
	 * Тест фильтрации по месяцу и году
	 */
	public function test_filter_by_month_and_year() {
		// Создаем запись с определенной датой
		$post = $this->factory->post->create(
			array(
				'post_type'   => 'activity',
				'post_author' => $this->employee,
				'post_date'   => '2026-01-15 10:00:00',
			)
		);

		wp_set_current_user( $this->employee );

		$request = new WP_REST_Request( 'GET', '/reports/v2/activity' );
		$request->set_param( 'year', '2026' );
		$request->set_param( 'month', '1' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertGreaterThanOrEqual( 1, count( $data ), 'Должна быть возвращена минимум 1 запись для января 2026' );
	}
}
