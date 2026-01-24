<?php
/**
 * Основной класс плагина
 */
namespace InEmployeeReports;

class Plugin {


	/**
	 * Путь к файлам плагина
	 *
	 * @var string
	 */
	public $path;

	/**
	 * URL к файлам плагина
	 *
	 * @var string
	 */
	public $url;

	/**
	 * Конструктор
	 */
	public function __construct( $pluginPath, $pluginURL ) {
		// Инициализация свойств
		$this->path = $pluginPath;  // Путь к файлам плагина
		$this->url  = $pluginURL;    // URL к файлам плагина

		// Инициализация плагина по хуку init если пользователь авторизован
		if ( wp_get_current_user() ) {
			add_action( 'init', array( $this, 'init' ) );
		}
	}

	/**
	 * Экземпляр класса отчетов
	 *
	 * @var Report
	 */
	public $report;

	/**
	 * Экземпляр класса фронтэнда
	 *
	 * @var Frontend
	 */
	public $frontend;

	/**
	 * Экземпляр класса параметров
	 *
	 * @var Settings
	 */
	public $settings;


	/**
	 * Инициализация плагина
	 */
	public function init() {
		$this->report   = new Report( $this );
		$this->frontend = new Frontend( $this );
		$this->settings = new Settings( $this );
	}

}
