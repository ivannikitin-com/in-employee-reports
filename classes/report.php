<?php
/**
 * Класс реализует функциональность отчетов
 */
namespace INER;
class Report extends Base
{
	
	/**
	 * Тип записей, используем старый для обратной совместимости
	 * @static
	 */
	const CPT 	= 'activity';	
	
	/**
	 * Конструктор
	 * инициализирует параметры и загружает данные
	 * @param INER\Plugin	$plugin Ссылка на основной объект плагина
	 */
	public function __construct( $plugin )
	{
		// Родительский конструктор
		parent::__construct( $plugin );
		
		// Если у пользователя нет прав на доступ к отчетам, ничего не делаем
		if ( ! ( current_user_can( RoleManager::VIEW_EMPLOYEE_REPORTS ) || current_user_can( 'administrator' ) ) )
			return;
		
		// Регистрируем тип записей
		$this->registerCPT();

		// Загрузка CSS
		add_action( 'admin_enqueue_scripts', array( $this, 'loadCSS' ) );		
		
		// Метабокс свойств записей
		add_action( 'add_meta_boxes', array( $this, 'addMetabox' ) );
		add_action( 'save_post',  array( $this, 'saveMetabox' ), 10, 2);
		
		// Колонки в админке
		add_filter('manage_' . self::CPT . '_posts_columns' , array( $this, 'getAdminColumns' ) );
		add_action('manage_' . self::CPT . '_posts_custom_column' , array( $this, 'showAdminColumns' ), 10, 2 );
		add_filter('manage_edit-' . self::CPT . '_sortable_columns' , array( $this, 'getSortableAdminColumns' ) );
		
		// Фильтрация отчетов по пользователям
		add_action( 'pre_get_posts', array( $this, 'setUsersFilter' ) );
	}
	
