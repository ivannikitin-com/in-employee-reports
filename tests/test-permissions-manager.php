<?php
/**
 * Тесты для класса Permissions_Manager
 */

class Test_Permissions_Manager extends WP_UnitTestCase {

	/**
	 * Тест создания ролей
	 */
	public function test_roles_created() {
		// Инициализируем роли
		InEmployeeReports\Permissions_Manager::initRoles();

		// Проверяем что роли созданы
		$employee_role = get_role( 'employee' );
		$head_role     = get_role( 'head_of_department' );

		$this->assertNotNull( $employee_role, 'Роль employee должна быть создана' );
		$this->assertNotNull( $head_role, 'Роль head_of_department должна быть создана' );
	}

	/**
	 * Тест наличия правильных capabilities у ролей
	 */
	public function test_roles_capabilities() {
		InEmployeeReports\Permissions_Manager::initRoles();

		$employee_role = get_role( 'employee' );
		$head_role     = get_role( 'head_of_department' );

		// Проверяем capabilities для employee
		$this->assertTrue( $employee_role->has_cap( InEmployeeReports\Permissions_Manager::READ_ACTIVITY ) );
		$this->assertTrue( $employee_role->has_cap( InEmployeeReports\Permissions_Manager::EDIT_ACTIVITY ) );
		$this->assertFalse( $employee_role->has_cap( InEmployeeReports\Permissions_Manager::READ_OTHER_ACTIVITIES ) );

		// Проверяем capabilities для head_of_department
		$this->assertTrue( $head_role->has_cap( InEmployeeReports\Permissions_Manager::READ_ACTIVITY ) );
		$this->assertTrue( $head_role->has_cap( InEmployeeReports\Permissions_Manager::EDIT_ACTIVITY ) );
		$this->assertTrue( $head_role->has_cap( InEmployeeReports\Permissions_Manager::READ_OTHER_ACTIVITIES ) );
	}

	/**
	 * Тест что сотрудник может читать свою запись
	 */
	public function test_employee_can_read_own_activity() {
		InEmployeeReports\Permissions_Manager::initRoles();

		$user = $this->factory->user->create( array( 'role' => 'employee' ) );
		$post = $this->factory->post->create(
			array(
				'post_type'   => 'activity',
				'post_author' => $user,
			)
		);

		wp_set_current_user( $user );

		$this->assertTrue( current_user_can( 'read_post', $post ), 'Сотрудник должен иметь возможность читать свою запись' );
	}

	/**
	 * Тест что сотрудник НЕ может читать чужую запись
	 */
	public function test_employee_cannot_read_others_activity() {
		InEmployeeReports\Permissions_Manager::initRoles();

		$user1 = $this->factory->user->create( array( 'role' => 'employee' ) );
		$user2 = $this->factory->user->create( array( 'role' => 'employee' ) );

		$post = $this->factory->post->create(
			array(
				'post_type'   => 'activity',
				'post_author' => $user2,
			)
		);

		wp_set_current_user( $user1 );

		$this->assertFalse( current_user_can( 'read_post', $post ), 'Сотрудник НЕ должен иметь возможность читать чужую запись' );
	}

	/**
	 * Тест что руководитель может читать все записи
	 */
	public function test_head_can_read_all_activities() {
		InEmployeeReports\Permissions_Manager::initRoles();

		$employee = $this->factory->user->create( array( 'role' => 'employee' ) );
		$head     = $this->factory->user->create( array( 'role' => 'head_of_department' ) );

		$post = $this->factory->post->create(
			array(
				'post_type'   => 'activity',
				'post_author' => $employee,
			)
		);

		wp_set_current_user( $head );

		$this->assertTrue( current_user_can( 'read_post', $post ), 'Руководитель должен иметь возможность читать все записи' );
	}

	/**
	 * Тест миграции старой роли 'head'
	 */
	public function test_old_head_role_migrated() {
		// Создаем старую роль 'head' для теста
		add_role( 'head', 'Старый руководитель' );

		// Создаем пользователя с этой ролью
		$user = $this->factory->user->create( array( 'role' => 'head' ) );

		// Выполняем миграцию
		InEmployeeReports\Permissions_Manager::migrate_old_roles();

		// Проверяем что пользователь теперь имеет роль head_of_department
		$user_obj = new WP_User( $user );
		$this->assertTrue( in_array( 'head_of_department', $user_obj->roles, true ), 'Пользователь должен иметь роль head_of_department после миграции' );
		$this->assertFalse( in_array( 'head', $user_obj->roles, true ), 'Пользователь НЕ должен иметь роль head после миграции' );

		// Проверяем что роль 'head' удалена
		$old_role = get_role( 'head' );
		$this->assertNull( $old_role, 'Роль head должна быть удалена после миграции' );
	}

	/**
	 * Тест метода getAllowedUsers
	 */
	public function test_getAllowedUsers() {
		InEmployeeReports\Permissions_Manager::initRoles();

		$employee = $this->factory->user->create( array( 'role' => 'employee' ) );
		$head     = $this->factory->user->create( array( 'role' => 'head_of_department' ) );

		// Сотрудник должен видеть только себя
		$allowed_for_employee = InEmployeeReports\Permissions_Manager::getAllowedUsers( $employee );
		$this->assertCount( 1, $allowed_for_employee, 'Сотрудник должен видеть только себя' );
		$this->assertContains( $employee, $allowed_for_employee, 'Список должен содержать ID сотрудника' );

		// Руководитель должен видеть всех сотрудников
		$allowed_for_head = InEmployeeReports\Permissions_Manager::getAllowedUsers( $head );
		$this->assertGreaterThan( 1, count( $allowed_for_head ), 'Руководитель должен видеть всех сотрудников' );
	}
}
