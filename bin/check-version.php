<?php
/**
 * Проверяет, что версия плагина совпадает в трёх местах:
 *  - заголовок "Version:" в wp-smsn1-ru.php
 *  - "Stable tag:" в readme.txt
 *  - последняя запись "## [x.y.z]" в CHANGELOG.md (кроме Unreleased)
 *
 * Использование: php bin/check-version.php
 * Код возврата: 0 — всё совпадает, 1 — расхождение.
 */

$root = dirname(__DIR__);

function wpsmsn1_read($path)
{
    if (!is_file($path)) {
        fwrite(STDERR, "Не найден файл: {$path}\n");
        return '';
    }
    return (string) file_get_contents($path);
}

function wpsmsn1_find($pattern, $subject)
{
    return preg_match($pattern, $subject, $m) ? trim($m[1]) : null;
}

$header_version = wpsmsn1_find('/^\s*\*?\s*Version:\s*([0-9][^\s*]*)/mi', wpsmsn1_read($root . '/wp-smsn1-ru.php'));
$stable_tag     = wpsmsn1_find('/^Stable tag:\s*(\S+)/mi', wpsmsn1_read($root . '/readme.txt'));
$changelog      = wpsmsn1_read($root . '/CHANGELOG.md');
$changelog_tag  = null;

if (preg_match_all('/^##\s*\[([^\]]+)\]/m', $changelog, $matches)) {
    foreach ($matches[1] as $tag) {
        if (strtolower(trim($tag)) !== 'unreleased') {
            $changelog_tag = trim($tag);
            break;
        }
    }
}

$rows = array(
    'wp-smsn1-ru.php (Version)' => $header_version,
    'readme.txt (Stable tag)'   => $stable_tag,
    'CHANGELOG.md (последний)'  => $changelog_tag,
);

$width = max(array_map('strlen', array_keys($rows))) + 2;
foreach ($rows as $label => $value) {
    printf("%-{$width}s %s\n", $label . ':', $value === null ? '— не найдено' : $value);
}

$values = array_filter($rows, static function ($v) {
    return $v !== null;
});

if (count($values) < 2) {
    fwrite(STDERR, "\nНе удалось извлечь версии для сравнения.\n");
    exit(1);
}

if (count(array_unique($values)) !== 1) {
    fwrite(STDERR, "\nВерсии не совпадают. Обновите Version, Stable tag и CHANGELOG перед релизом.\n");
    exit(1);
}

echo "\nВерсии совпадают: " . reset($values) . "\n";
exit(0);
