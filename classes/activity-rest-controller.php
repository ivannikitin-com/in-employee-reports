<?php
/**
 * Класс REST контроллера для данных отчетов
 * Недолго думая, просто расширяем готовый класс контроллера для постов
 */
namespace INER;
class Activity_REST_Controller extends \WP_REST_Posts_Controller
{
	/**
	 * Инициализация контроллера		 
	 */	
    public function __construct ( $post_type ) 
	{
        // Выполняем родительский конструктор
		parent::__construct( $post_type );
		
		// Регистрируем новое пространство и ресурс для REST
		// URL: http://ivannikitin.ivan.wp-server.ru/wp-json/reports/v2/activity/ 
		$this->namespace     = '/reports/v2';
        $this->resource_name = 'activity';
    }
	
	/**
	 * Регистрируем маршруты и методы		 
	 */	
    public function register_routes() 
	{
		// Методы для запросов списка записей очета и создания новой записи
        register_rest_route( $this->namespace, '/' . $this->resource_name, array(
            array(
                'methods'   			=> \WP_REST_Server::READABLE,	// GET
                'callback'  			=> array( $this, 'get_items' ),
                'permission_callback'	=> array( $this, 'get_items_permissions_check' ),
            ),
            array(
                'methods'   			=> \WP_REST_Server::CREATABLE,	// POST
                'callback'  			=> array( $this, 'create_item' ),
                'permission_callback'	=> array( $this, 'create_item_permissions_check' ),
            ),			
            // Register our schema callback.
            'schema' => array( $this, 'get_item_schema' ),
        ) );
		
		// Метод запроса отдельной записи
        register_rest_route( $this->namespace, '/' . $this->resource_name . '/(?P<id>[\d]+)', array(        
            array(
                'methods'   			=> \WP_REST_Server::READABLE,		// GET
                'callback'  			=> array( $this, 'get_item' ),
                'permission_callback'	=> array( $this, 'get_item_permissions_check' ),
            ),
			array(
				'methods'				=> \WP_REST_Server::EDITABLE,		// POST, PUT, PATCH
				'callback'				=> array( $this, 'update_item' ),
				'permission_callback'	=> array( $this, 'update_item_permissions_check' ),
			),			
			array(
				'methods'				=> \WP_REST_Server::DELETABLE,		// DELETE
				'callback'				=> array( $this, 'delete_item' ),
				'permission_callback'	=> array( $this, 'delete_item_permissions_check' ),
			),				
            // Register our schema callback.
            'schema' => array( $this, 'get_item_schema' ),
        ) );
    }	
	
    /**
     * Возвращает схему данных
     */
    public function get_item_schema( ) {
        $schema = array(
            // This tells the spec of JSON Schema we are using which is draft 4.
            '$schema'              => 'http://json-schema.org/draft-04/schema#',
            // The title property marks the identity of the resource.
            'title'                => 'activity',
            'type'                 => 'object',
            // In JSON Schema you can specify object properties in the properties attribute.
            'properties'           => array(
                'id' => array(
                    'description'  => 'Код записи',
                    'type'         => 'integer',
                    'context'      => array( 'view', 'edit', 'embed' ),
                    'readonly'     => true,
                ),
                'employee' => array(
                    'description'  => 'Сотрудник',
                    'type'         => 'string',
                    'context'      => array( 'view', 'edit', 'embed' ),
                    'readonly'     => true,					
                ),
                'date' => array(
                    'description'  => 'Дата',
                    'type'         => 'string',				
                ),
                'project' => array(
                    'description'  => 'Проект',
                    'type'         => 'string',				
                ),
                'quo' => array(
                    'description'  => 'Объем работ',
                    'type'         => 'float',				
                ),
                'rate' => array(
                    'description'  => 'Ставка',
                    'type'         => 'float',				
                ),				
                'comment' => array(
                    'description'  => 'Дата',
                    'type'         => 'string',				
                ),				
            ),
        ); 
        return $schema;
    }
	
