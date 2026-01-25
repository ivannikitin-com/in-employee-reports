#!/bin/bash

# Скрипт для извлечения changelog для конкретной версии
# Использование: ./extract-changelog.sh 3.0.0

VERSION=$1

if [ -z "$VERSION" ]; then
    echo "Ошибка: Не указана версия" >&2
    exit 1
fi

# Ищем раздел с указанной версией в CHANGELOG.md
# Формат: ## [3.0.0] 2026-01-24
# Извлекаем все до следующего раздела ## [

CHANGELOG_FILE="CHANGELOG.md"

if [ ! -f "$CHANGELOG_FILE" ]; then
    echo "Ошибка: Файл $CHANGELOG_FILE не найден" >&2
    exit 1
fi

# Нормализуем версию: если версия в формате 3.0, преобразуем в 3.0.0
# Это нужно для совместимости с форматом в CHANGELOG.md
if [[ "$VERSION" =~ ^[0-9]+\.[0-9]+$ ]]; then
    VERSION="${VERSION}.0"
fi

# Используем awk для извлечения раздела
# Ищем строку с версией и выводим все до следующего раздела
CHANGELOG_CONTENT=$(awk -v version="$VERSION" '
    /^## \[/ {
        if (found) exit
        # Проверяем точное совпадение версии в квадратных скобках
        if ($0 ~ "\\[" version "\\]") {
            found = 1
            print
            next
        }
    }
    found {
        if (/^## \[/) exit
        print
    }
' "$CHANGELOG_FILE")

# Проверяем, что changelog был найден
if [ -z "$CHANGELOG_CONTENT" ]; then
    echo "Ошибка: Changelog для версии $VERSION не найден в $CHANGELOG_FILE" >&2
    exit 1
fi

echo "$CHANGELOG_CONTENT"
