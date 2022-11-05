<?php

/**
 * @Author: Mohammed M. AlBanna
 * Description: A simple app to read tips from 'tips' folder and show them randomly inside the CLI
 */

// allow to interact with this script through cli only, not with the browser
if (php_sapi_name() !== 'cli') {
  echo 'Interact with the script only through the Command Line! Run "php app.php"';
  exit;
}

// some cases STDIN could be not defined
if (!defined('STDIN')) {
  define('STDIN', fopen('php://stdin', 'r'));
}

// read all the files inside the tips folder
define('TIPS_DIRECTORY', __DIR__ . DIRECTORY_SEPARATOR . 'tips' . DIRECTORY_SEPARATOR);

// define colors
define('COLOR_DEFAULT_TEXT', "\033[39m");
define('COLOR_RED_TEXT', "\033[31m");
define('COLOR_WHITE_TEXT', "\033[97m");
define('COLOR_YELLOW_TEXT', "\033[33m");
define('COLOR_GREEN_TEXT', "\033[32m");
define('COLOR_DARK_GRAY_TEXT', "\033[90m");
define('COLOR_LIGHT_GRAY_TEXT', "\033[37m");

// =====================
// START THE APP
run();
// =====================

function run(): void
{
  // if tips directory is not found for any reason, create it! I don't trust the user at all!!
  if(!is_dir(TIPS_DIRECTORY) || !file_exists(TIPS_DIRECTORY)) {
    @mkdir(TIPS_DIRECTORY);
  }

  // clear the previous output for re-run the app
  clear_screen();

  // list the available tips into the CLI
  $available_tips_files = get_tips_files_from_directory(TIPS_DIRECTORY);
  render_available_tips_files($available_tips_files);

  // wait the user input and make a decision
  echo get_colored_text("\nYour input: ", COLOR_WHITE_TEXT);
  $theUserFileInput = trim(fgets(STDIN));
  $tips = get_tips_from_user_input($theUserFileInput, $available_tips_files, 'run');
  render_tips($tips, 'run');
}


function render_available_tips_files(array $tipsFiles): void
{
  if(count($tipsFiles) == 0) {
    echo get_colored_text('There are no tips files found inside: ' . TIPS_DIRECTORY . ' directory!', COLOR_RED_TEXT) . PHP_EOL;
    echo PHP_EOL . get_colored_text('Press Enter to close...');
    fgets(STDIN);
    exit;
  }

  // show the listed files and wait the user's input
  echo get_colored_text('Available tips files listed below. Type the full file name, part of it, separate multiple files with "," or press Enter to show all tips.') . PHP_EOL;
  foreach ($tipsFiles as $file) {
    echo get_colored_text('- ' . $file, COLOR_RED_TEXT) . PHP_EOL;
  }
}

function render_tips(array $tips, callable $callback = null): void
{
  // start get a random tip and echo them to the console
  while ($tips) {
    echo PHP_EOL . get_random_tip($tips) . PHP_EOL;
    echo "\n\n" . get_colored_text('Tips left:' . count($tips) . ' - Press Enter to continue...', COLOR_DARK_GRAY_TEXT);
    fgets(STDIN);
  }

  // reach end of the tips
  echo "\n" . get_colored_text('================ THE END! ================', COLOR_GREEN_TEXT) . "\n\n";
  echo get_colored_text('Press Enter to re-run the app...', COLOR_DEFAULT_TEXT);
  fgets(STDIN);

  // re-run the app
  if($callback){
    $callback();
  }
}

