<?php
/**
 * Базовый класс
 */
namespace INER;
class Base
{
	/**
	 * Основной класс плагина
	 * @var Plugin
	 */
	protected $plugin;	
	
	/**
	 * Конструктор
	 * @param INER\Plugin	$plugin Ссылка на основной объект плагина
	 */
	public function __construct( $plugin )
	{
        // Сохраняем ссылку на основной объект плагина
		$this->plugin = $plugin;	
	}
	
}