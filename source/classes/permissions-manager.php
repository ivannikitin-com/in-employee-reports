<?php
/**
 * Класс реализует управление ролями и разрешениями
 */
namespace InEmployeeReports;

class Permissions_Manager {

	/**
	 * Роли пользователей, с которым работает плагин
	 */
	public static $roles = array(
		'employee'           => array(
			'title'    => 'Сотрудник',
			'caps'     => array(
				self::READ_ACTIVITY   => true,
				self::EDIT_ACTIVITY   => true,
				self::DELETE_ACTIVITY => true,
				self::CREATE_ACTIVITY => true,
			),
			'baseRole' => 'contributor',
		),
		'head_of_department' => array(
			'title'    => 'Руководитель',
			'caps'     => array(
				self::READ_ACTIVITY           => true,
				self::EDIT_ACTIVITY           => true,
				self::DELETE_ACTIVITY         => true,
				self::CREATE_ACTIVITY         => true,
				self::READ_OTHER_ACTIVITIES   => true,
				self::EDIT_OTHER_ACTIVITIES   => true,
				self::DELETE_OTHER_ACTIVITIES => true,
			),
			'baseRole' => 'editor',
		),
	);

	/**
	 * Разрешения на выполнения операций
	 *
	 * @url https://codex.wordpress.org/Function_Reference/register_post_type#capability_type
	 *
	 * Разрешения на выполнения операций над своей записью отчета
	 */
	const READ_ACTIVITY   = 'iner_read_activity';         // Просмотр своей записи, оно же, доступ к отчетам вообще
	const EDIT_ACTIVITY   = 'iner_edit_activity';         // Редактирование своей записи
	const DELETE_ACTIVITY = 'iner_delete_activity';       // Удаление своей записи
	const CREATE_ACTIVITY = 'iner_create_activity';       // Создание (и публикация) своей записи

	/**
	 * Разрешения на выполнения операций над чужими записями отчета
	 */
	const READ_OTHER_ACTIVITIES   = 'iner_read_other_activities';     // Просмотр "чужих" записей
	const EDIT_OTHER_ACTIVITIES   = 'iner_edit_other_activities';     // Редактирование "чужих" записей
	const DELETE_OTHER_ACTIVITIES = 'iner_delete_other_activities';   // Удаление "чужих" записей

	/**
	 * Инициализация ролей и разрешений
	 * Выполняется только при активации плагина
	 *
	 * @static
	 */
	public static function initRoles() {
		// Регистрируем роли
		foreach ( self::$roles as $role => $props ) {
			// Читаем базовую роль
			$baseRole = get_role( $props['baseRole'] );

			// Дополняем новыми разрешениями
			$caps = array_merge( $baseRole->capabilities, $props['caps'] );

			// Регистрация новой роли пользователя с разрешениями
			add_role( $role, $props['title'], $caps );

			// Установка разрешений для роли (на случай если роль уже существовала)
			$currentRole = get_role( $role );
			if ( $currentRole ) {
				foreach ( $props['caps'] as $cap => $hasCap ) {
					if ( $hasCap ) {
						$currentRole->add_cap( $cap );
					} else {
						$currentRole->remove_cap( $cap );
					}
				}
			}
		}

		// Администраторам даем права на все операции
		$adminRole = get_role( 'administrator' );
		if ( $adminRole ) {
			$adminRole->add_cap( self::READ_ACTIVITY, true );
			$adminRole->add_cap( self::EDIT_ACTIVITY, true );
			$adminRole->add_cap( self::DELETE_ACTIVITY, true );
			$adminRole->add_cap( self::CREATE_ACTIVITY, true );
			$adminRole->add_cap( self::READ_OTHER_ACTIVITIES, true );
			$adminRole->add_cap( self::EDIT_OTHER_ACTIVITIES, true );
			$adminRole->add_cap( self::DELETE_OTHER_ACTIVITIES, true );
		}
	}