function get_tips_from_user_input(string $userInput, array $availableTipsFiles, callable $callback = null): array
{
  // if the user input multiple files with comma separator
  $is_user_input_has_comma = strpos($userInput, ',') !== false;
  if ($is_user_input_has_comma) {
    $tips_files_with_ext = [];
    $tip_files = explode(',', $userInput);
    foreach ($tip_files as $tip_file) {
      $tip_file_with_ext = check_tips_file_with_ext_exist(auto_complete_file_name($tip_file, $availableTipsFiles));
      if($tip_file_with_ext !== false) {
        $tips_files_with_ext[] = $tip_file_with_ext;
      }
    }

    if (count($tips_files_with_ext) > 0) {
      echo get_colored_text('Showing tips from: ' . implode(', ', $tips_files_with_ext), COLOR_GREEN_TEXT) . "\n\n\n";
      return get_tips_from($tips_files_with_ext);
    } else {
      echo get_colored_text('================ Tips files are not found ================', COLOR_RED_TEXT). "\n\n\n";
      echo PHP_EOL . get_colored_text('Press Enter to re-run...');
      fgets(STDIN);
      if ($callback) {
        // re-run the application
        $callback();
        exit;
      }
    }
  }

  // if the user types the file name or part of it without extension, append it
  $tip_file_with_ext = check_tips_file_with_ext_exist(auto_complete_file_name($userInput, $availableTipsFiles));
  if ($userInput && $tip_file_with_ext) {
    echo get_colored_text('Showing tips from: -' . $tip_file_with_ext . '-', COLOR_GREEN_TEXT) . "\n\n\n";
    return get_tips_from($tip_file_with_ext);
  }

  // user pressed Enter with no input? Get all tips from all available files
  if(empty($userInput)) {
    echo get_colored_text('Showing tips from all the available tips files!', COLOR_GREEN_TEXT) . "\n\n\n";
    return get_tips_from($availableTipsFiles);
  }

  // user typed wrong file name or the file not found at all
  echo get_colored_text('================ Tips file is not found ================', COLOR_RED_TEXT). "\n\n\n";
  echo PHP_EOL . get_colored_text('Press Enter to re-run...');
  fgets(STDIN);
  if ($callback) {
    $callback();
    exit;
  }
}

// check if the file (with or without extension exists or not)
function check_tips_file_with_ext_exist(string $fileName): string|bool
{
  $file_name = pathinfo($fileName, PATHINFO_FILENAME);
  $file_ext = pathinfo($fileName, PATHINFO_EXTENSION);
  $file_ext = $file_ext === 'txt' ? $file_ext : 'txt';
  $full_file_name = $file_name . '.' . $file_ext;
  return file_exists(TIPS_DIRECTORY . $full_file_name) ? $full_file_name : false;
}

function get_tips_files_from_directory(string $directory = TIPS_DIRECTORY): array
{
  $listed_files = [];
  $files = scandir($directory);
  if ($files) {
    foreach ($files as $file) {
      if (is_dir($directory . $file) || !is_file_with_txt_ext($file)) {
        continue;
      }
      $listed_files[] = basename($file);
    }
  }

  return $listed_files;
}

// get all tips from file(s)
function get_tips_from(string|array $tipsFile): array
{
  if (is_array($tipsFile)) {
    $tips = [];
    foreach ($tipsFile as $file) {
      // this returns array of arrays
      $tips[] = stream_tips_from($file);
    }
    // to merge the arrays inside the array to a single array
    return call_user_func_array('array_merge', $tips);
  } else {
    return stream_tips_from($tipsFile);
  }
}

// read tips from a specific file
function stream_tips_from(string $file, string $directory = TIPS_DIRECTORY): array
{
  $is_txt_extension = is_file_with_txt_ext($file);
  $file = $directory . $file;
  if (!is_file($file) || !file_exists($file) || !$is_txt_extension) {
    return [];
  }

  $tips = [];
  $handle = fopen($file, 'r');
  if ($handle) {
    while (($tip = fgets($handle)) !== false) {
      $tip = trim($tip);
      if (!empty($tip)) {
        $tips[] = get_colored_text(strtoupper(basename($file, '.txt') . ': '), COLOR_RED_TEXT) . get_colored_text($tip, COLOR_YELLOW_TEXT);
      }
    }
  }

  fclose($handle);
  return $tips;
}

function is_file_with_txt_ext(string $file): bool
{
  return pathinfo($file, PATHINFO_EXTENSION) === 'txt';
}

// get a random tip from the get_all_tips and remove the showed tip from the array
function get_random_tip(array &$tips): string
{
  $tip_key = array_rand($tips);
  $tip = $tips[$tip_key];
  unset($tips[$tip_key]); // To free up the memory
  return $tip;
}

// return a text with specific color
function get_colored_text(string $text, string $colorCode = COLOR_DEFAULT_TEXT): string
{
  return "$colorCode$text";
}

// if the user types part of the file name, try auto complete the file name from the available tips files
function auto_complete_file_name(string $fileName, array $availableTipsFiles): string
{
  if(empty($fileName) || count($availableTipsFiles) === 0) {
    return '';
  }

  $bestMatchFile = '';
  $prevPercentage = 0;
  foreach ($availableTipsFiles as $file) {
    similar_text($fileName, $file, $currPercentage);
    if ($currPercentage >= 40 && ($currPercentage > $prevPercentage)) {
      $prevPercentage = $currPercentage;
      $bestMatchFile = $file;
    }
  }

  return $bestMatchFile;
}

// clear the CLI from any output text
function clear_screen(): void
{
  echo chr(27).chr(91).'H'.chr(27).chr(91).'J';   //^[H^[J
}