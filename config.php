<?php

define('VERSION','0.1');

/**
 * @constant Current directory variable for backward compatibility 
 */
!defined('__DIR__') && define('__DIR__', dirname(__FILE__));

// Change to current directory for CLI runs
chdir(__DIR__);

/**
 * @constant Path to application directory
 */
!defined('APP_PATH') && define('APP_PATH', __DIR__);
/**
 * @constant Make a shortcut to DIRECTORY_SEPARATOR
 */
!defined('DS') && define('DS', DIRECTORY_SEPARATOR);

/**
 * @constant Debug switch
 */
!defined('DEBUG') && define('DEBUG', TRUE);

// Set local debug log
ini_set('error_log', './php_errors.log');

// Implicit flushing of output buffer
ob_implicit_flush(TRUE);


/**
 * @constant Charset for conversion to UTF-8. Will be used by @iconv in @iconv_deep function
 * If your windows locale is English, Frech or other Western european, you might want to use CP1252
 */
!defined('SAM_CHARSET') && define('SAM_CHARSET','UTF-8');

/**
 * @constant Target charset/encoding
 */
!defined('TARGET_CHARSET') && define('TARGET_CHARSET','UTF-8');
ini_set('iconv.input_encoding',TARGET_CHARSET);
ini_set('iconv.internal_encoding',TARGET_CHARSET);
ini_set('iconv.output_encoding',TARGET_CHARSET);
ini_set('mbstring.internal_encoding',TARGET_CHARSET);

/**
 * @constant Which DB driver to use
 */
//define('SAM_DB', 'mysql');
define('SAM_DB', 'firebird');

/**
 * @constant Firebird database path
 */
//define('SAM_DATABASE', 'SAMDB'); // For MySQL
define('SAM_DATABASE', 'D:/Users/Andis/AppData/Local/SpacialAudio/SAMBC/SAMDB/SAMDB.fdb'); // For Firebird

/**
 * @constant Firebird hostname and port. Usually it is 'localhost' for local machine
 */
define('SAM_HOST', 'localhost');

/**
 * @constant SAMBCs database username. For Firebird it is 'SYSDBA'
 */
//define('SAM_USER', 'mysqluser'); // For MySQL
define('SAM_USER', 'SYSDBA'); // For Firebird

/**
 * @constant SAMBCs database password. For Firebird default password is 'masterkey'
 */
//define('SAM_PASS', 'mysqlpass'); // For MySQL
define('SAM_PASS', 'masterkey'); // For Firebird

/**
 * @constant MySQL hostname. Usually it is 'localhost' for local machine
 */
define('RDJ_HOST', 'localhost');

/**
 * @constant MySQL user
 */
define('RDJ_USER', 'mysqluser');

/**
 * @constant MySQL password
 */
define('RDJ_PASS', 'mysqlpass');

/**
 * @constant MySQL database
 */
define('RDJ_DATABASE', 'radiodj');

/**
 * @constant If set as WORK_MODE, inserts directly into MySQL database
 */
define('WORK_MODE_INSERT','INSERT');

/**
 * @constant If set as WORK_MODE, write sql to a file set in $output_file
 */
define('WORK_MODE_FILE','FILE');

define('OUTPUT_FILE', 'SAMBC_mysql.sql');

/**
 * @constant Maximum rows to insert at once
 */
define('MAX_INSERT_ROWS',1000);

?>