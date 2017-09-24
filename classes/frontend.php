<?php
/**
 * Класс реализует вывод отчетов на фронт-энд страницах сайта
 */
namespace INER;
class Frontend extends Base
{
	/**
	 * Конструктор
	 * 
	 * @param INER\Plugin	$plugin Ссылка на основной объект плагина 
	 */
	public function __construct( $plugin )
	{
		// Родительский конструктор
		parent::__construct( $plugin );
	}
	
	
}	