    /**
     * Проверка прав на доступ к отчетам вообще
     *
     * @param WP_REST_Request $request Текущий запрос
     */
    public function get_items_permissions_check( $request ) 
	{        
		/** DUMP Request 
		file_put_contents( INER_FOLDER . 'request-items.log', var_export($request, true ) . PHP_EOL . PHP_EOL );*/

		/** DUMP $_SERVER для проверки авторизации */
		$_SERVER['WP_User'] = wp_get_current_user();
		file_put_contents( INER_FOLDER . 'var-server.log', var_export($_SERVER, true ) . PHP_EOL . PHP_EOL );		
		
		
		
		// Проверка авторизации пользователя
		if ( ! is_user_logged_in() )
			return new \WP_Error( 'rest_unauthorized', 'Вы не авторизованы!', array( 'status' => '401' ) );
		
		// Проверка прав на доступ к отчетам
		if ( ! current_user_can( RoleManager::READ_ACTIVITY ) ) 
			return new \WP_Error( 'rest_forbidden', 'У вас нет прав на доступ к отчетам!', array( 'status' => '403' ) );
        
		return true;
    }	
	
    /**
     * Получаем список записей
     * @param WP_REST_Request $request Объект текущего запроса
     */
    public function get_items( $request ) 
	{
		/** DUMP Request 
		file_put_contents( INER_FOLDER . 'request-items.log', var_export($request, true ) . PHP_EOL . PHP_EOL );	*/	
		
		// Параметры запроса
		$employeeId = ( isset ($request['employeeId']) ) ? $request['employeeId'] : get_current_user_id();
		$year = ( isset ($request['year']) ) ? $request['year'] : date('Y');
		$month = ( isset ($request['month']) ) ? $request['month'] : date('m');
		
		// Данные для ответа
		$data = array();
		
		// Далем запрос на получение данных
		$args = array( 
			'post_type' 		=> Report::CPT,
			'year'				=> $year,
			'monthnum'			=> $month,
			'order'				=> 'ASC',
			'orderby'			=> 'ID',
			'posts_per_page'	=>-1
		);
		

		// Определяем пользователей, которых нужно показать
		if ( $employeeId == '0' )
		{
			// Находим список пользователей, которых нужно показать
			$args[ 'author__in'] = RoleManager::getAllowedUsers( get_current_user_id() );
			
			// Если это админ, уберем фильтр
			if ( current_user_can( 'administrator' ) ) 
				unset( $args[ 'author__in'] );
		}
		else
		{
			// Для единичного пользователя, просто указываем его
			$args[ 'author'] = $employeeId ;
		}
		
		$query = new \WP_Query();
		$posts = $query->query( $args );
		
        if ( empty( $posts ) ) 
            return rest_ensure_response( $data );		
		
		foreach( $posts as $post )
		{
			$response = $this->prepare_item_for_response( $post, $request );
			$data[] = $this->prepare_response_for_collection( $response );
		}
		
		return rest_ensure_response( $data );
    }
	
