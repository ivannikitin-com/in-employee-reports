<?php
/**
 * Класс реализует вывод отчетов на фронт-энд страницах сайта
 *
 * Демо таблиц
 * http://ivannikitin.ivan.wp-server.ru/wp-content/plugins/in-employee-reports/assets/handsontable/
 */
namespace INER;
class Frontend extends Base
{
	/**
	 * Шорткод
	 */	
	const SHORTCODE = 'in_employee_reports';
	
	/**
	 * Конструктор
	 * 
	 * @param INER\Plugin	$plugin Ссылка на основной объект плагина 
	 */
	public function __construct( $plugin )
	{
		// Родительский конструктор
		parent::__construct( $plugin );
		
		// Загрузка активов (CSS, JS) на фронтэнде
		add_action( 'wp_enqueue_scripts', array( $this, 'loadAssets' ) );
		
		// Регистрация шорткода
		add_shortcode( self::SHORTCODE, array( $this, 'getHTML' ) );
		
	}
	
	/**
	 * Загрузка CSS и JS
	 */	
    public function loadAssets()
    {	
		// Загрузка только для зарегистрированных пользователей
		if ( ! is_user_logged_in() ) 
			return;
		
		// Регистрация CSS
		wp_register_style( 'handsontable', $this->plugin->url . 'assets/handsontable/dist/handsontable.full.min.css', array(), '0.34.4');
		wp_register_style( 'in-employee-reports-frontend', $this->plugin->url . 'assets/css/frontend.css', array( 'wp-jquery-ui-dialog' ), '2.0');

		// Загрузка CSS
		wp_enqueue_style( 'handsontable' );
		wp_enqueue_style( 'in-employee-reports-frontend' );

		// Регистрация скриптов
		wp_register_script( 'handsontable', $this->plugin->url . 'assets/handsontable/dist/handsontable.full.min.js', array( 'jquery' ), '0.34.4', true );
		wp_register_script( 'numbro-ru', $this->plugin->url . 'assets/handsontable/dist/numbro/languages/ru-RU.min.js', array( 'handsontable' ), '0.34.4', true );
		wp_register_script( 'in-employee-reports', $this->plugin->url . 'assets/js/frontend.js', array( 'jquery', 'jquery-ui-dialog', 'handsontable', 'numbro-ru' ), '2.0', true );

		
		// Список пользователей для показа в списке
		$employees = array();	
		if ( current_user_can( 'administrator' ) ) 
		{
			// Все роли плагина
			$allRoles = array_keys( RoleManager::$roles );
			// Добавим админа
			$allRoles[] = 'administrator';
			// Для администратора выбираем всех сотрудгников и подрядчиков
			$user_query = new \WP_User_Query( array( 'role__in' => $allRoles ) );
			$employees[0] = 'Все';
		}
		else
		{
			// Для обычных пользователей берем данные из RoleManager
			$user_query = new \WP_User_Query( array( 'include' => RoleManager::getAllowedUsers( get_current_user_id() ) ) );
		}
		if ( ! empty( $user_query->results ) ) 
		{
			foreach ( $user_query->results as $user ) 
				$employees[ $user->ID ] = $user->display_name; 
		}
		
		// Спиок проектов для автозаполнения
		$projectList = apply_filters( 'iner_projects', array( 'Оклад', 'Координация проектов' ), get_current_user_id() );
		sort( $projectList );
			
		// Данные для скрипта
		$innerREST = array(
			'debug'			=> WP_DEBUG,
			'root'			=> esc_url_raw( rest_url() ),
			'nonce'			=> wp_create_nonce( 'wp_rest' ),
			'currentUserId'	=> get_current_user_id(),
			'employees' 	=> $employees,
			'projects'		=> $projectList,
		);
		wp_localize_script( 'in-employee-reports', 'innerREST', $innerREST );

		// Загрузка скриптов
		wp_enqueue_script( 'in-employee-reports' );
	}

	/**
	 * Возвращает HTML представления класса по шорткоду
	 */	
    public function getHTML( $atts, $content='' )
    {
		
		// Проверяем аутентификацию пользователя, если нет - на авторизацию!
		if ( ! is_user_logged_in() ) 
		{
		   auth_redirect();
		}		
		
		// Получаем aтрибуты вызова и пропускаем их через фильтр shortcode_atts_$shortcode
		// https://codex.wordpress.org/Function_Reference/shortcode_atts
		$atts = shortcode_atts( array(
			'foo' => 'no foo',
			'baz' => 'default baz'
		), $atts, self::SHORTCODE );		

		$year = date('Y');
		$html = <<<END_OF_HTML
<section id="inerFrontend">
	<div id="inerMessage">Сообщение</div>
	<div id="inerFilter">
		<label for="inerEmployee">Сотрудник</label>
		<select id="inerEmployee"></select>
		
		<span class="separator">&nbsp;</span>
		
		<label for="inerMonth">Месяц</label>
		<select id="inerMonth">
			<option value="1">Январь</option>
			<option value="2">Февраль</option>
			<option value="3">Март</option>
			<option value="4">Апрель</option>
			<option value="5">Май</option>
			<option value="6">Июнь</option>
			<option value="7">Июль</option>
			<option value="8">Август</option>
			<option value="9">Сентябрь</option>
			<option value="10">Октябрь</option>
			<option value="11">Ноябрь</option>
			<option value="12">Декабрь</option>
		</select>
		
		<span class="separator">&nbsp;</span>
		
		<label for="inerYear">Год</label>
		<input id="inerYear" type="number" min="2011" max="2025" step="1" value="{$year}" />
		
		<button id="inerReload">Показать</button>
	</div>
	<div id="inerTotals">
		Итого: 
		Количество: <span id="totalQuo">0</span> 
		<span style="display:block-inline;width:20px">&nbsp;</span> 
		Сумма: <span id="totalSum">0</span> 
	</div>
	<div id="inerHot"></div>
</section>
END_OF_HTML;

		return $html;
    }	
}	