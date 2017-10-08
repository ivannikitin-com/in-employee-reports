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
		// Регистрация CSS
		wp_register_style( 'handsontable', $this->plugin->url . 'assets/handsontable/dist/handsontable.full.min.css', array(), '0.34.4');
		wp_register_style( 'in-employee-reports-frontend', $this->plugin->url . 'assets/css/frontend.css', array( 'wp-jquery-ui-dialog' ), '2.0');

		// Загрузка CSS
		wp_enqueue_style( 'handsontable' );
		wp_enqueue_style( 'in-employee-reports-frontend' );

		// Регистрация скриптов
		//wp_register_script( 'numeral-js', $this->plugin->url . 'assets/Numeral-js/min/numeral.min.js', array( ), '2.0.6', true );
		//wp_register_script( 'numeral-js-locales', $this->plugin->url . 'assets/Numeral-js/min/locales.min.js', array( 'numeral-js' ), '2.0.6', true );
		wp_register_script( 'handsontable', $this->plugin->url . 'assets/handsontable/dist/handsontable.full.min.js', array( 'jquery' ), '0.34.4', true );
		wp_register_script( 'numbro-ru', $this->plugin->url . 'assets/handsontable/dist/numbro/languages/ru-RU.min.js', array( 'handsontable' ), '0.34.4', true );
		wp_register_script( 'in-employee-reports', $this->plugin->url . 'assets/js/frontend.js', array( 'jquery', 'jquery-ui-dialog', 'handsontable', 'numbro-ru' ), '2.0', true );

		
		// Данные для скрипта
		$innerREST = array(
			'debug'	=> WP_DEBUG,
			'root'	=> esc_url_raw( rest_url() ),
			'nonce'	=> wp_create_nonce( 'wp_rest' ),
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
		// Получаем aтрибуты вызова и пропускаем их через фильтр shortcode_atts_$shortcode
		// https://codex.wordpress.org/Function_Reference/shortcode_atts
		$atts = shortcode_atts( array(
			'foo' => 'no foo',
			'baz' => 'default baz'
		), $atts, self::SHORTCODE );		

		$html = <<<END_OF_HTML
<section class="inerFrontend">
	<div id="inerMessage">Сообщение</div>
	
	<div id="inerTotals">
		Итого: Количество: <span id="totalQuo">0</span>, Сумма: <span id="totalSum">0</span> руб. 
	</div>
	<div id="inerHot"></div>
</section>
END_OF_HTML;

		return $html;
    }	
}	