    /**
     * Подготавливает ответ для списка записей
     *
     * This is copied from WP_REST_Controller class in the WP REST API v2 plugin.
     *
     * @param WP_REST_Response $response Response object.
     * @return array Response data, ready for insertion into collection data.
     */
    public function prepare_response_for_collection( $response ) 
	{
        if ( ! ( $response instanceof WP_REST_Response ) ) {
            return $response;
        }
 
        $data = (array) $response->get_data();
        $server = rest_get_server();
 
        if ( method_exists( $server, 'get_compact_response_links' ) ) {
            $links = call_user_func( array( $server, 'get_compact_response_links' ), $response );
        } else {
            $links = call_user_func( array( $server, 'get_response_links' ), $response );
        }
 
        if ( ! empty( $links ) ) {
            $data['_links'] = $links;
        }
 
        return $data;
    }	
	
	
    /**
     * Проверка прав на доступ к отдельной записи
     *
     * @param WP_REST_Request $request Текущий запрос
     */
    public function get_item_permissions_check( $request ) 
	{
		/** DUMP Request 
		file_put_contents( INER_FOLDER . 'request-item.log', var_export($request, true ) . PHP_EOL . PHP_EOL )*/;		
		
		// Проверка авторизации пользователя
		if ( ! is_user_logged_in() )
			return new \WP_Error( 'rest_unauthorized', 'Вы не авторизованы!', array( 'status' => '401' ) );
		
		// Проверка прав на доступ к записи
		if ( ! RoleManager::canDo( $request[ Report::FIELD_ID ], get_current_user_id(), RoleManager::READ_ACTIVITY, RoleManager::READ_OTHER_ACTIVITIES ) ) 
			return new \WP_Error( 'rest_forbidden', 'У вас нет прав на доступ к записи #' . $request['id'], array( 'status' => '403' ) );		

        return true;
    }	

    /**
     * Получаем отдельную запись
     *
     * @param WP_REST_Request $request Current request.
     */
    public function get_item( $request ) 
	{
        $id = (int) $request[ Report::FIELD_ID ];
        $post = get_post( $id );
 
        if ( empty( $post ) ) {
            return rest_ensure_response( array() );
        }
 
        $response = $this->prepare_item_for_response( $post, $request );
 
        // Return all of our post response data.
        return $response;
    }
	
    /**
     * Подготавливает ответ одной записи в соотвествии с выбранной схемой
     *
     * @param WP_Post $post The comment object whose response is being prepared.
     */
    public function prepare_item_for_response( $post, $request ) 
	{
        $post_data = array();
		$post_data[ Report::FIELD_ID ] 			= (int) $post->ID;
		$employee = get_userdata( $post->post_author );
		$post_data[ Report::FIELD_EMPLOYEE ] 	= $employee->display_name;
		$post_data[ Report::FIELD_DATE ] 		= $post->post_date;
		$post_data[ Report::FIELD_PROJECT ] 	= get_post_meta( $post->ID, Report::META_PROJECT, true );
		$post_data[ Report::FIELD_QUO ] 		= (float) get_post_meta( $post->ID, Report::META_QUO, true ) * 1;
		$post_data[ Report::FIELD_RATE ] 		= (float) get_post_meta( $post->ID, Report::META_RATE, true ) * 1;
		$post_data[ Report::FIELD_COMMENT ] 	= $post->post_title;
        return rest_ensure_response( $post_data );
    }	
	
    /**
     * Проверка прав на создание новой записи
     *
     * @param WP_REST_Request $request Текущий запрос
     */
    public function create_item_permissions_check( $request ) 
	{
		/** DUMP Request 
		file_put_contents( INER_FOLDER . 'request-item-create.log', var_export($request, true ) . PHP_EOL . PHP_EOL );	*/	
		
		// Проверка авторизации пользователя
		if ( ! is_user_logged_in() )
			return new \WP_Error( 'rest_unauthorized', 'Вы не авторизованы!', array( 'status' => '401' ) );
		
		// Проверка прав на доступ к записи
		if ( ! RoleManager::canDo( $request['id'], get_current_user_id(), RoleManager::READ_ACTIVITY, RoleManager::READ_OTHER_ACTIVITIES ) ) 
			return new \WP_Error( 'rest_forbidden', 'У вас нет прав на доступ к записи #' . $request[ Report::FIELD_ID ], array( 'status' => '403' ) );		

		return true;
    }	
	
