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
	 * Массив хранения паролей приложений
	 * @var mixed
	 */
	protected $appPasswords;
	
	/**
	 * Конструктор
	 * инициализирует параметры и загружает данные
	 * @param R7K12\Plugin	$plugin			Ссылка на основной объект плагина
	 */
	public function __construct( $plugin )
	{
		$this->_name = get_class( $this );
		$this->plugin = $plugin;
		
		// Загружаем параметры
		$this->load();
		
		// Если это работа в админке
		if ( is_admin() )
		{
			// Стили для админки загружается классом report 
			// wp_enqueue_style( INER, $this->plugin->url . 'assets/css/admin.css' );
			
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
		
		// Загрузка паролей приложений пользователя
		$metaFields = get_user_meta( get_current_user_id(), RoleManager::APP_PASS_USER_META, true );
		$this->appPasswords = ( !empty( $metaFields ) && strlen($metaFields) > 3 ) ? unserialize( $metaFields ) : array();		
	}
	
	/**
	 * Сохранение параметров в БД Wordpress
	 */
	public function save()
	{
		update_option( $this->_name, $this->_params );
		
		// Сохранение паролей приложения
		update_user_meta( get_current_user_id(), RoleManager::APP_PASS_USER_META, serialize( $this->appPasswords ) );
		delete_transient( RoleManager::APP_PASS_CACHE );
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
				// Добавление пароля приложения
				if ( $_POST['submit'] == 'Создать' )
				{
					$application = sanitize_text_field( $_POST[ 'iner_app_name' ] );
					if ( !empty( $application ) )
					{
						$key = RoleManager::generateKey();
						$this->appPasswords[ $key ] = array
						(
							RoleManager::APP_PASS_USER_ID 	=> get_current_user_id(),
							RoleManager::APP_PASS_NAME 		=> $application,
							RoleManager::APP_PASS_SECRET 	=> RoleManager::generateKey( 'secret' )				
						);					
					}
				}
				
				// Удаление пароля приложения
				if ( in_array( 'X', $_POST ) )
				{
					$postFlip = array_flip( $_POST );
					$key = $postFlip[ 'X' ];
					unset( $this->appPasswords[ $key ] );
				}				

				// Save all data
				$this->save();
			}		
		}
		
?>
<h1>Отчеты сотрудников</h1>
<p>Параметры плагина in-employee-reports</p>
<?php if ( $nonceError ) echo '<p class="error">Ошибка поля nonce!</p>'; ?>

<form id="iner-settings" action="<?php echo $_SERVER['REQUEST_URI']?>" method="post">
	<?php wp_nonce_field( $nonceAction, $nonceField ) ?>
	
	<fieldset>
		<h2>Пароли приложений</h2>
		<p>Эта функция позволяет создать пароли для подключения к REST API отчетов внешних приложений: Excel и др.<br>
		   Для создания пароля введите произвольное имя приложения и нажмите кнопку [Создать].<br>
		   Мы рекомендуем создавать для каждого приложения или сервиса свой пароль и не использовать один и тот же пароль дважды<br>
		   В прилоджении в качестве логина указите значение ключа, в качестве пароля - секретного ключа<br>
		   При необходимости удалите пароль, нажав на кнопку [х]</p>
		   
		<div class="iner-field">
			<label for="iner_app_name">Приложение</label>
			<div class="iner-input">
				<input id="iner_app_name" name="iner_app_name" type="text" />
			</div>	
			<?php submit_button( 'Создать', 'secondary' ) ?>
		</div>

		<?php if ( count( $this->appPasswords ) ): ?>
			<table id="iner-application-passwords">
				<thead>
					<tr>
						<td>Приложение</td>
						<td>Ключ</td>
						<td colspan="2">Секретный ключ</td>
					</tr>
				</thead>
				<tbody>
					<?php foreach( $this->appPasswords as $key => $value ): ?>
						<tr>
							<td><?php echo $value[ RoleManager::APP_PASS_NAME ] ?></td>
							<td><?php echo $key ?></td>
							<td><?php echo $value[ RoleManager::APP_PASS_SECRET ] ?></td>
							<td><?php submit_button( 'X', 'delete', $key ) ?></td>
						</tr>
					<?php endforeach ?>
				</tbody>
			</table>
		<?php endif ?>
	
	</fieldset>
	
	<?php submit_button() ?>
</form>
<?php	
	}
	

	
}