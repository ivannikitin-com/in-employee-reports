/**
 * Фронтенд скрипт для отображения отчетов с использованием AG Grid
 */
jQuery( function( $ ) {
	'use strict';

	// === Элементы UI ===
	const elements = {
		totalQuo: $( '#totalQuo' ),
		totalSum: $( '#totalSum' ),
		selEmployee: $( '#inerEmployee' ),
		selMonth: $( '#inerMonth' ),
		txtYear: $( '#inerYear' ),
		btnReload: $( '#inerReload' ),
		btnExport: $( '#inerExport' ),
		message: $( '#inerMessage' ),
		gridContainer: document.getElementById( 'inerGrid' )
	};

	// === Конфигурация AG Grid ===
	const columnDefs = [
		{
			field: 'id',
			headerName: 'Код',
			width: 80,
			editable: false,
			filter: 'agNumberColumnFilter',
			sortable: true
		},
		{
			field: 'employee',
			headerName: 'Сотрудник',
			width: 150,
			editable: false,
			filter: 'agTextColumnFilter',
			sortable: true
		},
		{
			field: 'date',
			headerName: 'Дата',
			width: 120,
			editable: true,
			sortable: true,
			valueFormatter: function( params ) {
				if ( ! params.value ) {
					return '';
				}
				return moment( params.value ).format( 'DD.MM.YYYY' );
			},
			valueSetter: function( params ) {
				const newDate = moment( params.newValue, 'DD.MM.YYYY' );
				if ( newDate.isValid() ) {
					params.data.date = newDate.format( 'YYYY-MM-DD' );
					return true;
				}
				return false;
			}
		},
		{
			field: 'project',
			headerName: 'Проект',
			width: 200,
			editable: true,
			filter: 'agTextColumnFilter',
			sortable: true,
			cellEditor: 'agSelectCellEditor',
			cellEditorParams: {
				values: innerREST.projects || []
			}
		},
		{
			field: 'quo',
			headerName: 'Кол.',
			width: 100,
			editable: true,
			sortable: true,
			filter: 'agNumberColumnFilter',
			valueFormatter: function( params ) {
				return params.value ? parseFloat( params.value ).toFixed( 2 ) : '0.00';
			},
			valueSetter: function( params ) {
				const value = parseFloat( params.newValue );
				if ( ! isNaN( value ) ) {
					params.data.quo = value;
					return true;
				}
				return false;
			}
		},
		{
			field: 'rate',
			headerName: 'Ставка',
			width: 100,
			editable: true,
			sortable: true,
			filter: 'agNumberColumnFilter',
			valueFormatter: function( params ) {
				return params.value ? parseFloat( params.value ).toFixed( 2 ) + ' ₽' : '0.00 ₽';
			},
			valueSetter: function( params ) {
				const value = parseFloat( params.newValue );
				if ( ! isNaN( value ) ) {
					params.data.rate = value;
					return true;
				}
				return false;
			}
		},
		{
			field: 'comment',
			headerName: 'Комментарий',
			flex: 1,
			editable: true,
			filter: 'agTextColumnFilter',
			sortable: true
		}
	];

	const gridOptions = {
		columnDefs: columnDefs,
		defaultColDef: {
			sortable: true,
			filter: true,
			resizable: true
		},
		rowSelection: 'multiple',
		animateRows: true,
		onCellValueChanged: handleCellChanged,
		onRowDataUpdated: updateTotals,
		getRowId: function( params ) {
			return params.data.id ? params.data.id.toString() : 'new-' + Date.now();
		},
		localeText: {
			// Русская локализация для AG Grid
			noRowsToShow: 'Нет данных для отображения',
			loadingOoo: 'Загрузка...'
		}
	};

	// Инициализация AG Grid
	const gridApi = agGrid.createGrid( elements.gridContainer, gridOptions );

	// === Обработчики событий ===

	/**
	 * Обработчик изменения ячейки
	 */
	function handleCellChanged( event ) {
		const rowData = event.data;

		if ( ! rowData.id ) {
			// Создание новой записи
			showMessage( 'Добавление новой записи' );
			createReport( rowData )
				.done( function( response ) {
					rowData.id = response.id;
					rowData.employee = response.employee;
					// Обновляем ячейки в строке
					gridApi.refreshCells( { rowNodes: [ event.node ], force: true } );
					showMessage( 'Запись #' + response.id + ' добавлена' );
					setTimeout( hideMessage, 2000 );
				} )
				.fail( handleAjaxError );
		} else {
			// Обновление существующей записи
			showMessage( 'Обновление записи' );
			updateReport( rowData )
				.done( function( response ) {
					showMessage( 'Запись #' + response.id + ' обновлена' );
					setTimeout( hideMessage, 2000 );
					updateTotals();
				} )
				.fail( handleAjaxError );
		}
	}

	/**
	 * Загрузка данных из REST API
	 */
	function loadData() {
		showMessage( 'Загрузка данных' );

		$.ajax( {
			url: innerREST.root + 'reports/v2/activity/',
			method: 'GET',
			beforeSend: function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', innerREST.nonce );
			},
			data: {
				employeeId: elements.selEmployee.val(),
				month: elements.selMonth.val(),
				year: elements.txtYear.val()
			}
		} )
			.done( function( response ) {
				innerREST.debug && console.log( 'loadData response:', response );

				// Преобразование данных
				const processedData = response.map( function( item ) {
					// response возвращает объект с полем data
					const itemData = item.data || item;
					return {
						id: itemData.id,
						employee: itemData.employee,
						date: moment( itemData.date ).format( 'YYYY-MM-DD' ),
						project: itemData.project || '',
						quo: parseFloat( itemData.quo ) || 0,
						rate: parseFloat( itemData.rate ) || 0,
						comment: itemData.comment || ''
					};
				} );

				gridApi.setGridOption( 'rowData', processedData );
				updateTotals();
				hideMessage();
			} )
			.fail( handleAjaxError );
	}

	/**
	 * Обновление итоговых значений
	 */
	function updateTotals() {
		let totalQuo = 0;
		let totalSum = 0;

		gridApi.forEachNode( function( node ) {
			if ( node.data && node.data.quo && node.data.rate ) {
				const quo = parseFloat( node.data.quo ) || 0;
				const rate = parseFloat( node.data.rate ) || 0;
				totalQuo += quo;
				totalSum += quo * rate;
			}
		} );

		elements.totalQuo.text( totalQuo.toFixed( 2 ) );
		elements.totalSum.text( totalSum.toFixed( 2 ) + ' ₽' );
	}

	/**
	 * Экспорт в CSV
	 */
	function exportToCsv() {
		gridApi.exportDataAsCsv( {
			fileName: 'employee-reports-' +
				elements.txtYear.val() + '-' +
				elements.selMonth.val() + '.csv',
			columnSeparator: ';',
			skipColumnHeaders: false
		} );

		showMessage( 'Экспорт завершен' );
		setTimeout( hideMessage, 2000 );
	}

	// === REST API функции ===

	/**
	 * Создание новой записи
	 */
	function createReport( data ) {
		return $.ajax( {
			url: innerREST.root + 'reports/v2/activity/',
			method: 'POST',
			beforeSend: function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', innerREST.nonce );
			},
			data: {
				date: moment( data.date ).format( 'DD.MM.YYYY' ),
				project: data.project || '',
				quo: data.quo || 0,
				rate: data.rate || 0,
				comment: data.comment || ''
			}
		} );
	}

	/**
	 * Обновление существующей записи
	 */
	function updateReport( data ) {
		return $.ajax( {
			url: innerREST.root + 'reports/v2/activity/' + data.id,
			method: 'POST',
			beforeSend: function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', innerREST.nonce );
			},
			data: {
				date: moment( data.date ).format( 'DD.MM.YYYY' ),
				project: data.project || '',
				quo: data.quo || 0,
				rate: data.rate || 0,
				comment: data.comment || ''
			}
		} );
	}

	// === Вспомогательные функции ===

	/**
	 * Показать сообщение
	 */
	function showMessage( message ) {
		elements.message.text( message ).show( 'fast' );
	}

	/**
	 * Скрыть сообщение
	 */
	function hideMessage() {
		elements.message.hide( 'fast' );
	}

	/**
	 * Обработчик ошибок AJAX
	 */
	function handleAjaxError( jqXHR, textStatus, errorThrown ) {
		innerREST.debug && console.log( 'AJAX error:', jqXHR, textStatus, errorThrown );

		let errorMsg = 'Запрос не удался';
		if ( jqXHR.responseJSON && jqXHR.responseJSON.message ) {
			errorMsg = jqXHR.responseJSON.message;
		}

		$( '<div>' + errorMsg + '</div>' ).dialog( {
			modal: true,
			title: 'Ошибка',
			width: 500,
			buttons: {
				Ok: function() {
					$( this ).dialog( 'close' );
				}
			}
		} );

		hideMessage();
	}

	/**
	 * Инициализация списка сотрудников
	 */
	function initializeFilters() {
		// Список сотрудников - преобразуем объект в массив и сортируем
		const sortedEmployees = [];
		for ( const employeeKey in innerREST.employees ) {
			sortedEmployees.push( [ employeeKey, innerREST.employees[ employeeKey ] ] );
		}

		// Сортируем по имени
		sortedEmployees.sort( function( a, b ) {
			const x = a[ 1 ].toLowerCase();
			const y = b[ 1 ].toLowerCase();
			return x < y ? -1 : x > y ? 1 : 0;
		} );

		// Добавляем в select
		$.each( sortedEmployees, function( key, value ) {
			elements.selEmployee.append(
				$( '<option></option>' )
					.attr( 'value', value[ 0 ] )
					.text( value[ 1 ] )
			);
		} );

		// Устанавливаем текущего пользователя
		elements.selEmployee.val( innerREST.currentUserId );

		// Текущий месяц и год
		const dateNow = new Date();
		elements.selMonth.val( dateNow.getMonth() + 1 );
		elements.txtYear.val( dateNow.getFullYear() );
	}

	// === Инициализация ===
	initializeFilters();
	loadData();

	// Обработчики событий UI
	elements.btnReload.on( 'click', loadData );
	elements.btnExport.on( 'click', exportToCsv );
} );
