#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Adl;

use Psl\DateTime;
use Psl\Env;
use Psl\File;
use Psl\Filesystem;
use Psl\IO;
use Psl\Iter;
use Psl\Str;
use Psl\Vec;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * An enumeration of commands that can be executed by the ADR CLI tool.
 */
enum Command: string {
  case Regen = 'regen';
  case Create = 'create';
}

/**
 * Rebuilds the README.md file in the 'adr' directory using a template.
 *
 * If a custom template is found in the 'adr/templates' directory, it uses that;
 * otherwise, it falls back to a default template located in the script's directory.
 */
function rebuild_readme(): void {
  $template_contents = namespace\read_file_if_exists(namespace\get_path_from_current_working_directory(
    'adr',
    'templates',
    'template_readme.md',
  ));

  if ($template_contents === null) {
    $template_contents = File\read(namespace\get_path_from_script_directory(
      'templates',
      'readme_template.md',
    ));
  }

  $date = DateTime\DateTime::now()->format(DateTime\FormatPattern::Http);
  $output = Str\replace($template_contents, '{{timestamp}}', $date);
  $files = namespace\get_all_files_in_adr_dir();
  $files = Vec\sort($files);

  $formatted_files = [];
  foreach ($files as $file) {
    $new_str = Str\format(' - [%s](./%s)', $file, $file);
    $formatted_files[] = $new_str;
  }

  $replacement = Str\join($formatted_files, "\n");
  $with_contents = Str\replace($output, '{{contents}}', $replacement);

  $path = namespace\get_path_from_current_working_directory('adr', 'README.md');
  namespace\write_to_file($path, $with_contents);
}

/**
 * Generates a new ADR (Architectural Decision Record) file with a padded number
 * and a specified name. The file is created in the 'adr' directory with a template.
 *
 * @param int $n The number to pad and use in the file name.
 * @param string $name The name of the ADR to include in the file.
 */
function generate_adr(int $n, string $name): void {
  $padded_nums = Str\format('%05d', $n);
  $heading = Str\format('%s - %s', $padded_nums, $name);

  $template_contents = namespace\read_file_if_exists(namespace\get_path_from_current_working_directory(
    'adr',
    'templates',
    'template_adr.md',
  ));

  if ($template_contents === null) {
    $template_contents = File\read(namespace\get_path_from_script_directory(
      'templates',
      'adr_template.md',
    ));
  }

  $contents = Str\replace($template_contents, '{{name}}', $heading);
  $safe_name = $name;
  foreach (['/', '\\', ':', '*', '?', '"', '<', '>', '|'] as $char) {
    $safe_name = Str\replace($safe_name, $char, ' ');
  }

  $file_name = namespace\get_path_from_current_working_directory(
    'adr',
    Str\format('%s-%s.md', $padded_nums, $safe_name),
  );

  namespace\write_to_file($file_name, $contents);
}

/**
 * Establishes the core files and directories.
 *
 * Creates 'adr', 'assets', and 'templates' directories, and initializes
 * a README.md file with a template.
 */
function establish_core_files(): void {
  Filesystem\create_directory(namespace\get_path_from_current_working_directory(
    'adr',
    'assets',
  ));
  Filesystem\create_directory(namespace\get_path_from_current_working_directory(
    'adr',
    'templates',
  ));

  $readme_file = namespace\get_path_from_current_working_directory(
    'adr',
    'README.md',
  );
  $readme_template = File\read(namespace\get_path_from_script_directory(
    'templates',
    'readme_template.md',
  ));

  namespace\write_to_file($readme_file, $readme_template);
}

/**
 * Retrieves a list of all files in the 'adr' directory, excluding
 * 'README.md', 'assets', and 'templates'.
 *
 * @return list<non-empty-string>
 */
function get_all_files_in_adr_dir(): array {
  $files = Filesystem\read_directory(namespace\get_path_from_current_working_directory(
    'adr',
  ));

  foreach ($files as $file) {
    $name = Filesystem\get_basename($file);

    if ($name !== 'README.md' && $name !== 'assets' && $name !== 'templates') {
      $file_list[] = $name;
    }
  }

  return $file_list;
}

/**
 * Reads the contents of a file if it exists, returning null if the file is not found.
 *
 * @param non-empty-string $filepath
 *
 * @return null|string The contents of the file or null if it does not exist.
 */
function read_file_if_exists(string $filepath): null|string {
  try {
    return File\read($filepath);
  } catch (File\Exception\NotFoundException) {
    return null;
  }
}

/**
 * Joins the given path parts to the current script's directory (__DIR__).
 *
 * @param non-empty-string ...$parts Path components to append to __DIR__.
 * @return non-empty-string The absolute path.
 */
function get_path_from_script_directory(string ...$parts): string {
  $root = __DIR__;
  $all_parts = Vec\concat([$root], $parts);

  /** @var non-empty-string */
  return Str\join($all_parts, Filesystem\SEPARATOR);
}

/**
 * Joins the given path parts to the current working directory (CWD).
 *
 * @param non-empty-string ...$parts Path components to append to the CWD.
 * @return non-empty-string The absolute path.
 */
function get_path_from_current_working_directory(string ...$parts): string {
  $root = Env\current_dir();
  $all_parts = Vec\concat([$root], $parts);

  /** @var non-empty-string */
  return Str\join($all_parts, Filesystem\SEPARATOR);
}

/**
 * Writes the given content to the specified file path, truncating it first.
 *
 * @param non-empty-string $path
 * @param non-empty-string $content
 */
function write_to_file(string $path, string $content): void {
  $file = File\open_write_only($path, File\WriteMode::Truncate);
  $lock = $file->lock(File\LockType::Exclusive);
  $file->writeAll($content);
  $lock->release();
  $file->close();
}

/**
 * Regenerates the core files and directories, including the README.md file.
 *
 * This function establishes the core files and rebuilds the README.md file
 * using a template.
 *
 * @return true Always returns true to indicate success.
 */
function regenerate_core_files(): true {
  namespace\establish_core_files();
  namespace\rebuild_readme();

  return true;
}

/**
 * Creates a new ADR (Architectural Decision Record) with the given name.
 *
 * If no name is provided, an error message is displayed.
 *
 * @param non-empty-string $name The name of the ADR to create.
 *
 * @return bool Returns true on success, false if no name was provided.
 */
function create_adr(string $name): bool {
  if (Str\is_empty($name)) {
    IO\write_error_line(
      "No name supplied for the ADR.\nCommand should be: `./main.php create <Name of ADR here>`",
    );

    return false;
  }

  namespace\establish_core_files();
  $file_list = namespace\get_all_files_in_adr_dir();
  namespace\generate_adr(n: Iter\count($file_list), name: $name);
  namespace\rebuild_readme();

  return true;
}

/**
 * Main entry point for the ADR CLI tool.
 *
 * @param list<string> $argv Command line arguments.
 *
 * @return never This function does not return; it exits the script.
 */
function main(array $argv): never {
  $command_name = $argv[1] ?? null;
  $command = Command::tryFrom($command_name);
  if (null === $command) {
    IO\write_error_line(Str\format("Unknown command '%s'.", $command_name));
    IO\write_error_line(File\read(namespace\get_path_from_script_directory(
      'templates',
      'help.txt',
    )));

    exit(1);
  }

  $success = match ($command) {
    Command::Regen => namespace\regenerate_core_files(),
    Command::Create => namespace\create_adr(name: Str\join(
      pieces: Vec\slice($argv, 2),
      glue: '',
    )),
  };

  exit($success ? 0 : 1);
}

// Run the main function with command line arguments
main($argv);
