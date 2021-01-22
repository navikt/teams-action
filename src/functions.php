<?php declare(strict_types=1);
namespace NAVIT\Teams;

/**
 * Get an environment variable as a string
 *
 * @param string $name
 * @return string
 */
function env(string $name): string
{
    return trim((string) getenv($name));
}

/**
 * Output a log message
 *
 * @param string $message The message to output
 * @return void
 */
function output(string $message): void
{
    echo trim($message) . PHP_EOL;
}

/**
 * Sort teams by name
 *
 * @param array{name:string} $a
 * @param array{name:string} $b
 * @return int
 */
function sortTeam(array $a, array $b): int
{
    return strcmp($a['name'], $b['name']);
}
