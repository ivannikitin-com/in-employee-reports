<?php
/**
 * Класс реализует загрузку и сохранение параметров плагина
 */
namespace InEmployeeReports;

class Settings {

	/**
	 * Основной класс плагина
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Название опции в WordPress
	 *
	 * @var string
	 */
	protected $_name;


	/**
	 * Массив хранения параметров
	 *
	 * @var mixed
	 */
	protected $_params;

	/**
	 * Конструктор
	 * инициализирует параметры и загружает данные
	 *
	 * @param Plugin $plugin Ссылка на основной объект плагина
	 */
	public function __construct( $plugin ) {
		$this->_name  = get_class( $this );
		$this->plugin = $plugin;

		// Загружаем параметры
		$this->load();

		// Если это работа в админке
		if ( is_admin() ) {
			// Страница настроек
			add_action( 'admin_menu', array( $this, 'addSettingsPage' ) );
		}

	}

	/**
	 * Загрузка параметров в массив из БД WordPress
	 */
	public function load() {
		$this->_params = get_option( $this->_name, array() );
	}

	/**
	 * Сохранение параметров в БД WordPress
	 */
	public function save() {
		update_option( $this->_name, $this->_params );
	}

	/**
	 * Чтение параметра
	 *
	 * @param string $param   Название параметра
	 * @param mixed  $default Значение параметра по умолчанию, если его нет или он пустой
	 * @return mixed Возвращает параметр
	 */
	public function get( $param, $default = false ) {
		if ( ! isset( $this->_params[ $param ] ) ) {
			return $default;
		}

		if ( empty( $this->_params[ $param ] ) ) {
			return $default;
		}

		return $this->_params[ $param ];
	}

	/**
	 * Сохранение параметра
	 *
	 * @param string $param Название параметра
	 * @param mixed  $value Значение параметра
	 */
	public function set( $param, $value ) {
		$this->_params[ $param ] = $value;
	}

	/**
	 * Чтение свойства
	 *
	 * @param string $param Название параметра
	 */
	public function __get( $param ) {
		return $this->get( $param );
	}

	/**
	 * Запись свойства
	 *
	 * @param string $param Название параметра
	 * @param mixed  $value Значение параметра
	 */
	public function __set( $param, $value ) {
		return $this->set( $param, $value );
	}


	/**
	 * Добавляет страницу настроек плагина в меню типа данных
	 */
	public function addSettingsPage() {
		add_submenu_page(
			'edit.php?post_type=' . Report::CPT,
			'Настройки отчетов сотрудников',
			'Настройки',
			Permissions_Manager::READ_ACTIVITY,
			INER,
			array( $this, 'showSettingsPage' )
		);
	}

	/**
	 * Выводит страницу настроек плагина
	 */
	public function showSettingsPage() {
		$nonceField  = INER;
		$nonceAction = 'save-settings';
		$nonceError  = false;

		// Обработка формы
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			if ( ! isset( $_POST[ $nonceField ] ) || ! wp_verify_nonce( $_POST[ $nonceField ], $nonceAction ) ) {
				$nonceError = true;
			} else {
				// Здесь можно обрабатывать сохранение дополнительных настроек в будущем
				$this->save();
			}
		}

		?>
<div class="wrap">
	<h1>Отчеты сотрудников</h1>
	<p>Параметры плагина in-employee-reports</p>
	
		<?php
		if ( $nonceError ) {
			echo '<div class="notice notice-error"><p>Ошибка поля nonce!</p></div>';
		}
		?>

	<form id="iner-settings" action="<?php echo esc_url( $_SERVER['REQUEST_URI'] ); ?>" method="post">
		<?php wp_nonce_field( $nonceAction, $nonceField ); ?>
		
		<div class="notice notice-info inline">
			<h2>Пароли приложений</h2>
			<p>Для интеграции с внешними приложениями (например, Excel, Google Sheets и другие) используйте <strong>встроенные пароли приложений WordPress</strong>.</p>
			<p>Каждый пользователь может самостоятельно сгенерировать пароль приложения в своем профиле:</p>
			<ol>
				<li>Перейдите в <a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>">Пользователи → Профиль</a></li>
				<li>Прокрутите вниз до раздела "Пароли приложений"</li>
				<li>Введите имя приложения и нажмите "Добавить новый пароль приложения"</li>
				<li>Используйте сгенерированный пароль для аутентификации в REST API</li>
			</ol>
			<p><strong>Важно:</strong> Пароли приложений WordPress обеспечивают безопасный доступ к API без раскрытия основного пароля пользователя.</p>
		</div>
		
		<!-- Здесь можно добавить дополнительные настройки плагина в будущем -->
		
		<?php submit_button(); ?>
	</form>
</div>
		<?php
	}

}
