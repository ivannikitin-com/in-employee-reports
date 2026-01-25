# Тесты и отладка

## Отладочная среда разработки

Отладочный сайт с плагином доступен по адресу <http://127.0.0.1:8080/>  
Страница отчетов доступна по адресу <http://127.0.0.1:8080/reports/>  
Логин, пароль и пароль приложений приведены в файле `.env` в корне проекта (раздел `# WordPress`).

### Запуск отладочной среды

```bash
# Запуск Docker контейнеров
docker-compose up -d

# Просмотр логов
docker-compose logs -f wordpress
```

## Автоматизированное тестирование

Плагин использует PHPUnit для автоматизированного тестирования.

### Установка WordPress Test Suite

Перед запуском тестов необходимо установить тестовое окружение WordPress:

```bash
# Установить тестовое окружение WordPress
# Параметры: db_name, db_user, db_password, db_host, wp_version
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

**Примечание:** Скрипт `install-wp-tests.sh` загружает WordPress Test Suite в `/tmp/wordpress-tests-lib`.

### Запуск тестов

```bash
# Через composer
composer test

# Или напрямую через PHPUnit
vendor/bin/phpunit

# Запуск отдельного файла с тестами
vendor/bin/phpunit tests/test-permissions-manager.php

# Запуск конкретного теста
vendor/bin/phpunit --filter test_roles_created
```

### Проверка покрытия кода

```bash
# Генерация отчета о покрытии (требует Xdebug)
vendor/bin/phpunit --coverage-html coverage/

# Просмотр отчета
open coverage/index.html
```

### Структура тестов

- **`tests/bootstrap.php`** - Загрузка WordPress тестовой среды и плагина
- **`tests/test-permissions-manager.php`** - Тесты системы ролей и прав доступа
- **`tests/test-rest-api.php`** - Тесты REST API контроллера
- **`tests/test-frontend.php`** - Тесты фронтенда и шорткодов

### Отладка тестов

```bash
# Вывод подробной информации при запуске тестов
vendor/bin/phpunit --verbose

# Останавливаться на первой ошибке
vendor/bin/phpunit --stop-on-failure

# Показывать предупреждения
vendor/bin/phpunit --display-warnings
```

## Проверка стандартов кодирования

```bash
# Проверка соответствия WordPress Coding Standards
composer lint

# Автоматическое исправление проблем со стилем
composer lint-fix
```

## Логирование

Плагин использует стандартное логирование WordPress. Все сообщения записываются в `log/debug.log`.

Формат сообщений: `in-employee-reports: УРОВЕНЬ: Сообщение`

Уровни логирования:
- `error` - ошибки и критические ситуации
- `info` - информационные сообщения
- `debug` - отладочные сообщения (удаляются перед релизом)

Пример:
```php
WP_DEBUG && error_log('in-employee-reports: info: Пользователь авторизован');
```
