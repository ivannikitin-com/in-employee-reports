<?php
/**
 * Основной класс плагина
 */
namespace INER;
class Plugin
{
	/**
	 * Логи
	 * @static
	 */
	const ACTIVITY_LOG 	= 'activity.log';	
	const ERROR_LOG 	= 'error.log';
	
	/**
	 * Путь к файлам плагина
	 * @var string
	 */
	public $path;
	
	/**
	 * URL к файлам плагина
	 * @var string
	 */
	public $url;	
		
	/**
	 * Конструктор
	 */
	public function __construct( $pluginPath, $pluginURL )
	{
		// Инициализация свойств
		$this->path = $pluginPath;	// Путь к файлам плагина
		$this->url = $pluginURL;	// URL к файлам плагина
		
		// Инициализация плагина по хуку init
		add_action( 'init', array( $this, 'init' ) );
	}		
	
	/**
	 * Экземпляр класса отчетов
	 * @var INER\Report
	 */
	public $report;	
	
	/**
	 * Экземпляр класса фронтэнда
	 * @var INER\Frontend
	 */
	public $frontend;		
	
	/**
	 * Экземпляр класса параметров
	 * @var INER\Frontend
	 */
	public $settings;	
	
	
	/**
	 * Инициализация плагина
	 */
	public function init()
	{
		$this->report 	= new Report( $this );
		$this->frontend	= new Frontend( $this );
		$this->settings	= new Settings( $this );
	}
	
	/**
	 * Запись логов
	 * @param string	$log		Имя файла лога
	 * @param string	$message	Сообщение для вывода
	 */
	private function log( $log, $message )
	{
		// Выводим логи только в режиме отладки
		if ( ! WP_DEBUG )
			return;
		
		// Добавляем в сообщение дату, время и разделитель записей
		$message = '[ ' . date( 'd.m.Y H:i:s' ) . ' ]' . PHP_EOL . $message . PHP_EOL . PHP_EOL;
		
		// Файл пишем в папку плагина
		$log = $this->path . $log;
		file_put_contents( $log, $message, FILE_APPEND );
	}
	
	/**
	 * Запись в лог активности
	 * @param string	$message	Сообщение для вывода
	 */
	public function activityLog( $message )
	{	
		$this->log( self::ACTIVITY_LOG, $message );
	}
	
	/**
	 * Запись в лог ошибок
	 * @param string	$message	Сообщение для вывода
	 */
	public function errorLog( $message )
	{	
		$this->log( self::ERROR_LOG, $message );
	}	
}