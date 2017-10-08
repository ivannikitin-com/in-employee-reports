/**
 * Загрузка данных в HOT
 */
jQuery( function($)
{
	// Элементы
	var totalQuo = $('#totalQuo'),
		totalSum = $('#totalSum');

	// Handsontable
	var container = $("#inerHot")
		.handsontable({
			colHeaders: [ 'Код', 'Сотрудник', 'Дата', 'Проект', 'Кол.', 'Ставка', 'Комментарий'],
			columns: [
				{data: 'id', type: 'numeric', readOnly: true },
				{data: 'employee', readOnly: true },
				{data: 'date', type: 'date', dateFormat: 'DD.MM.YYYY', correctFormat: true },
				{data: 'project' },
				{data: 'quo', type: 'numeric',  format: '0,0.[000]', language: 'ru-RU' }, 
				{data: 'rate', type: 'numeric', format: '0,0 $', language: 'ru-RU'  },
				{data: 'comment'  }
			],
			startRows: 1,
			startCols: 7,
			rowHeaders: false,
			minSpareRows: 1,
			afterChange: dataChanged,
			contextMenu: {
			  items: {
				"row_above": { name: 'Ряд выше'	},
				"row_below": { name: 'Ряд ниже'},
				"hsep1": "---------",
				"remove_row": { name: 'Удалить ряд' }
			  }
			},
		beforeRemoveRow: deleteRows	
	});	
	var hotInstance = $("#inerHot").handsontable('getInstance');

	// Сообщение на экране
	function Message( selectror, hideCallback )
	{
		this.count = 0;
		this.banner = $( selectror );
		this.hideCallBack = hideCallback;
		this.show = function( message )
		{
			if ( this.banner )
			{
				this.banner.text( message ).show( 'fast' );
				this.count++;
			}	
		}
		this.hide = function( message )
		{
			if ( this.banner )
			{
				if ( message )
					this.banner.text( message );
					
				this.count--;				
				if ( this.count <= 0 )
				{
					this.banner.hide( 'fast' );
					this.count = 0;
					// Все погашено, вызываем колбек
					if ( this.hideCallBack )
						this.hideCallBack();
				}
			}
		}	
	}
	var message = new Message( '#inerMessage', function(){
		// Рассчет истоговых значений, когда будет погашен баннер
		var data = hotInstance.getData(),
		 	quo = 0,
			sum = 0;
		innerREST.debug && console.log( 'Total data:', data );
		for (var i=0; i < data.length; i++)
		{
			quo += data[i][4] * 1;
			sum += data[i][4] * data[i][5];
		}
		totalQuo.text( quo );
		totalSum.text( sum );
	});	
	
	
	
	// Загрузка данных	
	loadData();	
	function loadData() 
	{
		message.show( 'Загрузка данных' );
		$.ajax({
			url: innerREST.root + 'reports/v2/activity/',
			method: 'GET',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', innerREST.nonce );
			},
			data:{
				'title' : 'Hello Moon'
			}
		})
		.done( function ( response ) {
			// Преобразовамние данных и даты
			innerREST.debug && console.log( 'response', response );
			var resultData = [];
			for (var i=0; i<response.length; i++)
			{
				response[i].data.date = moment(response[i].data.date).format('DD.MM.YYYY');
				resultData.push( response[i].data );					
			}
			hotInstance.loadData( resultData );
			message.hide();
		})
		.fail(function( jqXHR, exception ) {
			innerREST.debug && console.log( 'loadData exception:', jqXHR, exception );
			var errorMsg = (typeof jqXHR.responseJSON.message !== 'undefined' ) ? jqXHR.responseJSON.message : 'Запрос данных не удался';
			$( '<div>' + errorMsg + '</div>').dialog({
				modal: true,
				title: "Ошибка получения данных",
				width: 500,
				buttons: { Ok: function() { $( this ).dialog( "close" ) } }
			});
			message.hide();
	  	});	
	}
	
	// Обновление данных
	// http://ivannikitin.ivan.wp-server.ru/wp-content/plugins/in-employee-reports/assets/handsontable/demo/ajax.html
	function dataChanged( change, source )
	{
		//innerREST.debug && console.log( 'dataChanged:', source, change );
		
		// Это загрузка данных. Не сохраняем
		if (source === 'loadData' || source === 'programUpdate' )
		  return;	
		
		// Текущий массив данных в таблице
		var data = hotInstance.getData();
		
		// Данные для передачи
		var payLoad = {},
			dateRec, 
			currentTime,
			dateFormat = "YYYY-MM-DD HH:mm:ss";
			
		// Массив изменений
		for (var i=0; i < change.length; i++)
		{
			// Код измененной записи
			var rowNum = change[i][0];
			var rowId = data[rowNum][0];
			innerREST.debug && console.log( 'Измененный ID:', rowId );
			
			// Если ID существует, то запись есть, иначе новый ряд
			if ( rowId )
			{
				// Изменение записи
				payLoad = {};
				payLoad[change[i][1]] = change[i][3];
				innerREST.debug && console.log( 'Изменение записи:', rowId, payLoad );
				message.show( 'Обновление данных ');
				$.ajax({
					url: innerREST.root + 'reports/v2/activity/' + rowId,
					method: 'POST',
					beforeSend: function ( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', innerREST.nonce );
					},
					data: payLoad
				})
				.done( function ( response ) {
					// В ответ приходит измененная запись
					innerREST.debug && console.log( 'response', response );
					hotInstance.setDataAtCell(rowNum, 1, response.employee, 'programUpdate');
					hotInstance.setDataAtCell(rowNum, 2, moment(response.date).format('DD.MM.YYYY'), 'programUpdate');
					hotInstance.setDataAtCell(rowNum, 3, response.project, 'programUpdate');
					hotInstance.setDataAtCell(rowNum, 4, response.quo, 'programUpdate');
					hotInstance.setDataAtCell(rowNum, 5, response.rate, 'programUpdate');
					hotInstance.setDataAtCell(rowNum, 6, response.comment, 'programUpdate');					
					message.hide( 'Запись #' + response.id + ' обновлена');
				})
				.fail(function( jqXHR, exception ) {
					innerREST.debug && console.log( 'loadData exception:', jqXHR, exception );
					var errorMsg = (typeof jqXHR.responseJSON.message !== 'undefined' ) ? jqXHR.responseJSON.message : 'Запрос данных не удался';
					$( '<div>' + errorMsg + '</div>').dialog({
						modal: true,
						title: "Ошибка обновления данных",
						width: 500,
						buttons: { Ok: function() { $( this ).dialog( "close" ) } }
					});
					message.hide();
				});					
			}
			else
			{
				// Добавление записи
				innerREST.debug && console.log( 'Добавление записи');
				payLoad = {};
				payLoad[ change[i][1] ] = change[i][3];	
				innerREST.debug && console.log( 'Добавление записи:', rowId, payLoad );
				message.show( 'Добавление новой записи' );
				$.ajax({
					url: innerREST.root + 'reports/v2/activity/',
					method: 'POST',
					beforeSend: function ( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', innerREST.nonce );
					},
					data: payLoad
				})
				.done( function ( response ) {
					// В ответ приходит добавленная запись
					innerREST.debug && console.log( 'response', response );
					// Добавляем полкченные с сервера поля ID сотрудника и даты
					hotInstance.setDataAtCell(rowNum, 0, response.id, 'programUpdate');  
					hotInstance.setDataAtCell(rowNum, 1, response.employee, 'programUpdate');
					hotInstance.setDataAtCell(rowNum, 2, moment(response.date).format('DD.MM.YYYY'), 'programUpdate');
					hotInstance.setDataAtCell(rowNum, 3, response.project, 'programUpdate');
					hotInstance.setDataAtCell(rowNum, 4, response.quo, 'programUpdate');
					hotInstance.setDataAtCell(rowNum, 5, response.rate, 'programUpdate');
					hotInstance.setDataAtCell(rowNum, 6, response.comment, 'programUpdate');
					message.hide( 'Запись #' + response.id + ' добавлена');
				})
				.fail(function( jqXHR, exception ) {
					innerREST.debug && console.log( 'loadData exception:', jqXHR, exception );
					var errorMsg = (typeof jqXHR.responseJSON.message !== 'undefined' ) ? jqXHR.responseJSON.message : 'Запрос данных не удался';
					$( '<div>' + errorMsg + '</div>').dialog({
						modal: true,
						title: "Ошибка обновления данных",
						width: 500,
						buttons: { Ok: function() { $( this ).dialog( "close" ) } }
					});
					message.hide();
				});	
			}
		}
	}
	
	// Удаление данных
	function deleteRows( index, amount )
	{	
		innerREST.debug && console.log( 'Удаление ' + amount + ' рядов, начиная с ' + index);
		
		// Текущий массив данных в таблице
		var data = hotInstance.getData();
		for (var i=0; i < amount; i++ )
		{
			var id = data[index + i][0];
			innerREST.debug && console.log( 'Удаление ряда #' + id);
			message.show( 'Удаление записей' );
			$.ajax({
				url: innerREST.root + 'reports/v2/activity/' + id,
				method: 'DELETE',
				beforeSend: function ( xhr ) {
					xhr.setRequestHeader( 'X-WP-Nonce', innerREST.nonce );
				}
			})
			.done( function ( response ) {
				// В ответ приходит флаг успешности и ID удаленной записи
				innerREST.debug && console.log( 'response', response );
				message.hide( 'Запись #' + response.id + ' удалена');
			})
			.fail(function( jqXHR, exception ) {
				innerREST.debug && console.log( 'loadData exception:', jqXHR, exception );
				var errorMsg = (typeof jqXHR.responseJSON.message !== 'undefined' ) ? jqXHR.responseJSON.message : 'Удаление данных не удалось';
				$( '<div>' + errorMsg + '</div>').dialog({
					modal: true,
					title: "Ошибка удаления данных",
					width: 500,
					buttons: { Ok: function() { $( this ).dialog( "close" ) } }
				});
				message.hide();
			});				
		}		
	}
	
});
