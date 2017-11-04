<?php
/**
 * Класс реализует управление ролями и разрешениями
 */
namespace INER;
class RoleManager
{
	/**
	 * Роли пользователей, с которым работает плагин
	 */	
	public static $roles = array(
		'employee'	=> array(
			'title'	=> 'Сотрудник',
			'caps'	=> array( 
				self::READ_ACTIVITY 			=> true, 
				self::EDIT_ACTIVITY 			=> true, 
				self::DELETE_ACTIVITY 			=> true, 
				self::CREATE_ACTIVITY 			=> true, 
				self::READ_OTHER_ACTIVITIES 	=> false, 
				self::EDIT_OTHER_ACTIVITIES 	=> false, 
				self::DELETE_OTHER_ACTIVITIES	=> false, 
			),
		'baseRole' => 'contributor'	
		),
		'head_of_department'	=> array(
			'title'	=> 'Начальник отдела',
			'caps'	=> array( 
				self::READ_ACTIVITY 			=> true, 
				self::EDIT_ACTIVITY 			=> true, 
				self::DELETE_ACTIVITY 			=> true, 
				self::CREATE_ACTIVITY 			=> true, 
				self::READ_OTHER_ACTIVITIES 	=> true, 
				self::EDIT_OTHER_ACTIVITIES 	=> false, 
				self::DELETE_OTHER_ACTIVITIES	=> false, 
			),
		'baseRole' => 'author'	
		),		
		'head'	=> array(
			'title'	=> 'Руководитель',
			'caps'	=> array( 
				self::READ_ACTIVITY 			=> true, 
				self::EDIT_ACTIVITY 			=> true, 
				self::DELETE_ACTIVITY 			=> true, 
				self::CREATE_ACTIVITY 			=> true, 
				self::READ_OTHER_ACTIVITIES 	=> true, 
				self::EDIT_OTHER_ACTIVITIES 	=> true, 
				self::DELETE_OTHER_ACTIVITIES	=> true, 
			),
			'baseRole' => 'editor'
		),		
	);
	
	/**
	 * Разрешения на выполнения операций
	 * @url https://codex.wordpress.org/Function_Reference/register_post_type#capability_type
	 *
	 * Разрешения на выполнения операций над своей записью отчета
	 */	
	const READ_ACTIVITY 	= 'iner_read_activity';			// Просмотр своей записи, оно же, доступ к отчетам вообще
	const EDIT_ACTIVITY 	= 'iner_edit_activity';			// Редактирование своей записи
	const DELETE_ACTIVITY 	= 'iner_delete_activity';		// Удаление своей записи
	const CREATE_ACTIVITY 	= 'iner_create_activity';		// Создание (и публикация) своей записи
	
	/**
	 * Разрешения на выполнения операций над чужими записями отчета
	 */	
	const READ_OTHER_ACTIVITIES 	= 'iner_read_other_activities';		// Просмотр "чужих" записей
	const EDIT_OTHER_ACTIVITIES 	= 'iner_edit_other_activities';		// Редактирование "чужих" записей
	const DELETE_OTHER_ACTIVITIES 	= 'iner_delete_other_activities';	// Удаление "чужих" записей
	
	/**
	 * Пароли приложений пользователя
	 */	
	const APP_PASS_USER_META 	= 'app_password';		// Мета-поле пользователя, в котором хранится сериализованный массив с паролем
	const APP_PASS_CACHE 		= 'iner_app_password';	// Кэш, в котором хранится массив со всеми паролями
	const APP_PASS_NAME 		= 'application';		// Параметр массива: название приложения
	const APP_PASS_USER_ID 		= 'user';				// Параметр массива: ID пользователя
	const APP_PASS_KEY 			= 'key';				// Параметр массива: ключ приложения
	const APP_PASS_SECRET 		= 'secret';				// Параметр массива: секретный ключ приложени	
	
	/**
	 * Инициализация ролей и разрешений
	 * Выполняется только при активации плагина
	 * @static
	 */    
	public static function initRoles() 
	{
		// Регистрируем роли
		foreach ( self::$roles as $role => $props )
		{
			// Читаем базовую роль
			$baseRole = get_role( $props['baseRole'] );
			
			// Дополняем новыми разрешениями
			$caps = array_merge( $baseRole->capabilities, $props['caps'] );
			
			// Регистрация новой роли пользователя с разрешениями
			if ( ! add_role( $role, $props['title'], $caps ) )
			{
				// Такая роль уже зарегистрирована, просто добавим разрешения в эту роль
				$currentRole = get_role( $role );				
				foreach ( $caps as $cap => $value )
					$currentRole->add_cap( $cap, $value );	
			}
		}
		
		// Администраторам даем права на все операции
		$adminRole = get_role( 'administrator' );
		$adminRole->add_cap( self::READ_ACTIVITY, true );
		$adminRole->add_cap( self::EDIT_ACTIVITY, true );
		$adminRole->add_cap( self::DELETE_ACTIVITY, true );
		$adminRole->add_cap( self::CREATE_ACTIVITY, true );
		$adminRole->add_cap( self::READ_OTHER_ACTIVITIES, true );
		$adminRole->add_cap( self::EDIT_OTHER_ACTIVITIES, true );
		$adminRole->add_cap( self::DELETE_OTHER_ACTIVITIES, true );
    }
	