    /**
     * Создаем новую запись
     *
     * @param WP_REST_Request $request Current request.
     */
    public function create_item( $request ) 
	{		
		if ( ! empty( $request[ Report::FIELD_ID ] ) )
			return new WP_Error( 'rest_post_exists', 'Нельзя создать запись с ID #' . $request[ Report::FIELD_ID ], array( 'status' => 400 ) );
		
		// Читаем исходные данные
		// Сотрудник
		$employeeId = 0;
		if ( isset( $request[ Report::FIELD_EMPLOYEE ] ) )
		{
			$searchUser = new WP_User_Query( array( 
			  'search' => $request[ Report::FIELD_EMPLOYEE ],
			  'search_fields' => array('user_login','user_nicename','display_name')				
			));
			if ( ! empty( $searchUser->results ) )
				$employeeId = $searchUser->results[0]->ID;
		}
		else
		{
			$employeeId = get_current_user_id();
		}
		
		//  Дата в UTC
		$date = isset( $request[ Report::FIELD_DATE ] ) ? date_create_from_format( 'd.m.Y', $request[ Report::FIELD_DATE ] ) : date_create();
		$date = date_format($date, 'Y-m-d H:i:s');
		
		$project =  isset( $request[ Report::FIELD_PROJECT ] ) ? $request[ Report::FIELD_PROJECT  ] : '';
		$quo =  (float) isset( $request[ Report::FIELD_QUO  ] ) ? $request[ Report::FIELD_QUO ] : 0;
		$rate =  (float) isset( $request[ Report::FIELD_RATE ] ) ? $request[ Report::FIELD_RATE ] : 0;
		$comment =  isset( $request[ Report::FIELD_COMMENT ] ) ? $request[ Report::FIELD_COMMENT ] : '';
		
		// Добавляем запись
		$postId = wp_insert_post( array(
			'post_author'	=> $employeeId,
			'post_date_gmt'	=> $date,
			'post_title'	=> $comment,
			'post_status'	=> 'publish',
			'post_type'		=> Report::CPT,
			'meta_input'	=> array(
				Report::META_PROJECT	=> $project,
				Report::META_QUO		=> $quo,
				Report::META_RATE		=> $rate,
			),			
		));
		
		// Проверяем на ошибку
		if ( is_wp_error( $postId ) ) 
			return new WP_Error( 'rest_post_exists', 'Ошибка создания записи ' . $postId->get_error_code(), array( 'status' => 500 ) );			
		
		// Получаем текущую запись
		$response = $this->get_item( array( 'id' => $postId ) );
 
        // Возвращает текущую запись
        return $response;
    }
	
    /**
     * Проверка прав на обновление записи
     *
     * @param WP_REST_Request $request Текущий запрос
     */
    public function update_item_permissions_check( $request ) 
	{
		// Проверка авторизации пользователя
		if ( ! is_user_logged_in() )
			return new \WP_Error( 'rest_unauthorized', 'Вы не авторизованы!', array( 'status' => '401' ) );
		
		// Проверка прав на доступ к записи
		if ( ! RoleManager::canDo( $request[ Report::FIELD_ID ], get_current_user_id(), RoleManager::EDIT_ACTIVITY, RoleManager::EDIT_OTHER_ACTIVITIES ) ) 
			return new \WP_Error( 'rest_forbidden', 'У вас нет прав на редактирование записи #' . $request[ Report::FIELD_ID ], array( 'status' => '403' ) );		

		return true;
    }	
	
