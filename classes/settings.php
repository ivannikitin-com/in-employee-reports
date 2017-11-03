<?php
/**
 * Класс реализует загрузку и сохранение любых параметров
 */
namespace INER;
class Settings
{
	/**
	 * Основной класс плагина
	 * @var Plugin
	 */
	protected $plugin;	
	
	/**
	 * Название опции в Wordpress
	 * @var string
	 */
	protected $_name;	
	
	
	/**
	 * Массив хранения параметров
	 * @var mixed
	 */
	protected $_params;
	
	/**
	 * Конструктор
	 * инициализирует параметры и загружает данные
	 * @param string 		$optionName		Название опции в Wordpress, по умолчанию используется имя класса
	 * @param R7K12\Plugin	$plugin			Ссылка на основной объект плагина
	 */
	public function __construct( $optionName = '', $plugin )
	{
		if ( empty ( $optionName ) ) $optionName = get_class( $this );
		$this->_name = $optionName;
		
		$this->plugin = $plugin;
		
		// Загружаем параметры
		$this->load();
		
		// Если это работа в админке
		if ( is_admin() )
		{
			// Стили для админки
			 wp_enqueue_style( INER, $this->plugin->url . 'assets/css/admin.css' );
			
			// Страница настроек
			add_action( 'admin_menu', array( $this, 'addSettingsPage' ) );
		}
		
	}
	
	/**
	 * Загрузка параметров в массив из БД Wordpress
	 */
	public function load()
	{
		$this->_params = get_option( $this->_name, array() );
	}
	
	/**
	 * Сохранение параметров в БД Wordpress
	 */
	public function save()
	{
		update_option( $this->_name, $this->_params );
	}

	/**
	 * Чтение параметра
	 * @param string	$param		Название параметра
	 * @param mixed 	$default	Значение параметра по умолчанию, если его нет или он пустой
	 * @return mixed				Возвращает параметр
	 */
	public function get( $param, $default = false )
	{
		if ( ! isset( $this->_params[ $param ] ) )
			return $default;
		
		if ( empty( $this->_params[ $param ] ) )
			return $default;
		
		return $this->_params[ $param ];
	}
	
	/**
	 * Сохранение параметра
	 * @param string	$param		Название параметра
	 * @param mixed 	$value		Значение параметра
	 */
	public function set( $param, $value )
	{
		$this->_params[ $param ] = $value;
	}
	
	/**
	 * Чтение свойства
	 * @param string	$param		Название параметра
	 */
	public function __get( $param )
	{
		return $this->get( $param );
	}
	/**
	 * Запись свойства
	 * @param string	$param		Название параметра
	 */
	public function __set( $param, $value )
	{
		return $this->set( $param, $value );
	}	
	

	/** ==========================================================================================
	 * Добавляет страницу настроект плагина в меню типа данных
	 */
	public function addSettingsPage()
	{
		add_submenu_page(
			'edit.php?post_type=' . Report::CPT,
			'Настройки отчетов сотрудников',
			'Настройки',
			RoleManager::READ_ACTIVITY,
			INER,
			array( $this, 'showSettingsPage' )
		);		
	}
	
	/** 
	 * Выводит страницу настроект плагина
	 */
	public function showSettingsPage( )
	{	
		$nonceField = INER;
		$nonceAction = 'save-settings';
		$nonceError = false;
		
		// Обработка формы
		if ( $_SERVER['REQUEST_METHOD'] == 'POST' )
		{
			if ( ! isset( $_POST[$nonceField] ) || ! wp_verify_nonce( $_POST[$nonceField], $nonceAction ) ) 
			{
				$nonceError = true;
			} 
			else 
			{
				// process form data
				//$this->set( CRM::PROJECTKEY_PARAM, 				sanitize_text_field( $_POST['r7k12ProjectKey'] ) );	


				// Save all data
				$this->save();					
				

			}		
		}
		
?>
<h1>Отчеты сотрудников</h1>
<p>Параметры плашина in-employee-reports</p>
<?php if ( $nonceError ) echo 'Ошибка поля nonce!'; ?>

<form id="iner-settings" action="<?php echo $_SERVER['REQUEST_URI']?>" method="post">
	<?php wp_nonce_field( $nonceAction, $nonceField ) ?>
	

	
	<h2>Пароли приложений</h2>
    <p>Этот параметр позволяет создать пароли для подключения к REST API отчетов внешних приложений: Excel и др.</p>
	<div class="iner-field">
		<label for="r7k12CF7_name"><?php esc_html_e( 'Customer name fields', R7K12 ) ?></label>
		<div class="r7k12-input">
			<input id="r7k12CF7_name" name="r7k12CF7_name" type="text" 
				   value="<?php echo esc_attr( $this->get( 1 ) ) ?>" />
			<p><?php esc_html_e( 'Specify form fields with customer name delimeted by comma. For example: name-123, name-345', INER ) ?></p>
		</div>
	</div>
	
	<?php submit_button() ?>
</form>
<?php	
	}
	

	
}