	/**
	 * Проверяет разрешение на доступ к объекту с указанными правами
	 * @param int		$postId		ID поста
	 * @param int		$userId		ID пользователя. Если 0 - текущий пользователь
	 * @param string	$ownCap		Разрешение на доступ к своим записям
	 * @param string	$otherCap	Разрешение на доступ к чужим записям
	 * @static
	 */    
	public static function canDo( $postId, $userId=0, $ownCap=self::READ_ACTIVITY, $otherCap=self::READ_OTHER_ACTIVITIES ) 
	{
		// Если пользователь не указан, подставим текущего
		if ( empty( $userId ) ) 
			$userId = get_current_user_id();
		
		// Если пользователь не определен (не залогинен) не разрешаем операцию!
		if ( empty( $userId ) ) 
			return false;
		
		// Если это администратор, то ему можно всё
		if ( user_can( $userId, 'administrator' ) )
			return true;
		
		// Получаем ID владельца записи
		$postAuthorId = get_post_field( 'post_author', $postId );
		
		// Если ID записи 0 - это добавление. Делаем проверку на CREATE
		if ( empty( $postId ) && $ownCap == self::CREATE_ACTIVITY )
		{
			// Считаем, что это пользователь - автор записи. Для следующих проверок.
			$postAuthorId = $userId;
		}
		
		// Если эта запись своя
		if ( $userId == $postAuthorId )
		{
			// Возвратим права на "свое" действие
			return user_can( $userId, $ownCap );
		}	
		else
		{
			// Если пользователь имеет право работать с "чужими" записями
			if ( user_can( $userId, $otherCap ) )
			{
				// Список разрешенных пользователей
				$allowedUsers = self::getAllowedUsers( $userId ) ;
				return in_array( $userId, $allowedUsers );
			}
			else
			{
				// Нет, с чужими права работать не имеет
				return false;
			}
		}		
		// Если что-то не сработало, то запрещаем действие
		return false;
    }
	
	/**
	 * Возвращает список пользователей, разрешенных для просмотра указанному пользователю
	 * @param int	$userId		ID пользователя
	 * @static
	 */    
	public static function getAllowedUsers( $userId ) 
	{
		return apply_filters( 'in-employee-department-users', array( $userId ), $userId );
	}
	
	
	/**
	 * Генерация нового ключа для паролей
	 * @param string	$param		Некий параметр, который может использоваться в качестве дополнительной соли 
	 * @static
	 */    
	public static function generateKey( $param = '' ) 
	{
		return md5( AUTH_SALT . microtime() . $param );
	}	
	
	/**
	 * Возвращает массив паролей приложений
	 * @static
	 */    
	public static function getApplicationPasswords() 
	{
		$applicationPasswords = get_transient( self::APP_PASS_CACHE );
		if ( $applicationPasswords === false )
		{
			// Формируем массив паролей
			$applicationPasswords = array();
			
			// Читаем пользователей, кто имеет право на доступ к отчетам
			$userRoles = array_merge( array( 'administrator' ), array_keys( self::$roles ) );
			$user_query = new \WP_User_Query( array( 'role__in' => $userRoles ) );
			$users = $user_query->get_results();
			
			if ( ! empty( $users ) ) 
			{
				foreach ( $users as $user )
				{
					// Читаем пароли этого пользователя
					$metaFields = get_user_meta( $user->ID, self::APP_PASS_USER_META, true );
					$userPasswords = ( !empty( $metaFields ) ) ? unserialize( $metaFields ) : array();
					$applicationPasswords = array_merge( $applicationPasswords, $userPasswords );
				}
			}
			// Сохраняем в кэше	
			set_transient( self::APP_PASS_CACHE, $applicationPasswords, 2 * MONTH_IN_SECONDS );
		}
		return $applicationPasswords;
	}
	
	/**
	 * Аутентифицирует приложение по ключу и паролю
	 * @param string	$key	Ключ приложения
	 * @param string	$secret	Секрет приложения	 
	 * @static
	 */    
	public static function authentificateApplication( $key, $secret ) 
	{
		$applicationPasswords = self::getApplicationPasswords();
		
		if ( ! array_key_exists( $key, $applicationPasswords ) )
			return false;
			
		if ( $applicationPasswords[$key][self::APP_PASS_SECRET] !== $secret )
			return false;
			
		$user = get_userdata( $applicationPasswords[$key][self::APP_PASS_USER_ID] );
		wp_set_current_user( $user->ID, $user->user_login );
		wp_set_auth_cookie( $user->ID );
		do_action( 'wp_login', $user->user_login );
		return true;
	}
	
}