    /**
     * Обновление записи
     *
     * @param WP_REST_Request $request Current request.
     */
    public function update_item( $request ) 
	{		
		// Проверка ID
		if ( empty( $request[ Report::FIELD_ID ] ) )
			return new \WP_Error( 'rest_post_bad_id', 'Пустой ID записи', array( 'status' => '400' ) );	
		
		// Получаем запись
		$post = get_post( $request[ Report::FIELD_ID ] );
		if ( is_wp_error( $post ) )
			return new WP_Error( 'rest_post_error', 'Ошибка доступа к записи #' . $request[ Report::FIELD_ID ] . ': ' . $post->get_error_code(), array( 'status' => 500 ) );	
		
		// Проверяем тип записи
		if ( $post->post_type != Report::CPT )
			return new WP_Error( 'rest_post_error', 'Ошибка типа записи #' . $request[ Report::FIELD_ID ], array( 'status' => 403 ) );	
		
		// Читаем исходные данные и формируем массив для обновления
		$postData = array( 'ID'	=> $post->ID );
		
		//  Дата в UTC
		if ( isset( $request[ Report::FIELD_DATE ] )  )
		{
			$date = date_create_from_format( 'd.m.Y', $request[ Report::FIELD_DATE ] );
			$date = date_format($date, 'Y-m-d H:i:s');
			$postData['post_date'] = $date;
		}
		
		// Комментарий
		if ( isset( $request[ Report::FIELD_COMMENT ] ) )
			$postData['post_title'] = $request[ Report::FIELD_COMMENT ];
			
		// Обновляем запись если были изменены комментарий или дата 	
		if ( count( $postData ) > 1 ) 
			wp_update_post( $postData );
		
		// Обновляем мета-поля
		if ( isset( $request[ Report::FIELD_PROJECT ] ) )
		{
			update_post_meta( $post->ID, Report::META_PROJECT, $request[ Report::FIELD_PROJECT ] );
		}

		if ( isset( $request[ Report::FIELD_QUO ] ) )
		{
			$quo =  (float) $request[ Report::FIELD_QUO ];
			update_post_meta( $post->ID, Report::META_QUO, $quo );
		}			
		
		if ( isset( $request[ Report::FIELD_RATE ] ) )
		{
			$rate =  (float) $request[ Report::FIELD_RATE ];
			update_post_meta( $post->ID, Report::META_RATE, $rate );
		}		
	
		// Получаем обновленную запись
		$response = $this->get_item( array( 'id' => $post->ID ) );
 
        // Возвращаем обновленную запись
        return $response;
    }
	
    /**
     * Проверка прав на удаление записи
     *
     * @param WP_REST_Request $request Текущий запрос
     */
    public function delete_item_permissions_check( $request ) 
	{
		// Проверка авторизации пользователя
		if ( ! is_user_logged_in() )
			return new \WP_Error( 'rest_unauthorized', 'Вы не авторизованы!', array( 'status' => '401' ) );
		
		// Проверка прав на доступ к записи
		if ( ! RoleManager::canDo( $request[ Report::FIELD_ID ], get_current_user_id(), RoleManager::DELETE_ACTIVITY, RoleManager::DELETE_OTHER_ACTIVITIES ) ) 
			return new \WP_Error( 'rest_forbidden', 'У вас нет прав на удаление записи #' . $request[ Report::FIELD_ID ], array( 'status' => '403' ) );		

		return true;
    }	
	
    /**
     * Удаление записи
     *
     * @param WP_REST_Request $request Current request.
     */
    public function delete_item( $request ) 
	{		
		// Проверка ID
		if ( empty( $request[ Report::FIELD_ID ] ) )
			return new \WP_Error( 'rest_post_bad_id', 'Пустой ID записи', array( 'status' => '400' ) );	
		
		// Получаем запись
		$post = get_post( $request[ Report::FIELD_ID ] );
		if ( is_wp_error( $post ) )
			return new WP_Error( 'rest_post_error', 'Ошибка доступа к записи #' . $request[ Report::FIELD_ID ] . ': ' . $post->get_error_code(), array( 'status' => 500 ) );	
		
		// Проверяем тип записи
		if ( $post->post_type != Report::CPT )
			return new WP_Error( 'rest_post_error', 'Ошибка типа записи #' . $request[ Report::FIELD_ID ], array( 'status' => 403 ) );	
		
		// Удаляем запись
		wp_delete_post( $post->ID, true );
	
		// Формируем ответ
		$response = new \WP_REST_Response();
		$response->set_data( array( 'deleted' => true, 'id' => $post->ID ) );
 
        // Возвращаем обновленную запись
        return $response;
    }	
}