	/**
	 * Миграция старых ролей
	 * Находит всех пользователей с ролью 'head' и меняет на 'head_of_department'
	 *
	 * @static
	 */
	public static function migrate_old_roles() {
		// Проверяем, существует ли роль 'head'
		$oldRole = get_role( 'head' );
		if ( ! $oldRole ) {
			// Роль не существует, миграция не требуется
			WP_DEBUG && error_log( 'in-employee-reports: info: Роль "head" не найдена, миграция не требуется' );
			return;
		}

		// Находим всех пользователей с ролью 'head'
		$users = get_users(
			array(
				'role' => 'head',
			)
		);

		WP_DEBUG && error_log( 'in-employee-reports: info: Найдено ' . count( $users ) . ' пользователей с ролью "head" для миграции' );

		// Меняем роль каждому пользователю
		foreach ( $users as $user ) {
			$user_obj = new \WP_User( $user->ID );
			$user_obj->set_role( 'head_of_department' );
			WP_DEBUG && error_log( 'in-employee-reports: info: Пользователь ' . $user->user_login . ' (ID: ' . $user->ID . ') мигрирован на роль "head_of_department"' );
		}

		// Удаляем старую роль 'head'
		remove_role( 'head' );
		WP_DEBUG && error_log( 'in-employee-reports: info: Роль "head" удалена из системы' );
	}

	/**
	 * Хук map_meta_cap для управления правами доступа к записям activity
	 * Определяет какие capabilities нужны для выполнения операций над записями
	 *
	 * @param array  $caps    Массив capabilities которые требуются
	 * @param string $cap     Запрошенный capability
	 * @param int    $user_id ID пользователя
	 * @param array  $args    Дополнительные аргументы (обычно ID поста)
	 * @return array Модифицированный массив capabilities
	 * @static
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		// Проверяем, касается ли это наших capability
		if ( ! in_array(
			$cap,
			array( 'edit_post', 'read_post', 'delete_post' ),
			true
		) ) {
			return $caps;
		}

		// Получаем ID поста из аргументов
		if ( empty( $args[0] ) ) {
			return $caps;
		}

		$post = get_post( $args[0] );
		if ( ! $post ) {
			return $caps;
		}

		// Проверяем, что это наш CPT
		if ( 'activity' !== $post->post_type ) {
			return $caps;
		}

		// Определяем является ли пользователь автором записи
		$is_author = ( (int) $user_id === (int) $post->post_author );

		// Маппим стандартные capabilities на наши кастомные
		switch ( $cap ) {
			case 'edit_post':
				$caps = array( $is_author ? self::EDIT_ACTIVITY : self::EDIT_OTHER_ACTIVITIES );
				break;

			case 'read_post':
				$caps = array( $is_author ? self::READ_ACTIVITY : self::READ_OTHER_ACTIVITIES );
				break;

			case 'delete_post':
				$caps = array( $is_author ? self::DELETE_ACTIVITY : self::DELETE_OTHER_ACTIVITIES );
				break;
		}

		return $caps;
	}

	/**
	 * Проверяет разрещение пользователя при назначении ему множественных ролей
	 *
	 * @param int    $userId     ID пользователя
	 * @param string $capability Разрешение
	 * @static
	 */
	public static function user_can( $userId, $capability ) {
		// Получим текущего пользователя
		$user = new \WP_User( $userId );

		if ( 0 === count( $user->roles ) ) {
			return false;
		}

		// Проверим каждую роль пользователя
		foreach ( $user->roles as $role ) {
			$currentRole = get_role( $role );
			if ( $currentRole && isset( $currentRole->capabilities[ $capability ] ) && $currentRole->capabilities[ $capability ] ) {
				return true;
			}
		}

		// Разрешение не найдено!
		return false;
	}

	/**
	 * Возвращает список пользователей, разрешенных для просмотра указанному пользователю
	 * Используется для фильтрации записей в админке и REST API
	 *
	 * @param int $userId ID пользователя
	 * @static
	 */
	public static function getAllowedUsers( $userId ) {
		// По умолчанию пользователь может видеть только свои записи
		$allowed_users = array( $userId );

		// Если пользователь - руководитель или администратор, может видеть всех
		$user = new \WP_User( $userId );
		if ( in_array( 'head_of_department', $user->roles, true ) || in_array( 'administrator', $user->roles, true ) ) {
			// Получаем всех сотрудников
			$employees     = get_users(
				array(
					'role__in' => array( 'employee', 'head_of_department' ),
					'fields'   => 'ID',
				)
			);
			$allowed_users = $employees;
		}

		// Применяем фильтр для возможности расширения (например, для начальников отделов)
		return apply_filters( 'in-employee-department-users', $allowed_users, $userId );
	}

}
