<?php
/**
 * @package Migrate SAMBC data to Radio DJ v0.2
 * @author Andis Grosšteins
 * @copyright (C) 2014 - Andis Grosšteins (http://axellence.lv)
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * 
 * See config.php for database connection and other configuration
 */

require_once('lib/functions.php');
// config_local.php is excluded in .gitignore
$config_file = file_exists('config_local.php')?'config_local.php':'config.php';
require_once($config_file);
require_once('lib/class.migration.php');

/**
 * Set work mode to:
 * WORK_MODE_FILE - for SQL script generation in current directory
 * WORK_MODE_INSERT - for direct data migration to RadioDJ database
 * Note: for WORK_MODE_INSERT to function SAM_USER and SAM_PASS has to be set
 * 		 to correct values in config.php
 * Taka look at config.php for other options
 */
define('WORK_MODE', WORK_MODE_FILE);

$migratin = new Migration;

//$migratin->migrate_histroylist();
$migratin->migrate_songlist();
?>