	/**
	 * Регистрация типа записи для хранения данных отчетов
	 */
	private function registerCPT()
	{
		$labels = array(
			'name'                => 'Записи отчета',
			'singular_name'       => 'Запись отчета',
			'menu_name'           => 'Отчеты',
			'name_admin_bar'      => 'Отчеты',
			'parent_item_colon'   => 'Parent Item:',
			'all_items'           => 'Все записи',
			'add_new_item'        => 'Новая запсь',
			'add_new'             => 'Добавить',
			'new_item'            => 'Новая запись',
			'edit_item'           => 'Редактировать',
			'update_item'         => 'Обновить',
			'view_item'           => 'Просмотр',
			'search_items'        => 'Поиск',
			'not_found'           => 'Не найдено',
			'not_found_in_trash'  => 'Не найдено в корзине',
		);
		$args = array(
			'label'               => 'Запись отчета',
			'description'         => 'Отчеты сотрудников о проделанной работе',
			'labels'              => $labels,
			'supports'            => array( 'title', 'author' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 70,
			'menu_icon'           => 'dashicons-list-view', 
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,		
			'exclude_from_search' => true,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
			'rest_base'           => self::CPT,
			'rest_controller_class' => 'WP_REST_Posts_Controller'			
			);
		register_post_type(self::CPT, $args );		
	}
	
	/**
	 * Загрузка CSS для админки
	 */
	public function loadCSS( $hook )
	{
        wp_register_style( 'in-employee-reports-admin', $this->plugin->url . 'assets/css/admin.css', false, '2.0' );
        wp_enqueue_style( 'in-employee-reports-admin' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
	}
	
	/* Структура записей отчетов 
	 *  id 			код записи
	 *	employee	Сотрудник (автор)
	 *	date		дата публикации записи (используем дату поста)
	 *	project		проект (мета-поле)
	 *	quo			количество (мета поле)
	 *	rate		ставка (мета поле)
	 *	comment		Комментарий (используем title записи)
	 */
	const FIELD_ID			= 'id';	
	const FIELD_EMPLOYEE	= 'employee';	
	const FIELD_DATE		= 'date';	
	const FIELD_PROJECT		= 'project';	
	const FIELD_QUO			= 'quo';	
	const FIELD_RATE		= 'rate';	
	
	/**
	 * Мета-поля, используем старые значения для обратной совместимости
	 * @static
	 */
	const META_PROJECT	= '_activity_project';	
	const META_QUO		= '_activity_quo';	
	const META_RATE		= '_activity_rate';	
	
	/**
	 * NONCE действия
	 * @static
	 */
	const NONCE_METABOX	= 'activity_metabox';	
	
	
	
	/**
	 * Регистрация метабокса для свойств записей
	 */
	public function addMetabox()
	{
		add_meta_box(
			'reportMetabox', 				// id, used as the html id att
			'Данные для отчета',			// meta box title, like "Page Attributes"
			array( $this, 'showMetabox' ), 	// callback function, spits out the content
			self::CPT, 						// post type or page. We'll add this to pages only
			'advanced', 					// context (where on the screen
			'default' 						// priority, where should this go in the context?
		);
	}	
	
	/**
	 * Отображение метабокса
	 */	
	public function showMetabox( $post, $box )
	{
		wp_nonce_field( self::NONCE_METABOX, self::NONCE_METABOX );

		// Поля записи
		$date 		= esc_attr( get_the_date( 'd.m.Y', $post ) );
		$project 	= esc_attr( get_post_meta( $post->ID, self::META_PROJECT, true ) ); 
		$quo 		= esc_attr( get_post_meta( $post->ID, self::META_QUO, true ) );
		$rate 		= esc_attr( get_post_meta( $post->ID, self::META_RATE, true ) );
		
		$dateFiled = self::FIELD_DATE;
		$projectFiled = self::FIELD_PROJECT;
		$quoFiled = self::FIELD_QUO;
		$rateFiled = self::FIELD_RATE;
		
		$html = <<<END_OF_HTML
<input type="hidden" name="activityMetabox" value="1" />
<div>
	<label for="{$dateFiled}">Дата</label>
	<input id="{$dateFiled}" type="text" name="{$dateFiled}" value="{$date}"/>
	<script>jQuery(function($){ $('#reportMetabox #{$dateFiled}').datepicker(); })</script>
</div>
<div>
	<label for="{$projectFiled}">Проект</label>
	<input id="{$projectFiled}" type="text" name="{$projectFiled}" value="{$project}"/>
</div>
<div>
	<label for="{$quoFiled}">Объем</label>
	<input id="{$quoFiled}" type="text" name="{$quoFiled}" value="{$quo}"/>
</div>
<div>
	<label for="{$rateFiled}">Ставка</label>
	<input id="{$rateFiled}" type="text" name="{$rateFiled}" value="{$rate}"/>
</div>	
END_OF_HTML;
		
		echo $html;
	}	
	
	/**
	 * Блокирование повторного вызова функции saveMetabox при выполнении wp_update_post
	 */
	private $wpUpdatePostInProcess = false;
	
	/**
	 * Сохранение метабокса
	 */	
	public function saveMetabox( $postID, $post )
	{
		// Если уже выполняется этот хук, больше ничего не делаем!
		if ( $this->wpUpdatePostInProcess ) 
			return;			
		
		// Это данные метабокса? 
		if ( ! isset($_POST['activityMetabox'] ) ) 
			return;		
		
		// не происходит ли автосохранение? 
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
			return; 

		// не ревизию ли сохраняем? 
		if (wp_is_post_revision($postID)) 
			return;
		
		// проверка достоверности запроса 
		check_admin_referer( self::NONCE_METABOX, self::NONCE_METABOX );
		
		// Получаем данные
		$date 		= sanitize_text_field( $_POST[self::FIELD_DATE] );
		$project 	= sanitize_text_field( $_POST[self::FIELD_PROJECT] );
		$quo 		= sanitize_text_field( $_POST[self::FIELD_QUO] );
		$rate 		= sanitize_text_field( $_POST[self::FIELD_RATE] );
		
		// Формат даты
		$date = date_create_from_format( 'd.m.Y', $date );
		$date = date_format($date, 'Y-m-d H:i:s');

		// Обновляем запись
		try
		{
			$this->wpUpdatePostInProcess = true;
			
			wp_update_post( array(
				'ID'		=> $postID,
				'post_date'	=> $date,
			) ); 

			update_post_meta( $postID, self::META_PROJECT, 	$project);
			update_post_meta( $postID, self::META_QUO, 		$quo);
			update_post_meta( $postID, self::META_RATE, 	$rate);			
		}
		finally
		{
			$this->wpUpdatePostInProcess = false;
		}
	}
	
	/**
	 * Возвращает массив колонок в админке для таблицы CPT
	 * @param mixed	$columns 	Массив колонок	 
	 */	
	public function getAdminColumns( $columns )
	{
		// Уберем ненужные колонки
		unset(
			$columns['author'],
			$columns['comments'],
			$columns['title'],
			$columns['date']
		);
		
		// Добавим колонки в нужном порядке
		$newColumns = array(
			self::FIELD_ID 		=> 'Код',
			'author' 			=> 'Сотрудник',			// Используем стандартную колонку автор
			'dateRec' 			=> 'Дата',				// Переименовываем колонку, чтобы вывести дату в нужном формате
			self::FIELD_PROJECT	=> 'Проект',
			self::FIELD_QUO		=> 'Часов/знаков',
			self::FIELD_RATE	=> 'Ставка',
			'summ' 			=> 'Сумма',					// Дополнительная колонка с расчетом суммы
			'title' 		=> 'Примечание'				// Используем стандартную колонку Название
		);
		
		return array_merge( $columns, $newColumns );		
	}
	
	/**
	 * Выводит значения колонок в админке для таблицы CPT
	 * @param string	$column 	Идентификатор колонки
	 * @param int		$postId 	Идентификатор записи		 
	 */	
	public function showAdminColumns( $column, $postId )
	{
		switch ( $column ) 
		{
			case self::FIELD_ID :
				echo $postId;
				break;
				
			case 'dateRec' :
				echo get_the_date( 'd.m.Y', $postId );
				break;				

			case self::FIELD_PROJECT :
				echo get_post_meta($postId , self::META_PROJECT , true); 
				break;

			case self::FIELD_QUO :
				echo get_post_meta($postId , self::META_QUO , true); 
				break;

			case self::FIELD_RATE :
				echo sprintf( '%01.2f руб.', get_post_meta($postId , self::META_RATE , true) ); 
				break;
				
			case 'summ' :
				$summ = (float) get_post_meta($postId , self::META_QUO , true) * get_post_meta($postId , self::META_RATE , true);
				echo sprintf( '%01.2f руб.', $summ ); 
				break;
		}	
	}
	
	/**
	 * Возвращает массив колонок для сортировки в админке
	 * @param mixed	$sortableColumns 	Массив колонок для сортировки		 
	 */	
	public function getSortableAdminColumns( $sortableColumns )
	{
		$sortableColumns[self::FIELD_ID] = self::FIELD_ID;
		$sortableColumns['author'] = 'author';
		$sortableColumns['dateRec'] = 'date';
		$sortableColumns[self::FIELD_PROJECT] = self::META_PROJECT;
		return $sortableColumns;
	}
	
	/**
	 * Устанавливает фильтрацию записей отчетов по пользователю
	 * @param WP_Query	$query Объект запроса		 
	 */	
	public function setUsersFilter( $query )
	{
		if ( $query->get( 'post_type' ) == self::CPT )
		{
			// Получаем список пользователей, отчеты которых нужно показать
			$userIds = $this->plugin->roleManager->getUserIds();
			
			// Для админов не фильтруем
			if ( empty( $userIds ) )
				return;
			
			// Возможный текущий фильтр
			$currentAuthor = $query->get('author');
			
			// Если пользователь пытается подставить в фильтр ID, доступ к которому есть, разрешаем и ничего не делаем
			if ( ! empty( $currentAuthor )  && in_array( $currentAuthor, $userIds ) )
				return;
			
			// Ставим фильтр
			$query->set( 'author', implode( ',', $userIds ) );
		}
	}
	
}