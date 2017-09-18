<?php
/**
 * Класс реализует управление ролями и разрешениями
 */
namespace INER;
class RoleManager extends Base
{
	/**
	 * Роли пользователей, с которым работает плагин
	 */	
	public static $roles = array(
		'head'	=> array(
			'title'	=> 'Руководитель',
			'caps'	=> array( 
				self::VIEW_EMPLOYEE_REPORTS => true, 
				self::VIEW_EMPLOYEE_ALL_REPORTS => true 
			),
			'baseRole' => 'editor'
		),
		'head_of_department'	=> array(
			'title'	=> 'Начальник отдела',
			'caps'	=> array( 
				self::VIEW_EMPLOYEE_REPORTS => true, 
				self::VIEW_EMPLOYEE_DEPARTMENT_REPORTS => true 
			),
		'baseRole' => 'author'	
		),		
		'employee'	=> array(
			'title'	=> 'Сотрудник',
			'caps'	=> array( 
				self::VIEW_EMPLOYEE_REPORTS => true 
			),
		'baseRole' => 'contributor'	
		),		
	);
	
	/**
	 * Разрешения на выполнения операций
	 */		
	const VIEW_EMPLOYEE_REPORTS 			= 'view_employee_reports';				// Общий доступ к отчетам сотрудников
	const VIEW_EMPLOYEE_DEPARTMENT_REPORTS 	= 'view_employee_department_reports';	// Просмотр отчетов сотрудников отдела
	const VIEW_EMPLOYEE_ALL_REPORTS 		= 'view_employee_all_reports';			// Просмотр всех отчетов всех сотрудников
	
	/**
	 * Инициализация ролей и разрешений
	 * Выполняется только при активации плагина
	 */    
	public static function initRoles() 
	{
		foreach ( self::$roles as $role => $props )
		{
			// Читаем базовую роль
			$baseRole = get_role( $props['baseRole'] );
			
			// Дополняем новыми разрешениями
			$caps = array_merge( $baseRole->capabilities, $props['caps'] );
			
			// Регистрация новой роли пользователя с разрешениями
			add_role( $role, $props['title'], $caps );
		}
    }
	
	/**
	 * Конструктор
	 * инициализирует параметры и загружает данные
	 * @param INER\Plugin	$plugin Ссылка на основной объект плагина
	 */
	public function __construct( $plugin )
	{
		// Родительский конструктор
		parent::__construct( $plugin );		
	}
	
	/**
	 * Возвращает массив id пользователей, к отчетам которых пользователь имеет доступ
	 * @param int		$userId ID пользователя, 0 - текущий пользователь
	 * @return mixed	Если массив пустой, показываем ВСЕХ
	 */	
	public function getUserIds( $userId=0 )
	{
		// Если ID не указан, то пользователь текущий
		if ( empty( $userId ) )
			$userId = get_current_user_id();
		
		// Результирующий массив
		$userIds = array( $userId );
		
		// Администратору и руководству показываем ВСЕХ
		if ( user_can( $userId, 'administrator' ) || user_can( $userId, self::VIEW_EMPLOYEE_ALL_REPORTS )  )
			return array();
		
		// Рекомодителю отдела запрашиваем фильтром список сотрудников
		if ( user_can( $userId, self::VIEW_EMPLOYEE_DEPARTMENT_REPORTS )  )
			return apply_filters( 'in-employee-department-users', $userIds, $userId );
		
		// Остальным возвращаем массив с ID текущего пользователя
		return $userIds;
	}
	
	
	
}