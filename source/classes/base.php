<?php
/**
 * Базовый класс
 */
namespace InEmployeeReports;

class Base {

	/**
	 * Основной класс плагина
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Конструктор
	 *
	 * @param Plugin $plugin Ссылка на основной объект плагина
	 */
	public function __construct( $plugin ) {
		// Сохраняем ссылку на основной объект плагина
		$this->plugin = $plugin;
	}

}
