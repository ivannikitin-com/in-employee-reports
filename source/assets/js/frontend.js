/**
 * Фронтенд скрипт для отображения отчетов с использованием AG Grid
 */
jQuery(function($) {
    'use strict';

    // === Вспомогательные функции для работы с датами ===
    /**
     * Форматирование даты в формат DD.MM.YYYY
     */
    function formatDateDDMMYYYY(date) {
        if (!date) return '';
        const d = new Date(date);
        if (isNaN(d.getTime())) return '';
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        return day + '.' + month + '.' + year;
    }

    /**
     * Форматирование даты в формат YYYY-MM-DD
     */
    function formatDateYYYYMMDD(date) {
        if (!date) return '';
        const d = new Date(date);
        if (isNaN(d.getTime())) return '';
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        return year + '-' + month + '-' + day;
    }

    /**
     * Парсинг даты из формата DD.MM.YYYY
     */
    function parseDateDDMMYYYY(dateString) {
        if (!dateString) return null;
        const parts = dateString.split('.');
        if (parts.length !== 3) return null;
        const day = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1; // месяцы в JS начинаются с 0
        const year = parseInt(parts[2], 10);
        const date = new Date(year, month, day);
        // Проверяем валидность
        if (date.getDate() === day && date.getMonth() === month && date.getFullYear() === year) {
            return date;
        }
        return null;
    }

    /**
     * Проверка валидности даты в формате DD.MM.YYYY
     */
    function isValidDateDDMMYYYY(dateString) {
        return parseDateDDMMYYYY(dateString) !== null;
    }

    /**
     * Получение текущей даты в формате YYYY-MM-DD
     */
    function getTodayYYYYMMDD() {
        return formatDateYYYYMMDD(new Date());
    }

    /**
     * Кастомный редактор ячейки для поля "Проект" с автодополнением
     */
    function ProjectAutocompleteEditor() {}

    ProjectAutocompleteEditor.prototype.init = function(params) {
        this.params = params;
        
        // Создаем input элемент
        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = 'ag-input-field-input';
        this.input.value = params.value || '';
        
        // Создаем datalist для автодополнения
        const datalistId = 'project-autocomplete-list';
        let datalist = document.getElementById(datalistId);
        
        if (!datalist) {
            datalist = document.createElement('datalist');
            datalist.id = datalistId;
            
            // Добавляем опции из списка проектов
            const projects = innerREST.projects || [];
            projects.forEach(function(project) {
                const option = document.createElement('option');
                option.value = project;
                datalist.appendChild(option);
            });
            
            // Добавляем datalist в body
            document.body.appendChild(datalist);
        }
        
        // Привязываем datalist к input
        this.input.setAttribute('list', datalistId);
    };

    ProjectAutocompleteEditor.prototype.getGui = function() {
        return this.input;
    };

    ProjectAutocompleteEditor.prototype.getValue = function() {
        return this.input.value;
    };

    ProjectAutocompleteEditor.prototype.isCancelBeforeStart = function() {
        return false;
    };

    ProjectAutocompleteEditor.prototype.isCancelAfterEnd = function() {
        return false;
    };

    ProjectAutocompleteEditor.prototype.afterGuiAttached = function() {
        // Фокус и выделение текста при открытии редактора
        setTimeout(function() {
            this.input.focus();
            this.input.select();
        }.bind(this), 0);
    };

    // === Элементы UI ===
    const elements = {
        totalQuo: $('#totalQuo'),
        totalSum: $('#totalSum'),
        selEmployee: $('#inerEmployee'),
        selMonth: $('#inerMonth'),
        txtYear: $('#inerYear'),
        btnReload: $('#inerReload'),
        btnExport: $('#inerExport'),
        btnAddRecord: $('#inerAddRecord'),
        btnAddRow: $('#inerAddRow'),
        btnDeleteRow: $('#inerDeleteRow'),
        message: $('#inerMessage'),
        addDialog: $('#inerAddDialog'),
        addForm: $('#inerAddForm'),
        addEmployee: $('#inerAddEmployee'),
        addDate: $('#inerAddDate'),
        addProject: $('#inerAddProject'),
        addProjectList: $('#inerAddProjectList'),
        addQuo: $('#inerAddQuo'),
        addRate: $('#inerAddRate'),
        addComment: $('#inerAddComment'),
        gridContainer: document.getElementById('inerGrid')
    };

    // Счетчик временных идентификаторов новых строк (до сохранения на сервере)
    let tempRowCounter = 0;

    /**
     * Возвращает уникальный временный идентификатор новой строки
     */
    function nextTempId() {
        tempRowCounter += 1;
        return 'new-' + tempRowCounter + '-' + Date.now();
    }

    /**
     * Убирает переносы строк из текста, заменяя их пробелами
     */
    function normalizeComment(value) {
        if (value === undefined || value === null) return '';
        return String(value).replace(/[\r\n]+/g, ' ').replace(/\s+/g, ' ').trim();
    }

    // === Конфигурация AG Grid ===
    const columnDefs = [{
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
        // Колонка даты: valueGetter даёт в редактор DD.MM.YYYY; valueSetter принимает DD.MM.YYYY и YYYY-MM-DD, хранит YYYY-MM-DD в data.date.
        {
            field: 'date',
            headerName: 'Дата',
            width: 120,
            editable: true,
            sortable: true,
            valueGetter: function(params) {
                if (!params.data || !params.data.date) return '';
                return formatDateDDMMYYYY(params.data.date);
            },
            valueFormatter: function(params) {
                if (!params.value) return '';
                // valueGetter уже вернул DD.MM.YYYY — не форматировать повторно
                if (typeof params.value === 'string' && /^\d{1,2}\.\d{1,2}\.\d{4}$/.test(params.value)) return params.value;
                return formatDateDDMMYYYY(params.value);
            },
            valueSetter: function(params) {
                const raw = params.newValue;
                if (raw === undefined || raw === null || String(raw).trim() === '') return false;
                const str = String(raw).trim();
                let parsedDate = parseDateDDMMYYYY(str);
                // Редактор мог отдать YYYY-MM-DD — тоже принимаем
                if (!parsedDate && str.indexOf('-') === 4) {
                    const d = new Date(str);
                    if (!isNaN(d.getTime())) parsedDate = d;
                }
                if (parsedDate) {
                    params.data.date = formatDateYYYYMMDD(parsedDate);
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
            cellEditor: 'projectAutocompleteEditor'
        },
        {
            field: 'quo',
            headerName: 'Кол.',
            width: 100,
            editable: true,
            sortable: true,
            filter: 'agNumberColumnFilter',
            valueFormatter: function(params) {
                return params.value ? parseFloat(params.value).toFixed(2) : '0.00';
            },
            valueSetter: function(params) {
                const value = parseFloat(params.newValue);
                if (!isNaN(value)) {
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
            valueFormatter: function(params) {
                return params.value ? parseFloat(params.value).toFixed(2) + ' ₽' : '0.00 ₽';
            },
            valueSetter: function(params) {
                const value = parseFloat(params.newValue);
                if (!isNaN(value)) {
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
        components: {
            projectAutocompleteEditor: ProjectAutocompleteEditor
        },
        defaultColDef: {
            sortable: true,
            filter: true,
            resizable: true
        },
        rowSelection: 'multiple',
        animateRows: true,
        domLayout: 'autoHeight',
        onCellValueChanged: handleCellChanged,
        onRowDataUpdated: updateTotals,
        getRowId: function(params) {
            if (params.data.id) {
                return params.data.id.toString();
            }
            if (!params.data.__tempId) {
                params.data.__tempId = nextTempId();
            }
            return params.data.__tempId;
        },
        localeText: {
            // Русская локализация для AG Grid
            noRowsToShow: 'Нет данных для отображения',
            loadingOoo: 'Загрузка...'
        }
    };

    // Инициализация AG Grid
    const gridApi = agGrid.createGrid(elements.gridContainer, gridOptions);

    // === Обработчики событий ===

    /**
     * Обработчик изменения ячейки
     */
    function handleCellChanged(event) {
        const rowData = event.data;

        if (!rowData.id) {
            // Если создание этой строки уже выполняется, не создаем дубль.
            // Изменения, сделанные во время запроса, будут отправлены после его завершения.
            if (rowData.__creating) {
                rowData.__dirtyDuringCreate = true;
                return;
            }

            rowData.__creating = true;

            // Создание новой записи
            showMessage('Добавление новой записи');
            createReport(rowData)
                .done(function(response) {
                    rowData.id = response.id;
                    rowData.employee = response.employee;
                    rowData.__creating = false;
                    // Обновляем ячейки в строке
                    gridApi.refreshCells({ rowNodes: [event.node], force: true });
                    // Если во время сохранения данные менялись - сохраняем актуальную версию
                    if (rowData.__dirtyDuringCreate) {
                        rowData.__dirtyDuringCreate = false;
                        updateReport(rowData).fail(handleAjaxError);
                    }
                    showMessage('Запись #' + response.id + ' добавлена');
                    setTimeout(hideMessage, 2000);
                })
                .fail(function() {
                    rowData.__creating = false;
                    handleAjaxError.apply(null, arguments);
                });
        } else {
            // Обновление существующей записи
            showMessage('Обновление записи');
            updateReport(rowData)
                .done(function(response) {
                    showMessage('Запись #' + response.id + ' обновлена');
                    setTimeout(hideMessage, 2000);
                    updateTotals();
                })
                .fail(handleAjaxError);
        }
    }

    /**
     * Загрузка данных из REST API
     */
    function loadData() {
        showMessage('Загрузка данных');

        $.ajax({
                url: innerREST.root + 'reports/v2/activity/',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', innerREST.nonce);
                },
                data: {
                    employeeId: elements.selEmployee.val(),
                    month: elements.selMonth.val(),
                    year: elements.txtYear.val()
                }
            })
            .done(function(response) {
                // Преобразование данных
                const processedData = response.map(function(item) {
                    // response возвращает объект с полем data
                    const itemData = item.data || item;
                    return {
                        id: itemData.id,
                        employee: itemData.employee,
                        date: formatDateYYYYMMDD(itemData.date),
                        project: itemData.project || '',
                        quo: parseFloat(itemData.quo) || 0,
                        rate: parseFloat(itemData.rate) || 0,
                        comment: itemData.comment || ''
                    };
                });

                gridApi.setGridOption('rowData', processedData);
                updateTotals();
                hideMessage();
            })
            .fail(handleAjaxError);
    }

    /**
     * Добавление новой строки
     */
    function addNewRow() {
        const today = getTodayYYYYMMDD();
        const newRow = {
            id: null,
            __tempId: nextTempId(),
            employee: innerREST.currentUserName || '',
            date: today,
            project: '',
            quo: 0,
            rate: 0,
            comment: ''
        };

        // Добавляем новую строку в конец
        gridApi.applyTransaction({ add: [newRow] });

        // Фокусируемся на новой строке и первой редактируемой ячейке (дата)
        setTimeout(function() {
            const rowNode = gridApi.getRowNode(newRow.__tempId);
            if (rowNode) {
                gridApi.ensureIndexVisible(rowNode.rowIndex, 'bottom');
                gridApi.setFocusedCell(rowNode.rowIndex, 'date');
                gridApi.startEditingCell({
                    rowIndex: rowNode.rowIndex,
                    colKey: 'date'
                });
            }
        }, 100);
    }

    /**
     * Удаление строки
     */
    function deleteRow(rowNode) {
        if (!rowNode || !rowNode.data) {
            return;
        }

        const rowData = rowNode.data;

        // Если это существующая запись, удаляем через API
        if (rowData.id) {
            if (!confirm('Вы уверены, что хотите удалить запись #' + rowData.id + '?')) {
                return;
            }

            showMessage('Удаление записи');
            $.ajax({
                    url: innerREST.root + 'reports/v2/activity/' + rowData.id,
                    method: 'DELETE',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', innerREST.nonce);
                    }
                })
                .done(function() {
                    gridApi.applyTransaction({ remove: [rowData] });
                    updateTotals();
                    showMessage('Запись #' + rowData.id + ' удалена');
                    setTimeout(hideMessage, 2000);
                })
                .fail(handleAjaxError);
        } else {
            // Если это новая несохраненная строка, просто удаляем из таблицы
            gridApi.applyTransaction({ remove: [rowData] });
            updateTotals();
        }
    }

    /**
     * Удаление выбранных строк
     */
    function deleteSelectedRows() {
        const selectedRows = gridApi.getSelectedRows();
        
        if (!selectedRows || selectedRows.length === 0) {
            alert('Пожалуйста, выберите строки для удаления');
            return;
        }

        const rowsToDelete = selectedRows.length;
        if (!confirm('Вы уверены, что хотите удалить ' + rowsToDelete + ' строк(и)?')) {
            return;
        }

        // Разделяем строки на существующие (с id) и новые (без id)
        const existingRows = [];
        const newRows = [];

        selectedRows.forEach(function(rowData) {
            if (rowData.id) {
                existingRows.push(rowData);
            } else {
                newRows.push(rowData);
            }
        });

        // Удаляем новые строки сразу из таблицы
        if (newRows.length > 0) {
            gridApi.applyTransaction({ remove: newRows });
            updateTotals();
        }

        // Удаляем существующие строки через API
        if (existingRows.length > 0) {
            showMessage('Удаление записей');
            let deletedCount = 0;
            let errorCount = 0;

            // Удаляем все строки параллельно
            const deletePromises = existingRows.map(function(rowData) {
                return $.ajax({
                    url: innerREST.root + 'reports/v2/activity/' + rowData.id,
                    method: 'DELETE',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', innerREST.nonce);
                    }
                })
                .done(function() {
                    deletedCount++;
                })
                .fail(function() {
                    errorCount++;
                });
            });

            // Ждем завершения всех запросов
            $.when.apply($, deletePromises).always(function() {
                // Удаляем все строки из таблицы
                gridApi.applyTransaction({ remove: existingRows });
                updateTotals();
                
                if (errorCount === 0) {
                    showMessage('Удалено записей: ' + deletedCount);
                } else {
                    showMessage('Удалено: ' + deletedCount + ', ошибок: ' + errorCount);
                }
                setTimeout(hideMessage, 2000);
            });
        } else {
            updateTotals();
        }
    }

    /**
     * Обновление итоговых значений
     */
    function updateTotals() {
        let totalQuo = 0;
        let totalSum = 0;

        gridApi.forEachNode(function(node) {
            if (node.data && node.data.quo && node.data.rate) {
                const quo = parseFloat(node.data.quo) || 0;
                const rate = parseFloat(node.data.rate) || 0;
                totalQuo += quo;
                totalSum += quo * rate;
            }
        });

        elements.totalQuo.text(totalQuo.toFixed(2));
        elements.totalSum.text(totalSum.toFixed(2) + ' ₽');
    }

    /**
     * Инициализация модального окна добавления записи
     */
    function initAddDialog() {
        // Заполняем список проектов для автодополнения
        elements.addProjectList.empty();
        (innerREST.projects || []).forEach(function(project) {
            $('<option></option>').attr('value', project).appendTo(elements.addProjectList);
        });

        // Выбор даты
        if ($.fn.datepicker) {
            elements.addDate.datepicker({
                dateFormat: 'dd.mm.yy',
                changeMonth: true,
                changeYear: true
            });
        }

        // Диалоговое окно
        elements.addDialog.dialog({
            autoOpen: false,
            modal: true,
            width: 500,
            resizable: false,
            buttons: {
                'Сохранить': saveNewRecord,
                'Отмена': function() {
                    $(this).dialog('close');
                }
            }
        });
    }

    /**
     * Открытие модального окна добавления записи
     */
    function openAddDialog() {
        // Сотрудник: для руководителей - выбранный в фильтре, иначе текущий пользователь
        let employeeName = innerREST.currentUserName || '';
        const selectedEmployeeId = elements.selEmployee.val();
        if (innerREST.canCreateForOther && selectedEmployeeId && selectedEmployeeId !== '0') {
            employeeName = innerREST.employees[selectedEmployeeId] || employeeName;
        }

        elements.addEmployee.val(employeeName);
        elements.addDate.val(formatDateDDMMYYYY(new Date()));
        elements.addProject.val('');
        elements.addQuo.val('0');
        elements.addRate.val('0');
        elements.addComment.val('');

        elements.addDialog.dialog('open');
    }

    // Признак того, что запись из модального окна уже сохраняется
    let addRecordSaving = false;

    /**
     * Сохранение новой записи из модального окна
     */
    function saveNewRecord() {
        if (addRecordSaving) {
            return;
        }

        const date = String(elements.addDate.val() || '').trim();

        if (!isValidDateDDMMYYYY(date)) {
            alert('Введите корректную дату в формате ДД.ММ.ГГГГ');
            return;
        }

        const data = {
            employee: elements.addEmployee.val(),
            date: date,
            project: String(elements.addProject.val() || '').trim(),
            quo: parseFloat(elements.addQuo.val()) || 0,
            rate: parseFloat(elements.addRate.val()) || 0,
            comment: normalizeComment(elements.addComment.val())
        };

        addRecordSaving = true;
        showMessage('Добавление новой записи');
        createReport(data)
            .done(function(response) {
                elements.addDialog.dialog('close');
                showMessage('Запись #' + response.id + ' добавлена');
                setTimeout(hideMessage, 2000);
                loadData();
            })
            .fail(handleAjaxError)
            .always(function() {
                addRecordSaving = false;
                if (elements.addDialog.dialog('isOpen')) {
                    hideMessage();
                }
            });
    }

    /**
     * Экспорт в CSV
     */
    function exportToCsv() {
        gridApi.exportDataAsCsv({
            fileName: 'employee-reports-' +
                elements.txtYear.val() + '-' +
                elements.selMonth.val() + '.csv',
            columnSeparator: ';',
            skipColumnHeaders: false
        });

        showMessage('Экспорт завершен');
        setTimeout(hideMessage, 2000);
    }

    // === REST API функции ===

    /**
     * Создание новой записи
     */
    function createReport(data) {
        // Обрабатываем дату - если не указана, используем текущую
        let dateValue = data.date || getTodayYYYYMMDD();
        // Если дата в формате YYYY-MM-DD, преобразуем в DD.MM.YYYY
        if (dateValue.indexOf('-') === 4) {
            dateValue = formatDateDDMMYYYY(dateValue);
        } else if (isValidDateDDMMYYYY(dateValue)) {
            // Уже в правильном формате
            dateValue = dateValue;
        } else {
            // Пробуем распарсить как есть
            const parsedDate = new Date(dateValue);
            dateValue = !isNaN(parsedDate.getTime()) ? formatDateDDMMYYYY(parsedDate) : formatDateDDMMYYYY(new Date());
        }

        return $.ajax({
            url: innerREST.root + 'reports/v2/activity/',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', innerREST.nonce);
            },
            data: {
                employee: data.employee || '',
                date: dateValue,
                project: data.project || '',
                quo: data.quo || 0,
                rate: data.rate || 0,
                comment: normalizeComment(data.comment)
            }
        });
    }

    /**
     * Обновление существующей записи
     */
    function updateReport(data) {
        // Обрабатываем дату - если не указана, используем текущую
        let dateValue = data.date || getTodayYYYYMMDD();
        // Если дата в формате YYYY-MM-DD, преобразуем в DD.MM.YYYY
        if (dateValue.indexOf('-') === 4) {
            dateValue = formatDateDDMMYYYY(dateValue);
        } else if (isValidDateDDMMYYYY(dateValue)) {
            // Уже в правильном формате
            dateValue = dateValue;
        } else {
            // Пробуем распарсить как есть
            const parsedDate = new Date(dateValue);
            dateValue = !isNaN(parsedDate.getTime()) ? formatDateDDMMYYYY(parsedDate) : formatDateDDMMYYYY(new Date());
        }

        return $.ajax({
            url: innerREST.root + 'reports/v2/activity/' + data.id,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', innerREST.nonce);
            },
            data: {
                date: dateValue,
                project: data.project || '',
                quo: data.quo || 0,
                rate: data.rate || 0,
                comment: normalizeComment(data.comment)
            }
        });
    }

    // === Вспомогательные функции ===

    /**
     * Показать сообщение
     */
    function showMessage(message) {
        elements.message.text(message).show('fast');
    }

    /**
     * Скрыть сообщение
     */
    function hideMessage() {
        elements.message.hide('fast');
    }

    /**
     * Обработчик ошибок AJAX
     */
    function handleAjaxError(jqXHR, textStatus, errorThrown) {
        let errorMsg = 'Запрос не удался';
        if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
            errorMsg = jqXHR.responseJSON.message;
        }

        $('<div>' + errorMsg + '</div>').dialog({
            modal: true,
            title: 'Ошибка',
            width: 500,
            buttons: {
                Ok: function() {
                    $(this).dialog('close');
                }
            }
        });

        hideMessage();
    }

    /**
     * Инициализация списка сотрудников
     */
    function initializeFilters() {
        // Список сотрудников - преобразуем объект в массив и сортируем
        const sortedEmployees = [];
        for (const employeeKey in innerREST.employees) {
            sortedEmployees.push([employeeKey, innerREST.employees[employeeKey]]);
        }

        // Сортируем по имени
        sortedEmployees.sort(function(a, b) {
            const x = a[1].toLowerCase();
            const y = b[1].toLowerCase();
            return x < y ? -1 : x > y ? 1 : 0;
        });

        // Добавляем в select
        $.each(sortedEmployees, function(key, value) {
            elements.selEmployee.append(
                $('<option></option>')
                .attr('value', value[0])
                .text(value[1])
            );
        });

        // Устанавливаем текущего пользователя
        elements.selEmployee.val(innerREST.currentUserId);

        // Текущий месяц и год
        const dateNow = new Date();
        elements.selMonth.val(dateNow.getMonth() + 1);
        elements.txtYear.val(dateNow.getFullYear());
    }

    // === Инициализация ===
    initializeFilters();
    initAddDialog();
    loadData();

    // Обработчики событий UI
    elements.btnReload.on('click', loadData);
    elements.btnExport.on('click', exportToCsv);
    elements.btnAddRecord.on('click', openAddDialog);
    elements.btnAddRow.on('click', addNewRow);
    elements.btnDeleteRow.on('click', deleteSelectedRows);
});