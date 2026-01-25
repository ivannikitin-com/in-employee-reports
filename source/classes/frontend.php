<?php
/**
 * Класс реализует вывод отчетов на фронт-энд страницах сайта
 *
 * Демо таблиц
 * http://ivannikitin.ivan.wp-server.ru/wp-content/plugins/in-employee-reports/assets/handsontable/
 */
namespace InEmployeeReports;

class Frontend extends Base {

	/**
	 * Шорткод
	 */
	const SHORTCODE = 'in_employee_reports';

	/**
	 * Конструктор
	 *
	 * @param Plugin $plugin Ссылка на основной объект плагина
	 */
	public function __construct( $plugin ) {
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
	public function loadAssets() {
		// Загрузка только для зарегистрированных пользователей
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Регистрация CSS
		wp_register_style( 'ag-grid-core', $this->plugin->url . 'assets/dist/ag-grid.min.css', array(), '31.0' );
		wp_register_style( 'ag-grid-theme', $this->plugin->url . 'assets/dist/ag-theme-alpine.min.css', array( 'ag-grid-core' ), '31.0' );
		wp_register_style( 'in-employee-reports-frontend', $this->plugin->url . 'assets/css/frontend.css', array( 'wp-jquery-ui-dialog' ), '3.0' );

		// Загрузка CSS перенесена в метод getHTML, чтобы не грузить их на всех страницах

		// Регистрация скриптов
		wp_register_script( 'ag-grid', $this->plugin->url . 'assets/dist/ag-grid-community.min.js', array(), '31.0', true );
		wp_register_script( 'in-employee-reports', $this->plugin->url . 'assets/js/frontend.js', array( 'jquery', 'jquery-ui-dialog', 'ag-grid' ), '3.0', true );

		// Список пользователей для показа в списке
		$employees = array();
		if ( current_user_can( 'administrator' ) ) {
			// Все роли плагина
			$allRoles = array_keys( Permissions_Manager::$roles );
			// Добавим админа
			$allRoles[] = 'administrator';
			// Для администратора выбираем всех сотрудников
			$user_query   = new \WP_User_Query( array( 'role__in' => $allRoles ) );
			$employees[0] = '_Все_';
		} else {
			// Для обычных пользователей берем данные из Permissions_Manager
			$user_query = new \WP_User_Query( array( 'include' => Permissions_Manager::getAllowedUsers( get_current_user_id() ) ) );
		}

		if ( ! empty( $user_query->results ) ) {
			foreach ( $user_query->results as $user ) {
				$employees[ $user->ID ] = $user->display_name;
			}
		}

		// Сортируем массив по имени здесь невозможна, поскольку в JS нет ассоциалитвных массивов, а после десериализации
		// этот массив становится объектом, отсортированным по ключу. Сотрдировка должна быть на frontend'е
		// asort( $employees );

		// Список проектов для автозаполнения - получаем из базы данных
		$projectList = $this->getProjectsList();
		// Применяем фильтр для возможности модификации списка
		$projectList = apply_filters( 'iner_projects', $projectList, get_current_user_id() );
		sort( $projectList );

		// Получаем имя текущего пользователя
		$current_user = wp_get_current_user();
		$current_user_name = $current_user->display_name;

		// Данные для скрипта
		$innerREST = array(
			'debug'         => WP_DEBUG,
			'root'          => esc_url_raw( rest_url() ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'currentUserId' => get_current_user_id(),
			'currentUserName' => $current_user_name,
			'employees'     => $employees,
			'projects'      => $projectList,
		);
		wp_localize_script( 'in-employee-reports', 'innerREST', $innerREST );

		// Загрузка скриптов перенесена в метод getHTML, чтобы не грузить их на всех страницах и не было ошибок отсуствия контейнера с таблицей
	}

	/**
	 * Возвращает HTML представления класса по шорткоду
	 */
	public function getHTML( $atts, $content = '' ) {

		// Проверяем аутентификацию пользователя, если нет - на авторизацию!
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		// Получаем aтрибуты вызова и пропускаем их через фильтр shortcode_atts_$shortcode
		// https://codex.wordpress.org/Function_Reference/shortcode_atts
		$atts = shortcode_atts(
			array(
				'foo' => 'no foo',
				'baz' => 'default baz',
			),
			$atts,
			self::SHORTCODE
		);

		// Загрузка CSS
		wp_enqueue_style( 'ag-grid-core' );
		wp_enqueue_style( 'ag-grid-theme' );
		wp_enqueue_style( 'in-employee-reports-frontend' );

		// Загрузка скриптов
		wp_enqueue_script( 'in-employee-reports' );

		$year = gmdate( 'Y' );
		$html = <<<END_OF_HTML
<section id="inerFrontend">
	<div id="inerMessage" style="display:none;">Сообщение</div>
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
		<input id="inerYear" type="number" min="2011" max="2030" step="1" value="{$year}" />
		
		<button id="inerReload" class="button">Показать</button>
		<button id="inerExport" class="button">Экспорт в CSV</button>
	</div>
	<div id="inerRowActions">
		<button id="inerAddRow" class="button">Добавить строку</button>
		<button id="inerDeleteRow" class="button">Удалить строку</button>
	</div>
	<div id="inerTotals">
		Итого: 
		Количество: <span id="totalQuo">0</span> 
		<span style="display:inline-block;width:20px">&nbsp;</span> 
		Сумма: <span id="totalSum">0</span> 
	</div>
	<div id="inerGrid" class="ag-theme-alpine" style="height: 600px; width: 100%;"></div>
</section>
END_OF_HTML;

		return $html;
	}

	/**
	 * Получает список всех уникальных проектов из базы данных
	 *
	 * @return array Массив уникальных названий проектов
	 */
	private function getProjectsList() {
		global $wpdb;

		// Получаем все уникальные значения мета-поля _activity_project
		$meta_key = Report::META_PROJECT;
		$post_type = Report::CPT;

		// SQL запрос для получения уникальных значений мета-поля
		$query = $wpdb->prepare(
			"SELECT DISTINCT pm.meta_value 
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = %s
			AND p.post_type = %s
			AND p.post_status = 'publish'
			AND pm.meta_value != ''
			AND pm.meta_value IS NOT NULL
			ORDER BY pm.meta_value ASC",
			$meta_key,
			$post_type
		);

		$results = $wpdb->get_col( $query );

		// Очищаем и фильтруем результаты
		$projects = array();
		foreach ( $results as $project ) {
			$project = trim( $project );
			if ( ! empty( $project ) ) {
				$projects[] = $project;
			}
		}

		// Если проектов нет, возвращаем базовый список
		if ( empty( $projects ) ) {
			$projects = array( 'Оклад', 'Координация проектов' );
		}

		return $projects;
	}
}
