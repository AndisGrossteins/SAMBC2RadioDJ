<?php
/**
 * @package Migrate SAMBC data to Radio DJ v0.2
 * @author Andis Grosšteins
 * @copyright (C) 2014 - Andis Grosšteins (http://axellence.lv)
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Migration class for SAM Broadcaster to Radio DJ data migration
 * Currently supports only history data migration
 * Work in progres...
 * 
 * 
 */

class Migration{

	/**
	 * Database object singletone reference
	 */
	var $RDJ_DB;
	
	/**
	 * Database object singletone reference
	 */
	var $SAM_DB;
	
	/**
	 * Table fields translation list
	 */
	protected $field_mappings = array(
		'historylist' => array(
			//'id' => 'id', // We should rely on autoincrement
			'date_played' => 'date_played',
			'songtype' => 'song_type', // needs mapping
			//'id_subcat', // not in SAM
			'genre' => 'id_genre', // Can be interpreted from SAMBC songlist, but needs more work
			'duration' => 'duration', // needs conversion to secconds N/1000
			'artist' => 'artist',
			'title' => 'title',
			'album' => 'album',
			'composer' => 'composer',
			'albumyear' => 'year',
			'trackno' => 'track_no',
			//'disc_no' // Not in SAM
			'label' => 'publisher',
			//'copyright' => '', // Might be label or feeagency
			'isrc' => 'isrc',
			
			// To avoid MySQL warnings
			'subcat' => 'id_subcat',
			'original_artist' => 'original_artist',
			'copyright' => 'copyright',
		),
		
		'songlist' => array(
			//'id' => 'id',
			'filename' => 'path',
			'enabled' => 'enabled', // Just mark all as enabled
			'date_played' => 'date_played',
			'date_artist_played' => 'artist_played',
			'count_played' => 'count_played',
			'playlimit_count' => 'play_limit',
			'playlimit_action' => 'limit_action', // This will be tough - 'remove' or 'erase'
			'date_added' => 'start_date',
			//'end_date' => 'end_date',
			'songtype' => 'song_type', // needs mapping
			'genre' => 'id_genre', // Can be interpreted from SAMBC songlist, but needs more work
			'weight' => 'weight',
			'duration' => 'duration', // needs conversion to secconds N/1000,
			//'fade_type', // Not sure about this one
			'artist' => 'artist',
			//'artist' => 'original_artist',// Not in SAM
			'title' => 'title',
			'album' => 'album',
			'composer' => 'composer',
			'albumyear' => 'year',
			'trackno' => 'track_no',
			// 'disc_no', // Not in SAM
			'label' => 'publisher',
			'copyright' => 'copyright', // Not in SAM
			'isrc' => 'isrc',
			'info' => 'comments',
			//'sweepers' => 'sweepers', // No such thing in SAMBC
			'picture' => 'album_art', // TODO: Mention moving files in documentation
			'buycd' => 'buy_link',
			'overlay' => 'overlay', // Needs conversion 'no' = 0, 'yes' = 1
			//'date_played' => 'tdate_played',
			//'date_artist_played' => 'tartist_played',
			
			'id_subcat' => 'id_subcat',
			'original_artist' => 'original_artist',
			'enabled' => 'enabled',
			
			'bpm' => 'bpm', // Will be selected from xfade
			'xfade' => 'cue_times', //&c=1232&e=241788&i=25425&o=235141 parse_str N/1000 and convert to float
		),
		
	);
	
	// &x=-3000&fie=1&fit=1042&fil=80&fic=1&foe=1&fot=5000&fol=90&foc=1
	// &x=-3000&fie=1&fit=1042&fil=80&foe=1&fot=1146&fol=90
	/**
	 * SAMBC xfade to RDJ cue_times mapping
	 */
	var $xfade_map = array(
		'c' => 'sta', // Start cue point
		'x' => 'xta', // Fixed cross fade point. Negative values from end or positive from start.
		'e' => 'end', // End cue point
		'i' => 'int', // Intro cue pint
		'o' => 'out', // Outro cue point
		'f' => '', // Fade out cue point
		'bmp' => 'bpm', // BPM - typo in SAMDB (morons!)
		'fit' => 'fin', // Fade in duration (relative ms)
		'fot' => 'fou', // Fade out duration (relative ms)
		'fil' => '', // Fade in volume (percent)
		'fol' => '', // Fade out volume (percent)
		'fie' => '', // Fade in enabled
		'foe' => '', // Fade out enabled
		'fic' => '', // Fade in curve: 0=linear, 1=S-curve, 2=Exponential
		'foc' => '', // Fade out curve
		'xf' => '', // Cross-fade type: 0=disabled, 1=Fixed point (param x), 2=Auto detect, 
		'ft' => '', // XFade threashold dB
		'fs' => '', // Min. autofade time
		'fb' => '', // Max. autofade time
		'gl' => '', // gain level 6dB = 127, -6dB = -127
	);
	
	protected $limit_actions = array(
		'none' => 0,
		'delete' => 1, // remove in RDJ
		'erase' => 2, // delete in RDJ
	);
	
	/**
	 * Song type mapping
	 */
	protected $songtypes = array(
		'S' => 0, // Normal Song
		'J' => 1, // Jingle
		'I' => 2, // Station ID -> Sweepers
		'A' => 4, // Advertisement -> Commercial
		'N' => 10,// News
		'V' => 8, // Interviews -> Podcasts
		'X' => 1, // Sound FX -> Jingle
		'C' => 6, // Unknown content -> Other
		'?' => 6, // Unknown content -> Other
		'url' => 5 // Internet stream (used internally)
	);
	 
	protected $genre_mapping = array();
	
	protected $cat_mapping = array();
	
	protected $subcat_mapping = array();
	
	/**
	 * Map SAMBC songtypes to RDJ categories
	 */
	protected $types_map = array(
		'S' => 'music', // 1 in RDJ DB
		'X' => 'sound effects', // 2 in RDJ DB
		//'sweepers', // 3 in RDJ DB
		'I' => 'station ids', // 4 in RDJ DB
		'J' => 'jingles', // 5 in RDJ DB
		//'promos', // 6 in RDJ DB
		'A' => 'commercials', // 7 in RDJ DB
		'N' => 'news', // 8 in RDJ DB
		'V' => 'interviews', // 9 in RDJ DB
		'?' => 'radio shows', // 10 in RDJ DB
		'U' => 'radio streams', // 11 in RDJ DB	
	);
	
	function __construct(){
		try {
			$options = array(
				PDO::ATTR_AUTOCOMMIT=>TRUE,
				PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
			);
			if(SAM_DB == 'firebird'){
				$charset_search = array('/^cp-?(\d+)/i','/^iso-?(\d+)-(\d+)/i','/^utf-?(\d+)/i');
				$charset_replace = array('WIN\1','ISO\1_\2','UTF\1');
				$charset = preg_replace($charset_search, $charset_replace, SAM_CHARSET);
				$fb_charsets = array(
					'BINARY','OCTETS','ASCII','UTF8',
					'ISO8859_1','ISO8859_2','ISO8859_3','ISO8859_4','ISO8859_5','ISO8859_6','ISO8859_7','ISO8859_8','ISO8859_9','ISO8859_13',
					'WIN1250','WIN1251','WIN1252','WIN1253','WIN1254','WIN1255','WIN1256','WIN1257','WIN1258',
					'DOS437','DOS737','DOS775','DOS850','DOS852','DOS857','DOS858','DOS860','DOS861','DOS862','DOS863','DOS864','DOS865','DOS866','DOS869',
					'BIG_5','KSC_5601','SJIS_0208','EUCJ_0208','GB_2312','CP943C','TIS620',
					'KOI8R','KOI8U','CYRL'
				);
				if(!in_array($charset,$fb_charsets)){
					$charset = 'NONE';
				}
				$connect_string = "firebird:dbname=".SAM_HOST.":".SAM_DATABASE.";charset=".$charset;
			} else {
				$connect_string = "mysql:dbname=".SAM_DATABASE.";host=".SAM_HOST;
			}
			$this->SAM_DB = new PDO ($connect_string, SAM_USER, SAM_PASS, $options);
		} catch (PDOException $e) {
			var_dump($connect_string);
			//header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
			echo "Failed to get SAM DB handle: " . $e->getMessage() . "\n";
			exit;
		}
		
		try {
			$options = array(
				PDO::ATTR_AUTOCOMMIT=>FALSE,
				PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
			);
			$this->RDJ_DB = new PDO ("mysql:dbname=".RDJ_DATABASE.";host=".RDJ_HOST, RDJ_USER, RDJ_PASS, $options);
		} catch (PDOException $e) {
			//header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
			echo "Failed to get MySQL handle: " . $e->getMessage() . "\n";
			exit;
		}
	}
	
	private function getMapping($table){
		if(isset($this->field_mappings[$table])){
			return $this->field_mappings[$table];
		}
	} 
	
	/**
	 * Migrate SAMBC historylist
	 */
	function migrate_histroylist(){
		$table_sql = array();
		$insert_part = '';
		$data_sql = array();
		$sam_table = 'historylist';
		$rdj_table = 'history';
		$mapping = $this->getMapping($sam_table);		
		
		$result = $this->SAM_DB->query("SELECT COUNT(*) FROM $sam_table");
		$total_rows = $result->fetch(PDO::FETCH_NUM);
		$result->closeCursor();
		$total_rows = $remaining_rows = (int)$total_rows[0];
		
		// TODO: Export history data since date X
		$days = 90;
		$date_played = 'TIMESTAMPDIFF(DAY, date_played, NOW())>'.$days;
		$sambc_sql = "SELECT * FROM $sam_table ORDER BY ID ASC";
		$sambc_sql = "SELECT h.*, LOWER(s.genre) genre
						FROM historylist h
						LEFT JOIN songlist s ON s.ID = h.songID;";
		
		$query = $this->SAM_DB->prepare($sambc_sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
		$query->execute();
		
		$rows_processed = 0;
		$sql_out = array();
		
		$mysql_charset = mysql_charset(TARGET_CHARSET);
		
		if(WORK_MODE == WORK_MODE_FILE){
			file_put_contents(OUTPUT_FILE, '');
			$sql_out[] = "-- SAMBC Firebird to MySQL migration v".VERSION;
			$sql_out[] = "-- Generated at: ".date('Y-m-d H:i:s');
			$sql_out[] = "-- Host: localhost    Database: ".RDJ_DATABASE;
			$sql_out[] = "-- ------------------------------------------------------";
			$sql_out[] = "USE `".RDJ_DATABASE."`;";
			$sql_out[] = "\n";
			// this emulates mysqldump 5.6.12
			$sql_out[] = "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
			/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
			/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
			/*!40101 SET NAMES $mysql_charset */;
			/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
			/*!40103 SET TIME_ZONE='+00:00' */;
			/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
			/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
			/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
			/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
			/*!40101 SET @saved_cs_client     = @@character_set_client */;
			/*!40101 SET character_set_client = $mysql_charset */;
			/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
			/*!50003 SET sql_mode              = '' */ ;
			/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
			/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
			/*!50003 SET @saved_col_connection = @@collation_connection */ ;";
		}
		
		if(WORK_MODE == WORK_MODE_INSERT){
			// Set MySQL connection charset
			$charset_sql = "SET character_set_results = '$mysql_charset',
			character_set_client = '$mysql_charset',
			character_set_connection = '$mysql_charset',
			character_set_database = '$mysql_charset',
			character_set_server = '$mysql_charset'";
			$this->RDJ_DB->exec($charset_sql);
		}
		
		echo "Processing $sam_table => `$rdj_table`\n";
		echo "Total rows: ".$total_rows."\n";
		
		while( $row = $query->fetch(PDO::FETCH_ASSOC) ){

			if(empty($row)){
				break;
			}
			$row = array_change_key_case($row, CASE_LOWER);
			
			// Convert encoding
			if( TARGET_CHARSET != SAM_CHARSET ){
				$row = iconv_deep(SAM_CHARSET, TARGET_CHARSET.'//TRANSLIT', $row);
			}
			if(empty($insert_part)){
				$insert_part = "INSERT IGNORE INTO `$rdj_table`";
				$insert_part .= "(`".implode('`,`', $mapping)."`) VALUES";
				$table_sql[] = $insert_part;
			}
			
			$row_out = array();
			
			foreach( $mapping as $field => $target_field ){

				$value = isset($row[$field])? $row[$field] : NULL;

				if(is_numeric($value)){
					$value = strpos($value,'.')? (float)$value : (int)$value;
				}
				if($field == 'albumyear'){
					if( (int)$value == 0 ) $value = 1900;
				}
				if($field == 'songtype'){
					$value = isset($this->songtypes[$value])? $this->songtypes[$value] : 6; // Set unknown to Other
				}
				if($field == 'duration'){
					$value = round($value/1000, 3); // Convert milliseconds to seconds
				}
				if($field == 'genre'){
					$value = $this->getGenreID($value);
				}
				if( in_array($target_field, array('original_artist','copyright')) ){
					$value = '';
				}
				if( in_array($target_field, array('id_subcat')) ){
					$value = 0;
				}
				
				// Quote string fields
				if(is_string($value)){
					$value = $this->RDJ_DB->quote($value);
				}
				if($value === NULL){
					$value = 'NULL';
				}
				$row_out[$target_field] = $value;
			}
			$data_sql[] = "(".implode(",", $row_out).")";
			
			$rows_processed++;
			$remaining_rows--;
			
			// Prepare and insert each MAX_INSERT_ROWS
			if( ($remaining_rows >= MAX_INSERT_ROWS && $rows_processed >= MAX_INSERT_ROWS) || ($remaining_rows < MAX_INSERT_ROWS && $remaining_rows <= 0 )){
				
				$table_sql[] = implode(",\n", $data_sql).";\n";
				if(WORK_MODE == WORK_MODE_INSERT){
					$mysql_sql = implode("\n", $table_sql);
					$this->insertMySQL($mysql_sql);
				}
				if(WORK_MODE == WORK_MODE_FILE){
					$sql_out[] = implode("\n", $table_sql);
					file_put_contents(OUTPUT_FILE, implode("\n", $sql_out), FILE_APPEND);
					echo 'Exported '.$rows_processed." rows. Remaining rows: $remaining_rows\n";
				}
				
				$table_sql = $data_sql = $sql_out = array();
				$table_sql[] = "-- More data for table `$rdj_table`";
				$table_sql[] = $insert_part;
				$rows_processed = 0;
			}
		}
	}
	
	/**
	 * Migrate SAMBC songlist
	 */
	public function migrate_songlist() {
		$table_sql = array();
		$insert_part = '';
		$data_sql = array();
		$sam_table = 'songlist';
		$rdj_table = 'songs';
		$mapping = $this->getMapping($sam_table);		
		
		$result = $this->SAM_DB->query("SELECT COUNT(*) FROM $sam_table");
		$total_rows = $result->fetch(PDO::FETCH_NUM);
		$result->closeCursor();
		$total_rows = $remaining_rows = (int)$total_rows[0];
		
		$sambc_sql = "SELECT * FROM $sam_table ORDER BY ID ASC";
		
		$query = $this->SAM_DB->prepare($sambc_sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
		$query->execute();
		
		$rows_processed = 0;
		$sql_out = array();
		
		$output_file = preg_replace('/(\.\w{2,4})/i', '_songs\\1', OUTPUT_FILE);
		$mysql_charset = mysql_charset(TARGET_CHARSET);
		
		if(WORK_MODE == WORK_MODE_FILE){
			// Clear the output file
			file_put_contents($output_file, '');
			$sql_out[] = "-- SAMBC Firebird to MySQL migration v".VERSION;
			$sql_out[] = "-- Generated at: ".date('Y-m-d H:i:s');
			$sql_out[] = "-- Host: localhost    Database: ".RDJ_DATABASE;
			$sql_out[] = "-- ------------------------------------------------------";
			$sql_out[] = "USE `".RDJ_DATABASE."`;";
			$sql_out[] = "\n";
			$sql_out[] = "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
			/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
			/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
			/*!40101 SET NAMES $mysql_charset */;
			/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
			/*!40103 SET TIME_ZONE='+00:00' */;
			/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
			/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
			/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
			/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
			/*!40101 SET @saved_cs_client     = @@character_set_client */;
			/*!40101 SET character_set_client = $mysql_charset */;
			/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
			/*!50003 SET sql_mode              = '' */ ;
			/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
			/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
			/*!50003 SET @saved_col_connection = @@collation_connection */ ;";
		}
		
		
		if(WORK_MODE == WORK_MODE_INSERT){
			// Set MySQL connection charset
			$charset_sql = "SET character_set_results = '$mysql_charset',
			character_set_client = '$mysql_charset',
			character_set_connection = '$mysql_charset',
			character_set_database = '$mysql_charset',
			character_set_server = '$mysql_charset'";
			$this->RDJ_DB->exec($charset_sql);
		}
		
		echo "Processing $sam_table => `$rdj_table`\n";
		echo "Total rows: ".$total_rows."\n";

		while( $row = $query->fetch(PDO::FETCH_ASSOC) ){
			if(empty($row)){
				break;
			}
			$row = array_change_key_case($row, CASE_LOWER);
			
			// Convert encoding
			if( TARGET_CHARSET != SAM_CHARSET ){
				$row = iconv_deep(SAM_CHARSET, TARGET_CHARSET.'//TRANSLIT', $row);
			}
			
			if( empty($row['filename']) || ( empty($row['title']) && empty($row['artist']) ) ){
				echo "!!! filename or artist and title missing from entry ID ".$row['id']."\n";
				continue;
			}
			
			if(empty($insert_part)){
				// INSERT IGNORE  - because filename must be unique
				$insert_part = "INSERT IGNORE INTO `$rdj_table`";
				$insert_part .= "(`".implode('`,`', $mapping)."`) VALUES";
				$table_sql[] = $insert_part;
			}
			
			$row_out = array();
			
			foreach( $mapping as $field => $target_field ){
				$value = isset($row[$field])? $row[$field] : NULL;
				
				if(is_numeric($value)){
					$value = strpos($value,'.')? (float)$value : (int)$value;
				}
				if($field == 'enabled'){
					$value = 1;
				}
				if($field == 'albumyear'){
					 $value = (int)$value;
				}
				if($field == 'songtype'){
					// Detect if the filename is a url
					if(preg_match('@^(ht|f)tp://@i',$row['filename'])){
						$value = 'url';
					}
					$value = isset($this->songtypes[$value])? $this->songtypes[$value] : 6; // Set unknown to Other
				}
				if($field == 'duration'){
					$value = round($value/1000, 3); // Convert milliseconds to seconds
				}
				if($field == 'genre'){
					$value = $this->getGenreID($value);
				}
				if($field == 'overlay'){
					$value = strtolower($value) == 'yes'? 1 : 0;
				}
				if($field == 'playlimit_action'){
					$value = strtolower($value);
					$value = isset($this->limit_actions[$value])? $this->limit_actions[$value] : 0;
				}
				if($field == 'category'){
					$type = strtoupper($row['songtype']);
					$cat = isset($this->types_map[$value])? $this->types_map[$value] : NULL;
					$value = $this->getCatID($cat);
				}
				if($field == 'xfade'){
					$duration = $row['duration']/1000;
					$cue_times = $this->getCueTimes($value, $duration);
					$row['bpm'] = isset($cue_times['bpm'])? (float)$cue_times['bpm'] : 0;
					unset($cue_times['bpm']);
					$value = http_build_query($cue_times);
				}
				if($field == 'bpm' && isset($row['bpm'])){
					$value = $row['bpm'];
				}
				if( in_array($target_field, array('original_artist','copyright')) ){
					$value = '';
				}
				if( in_array($target_field, array('id_subcat')) ){
					$value = 0;
				}
				
				// Quote string fields
				if(is_string($value)){
					$value = $this->RDJ_DB->quote($value);
				}
				if($value === NULL){
					$value = 'NULL';
				}
				$row_out[$target_field] = $value;
			}
			$data_sql[] = "(".implode(",", $row_out).")";
			
			$rows_processed++;
			$remaining_rows--;
			
			// Prepare and insert each MAX_INSERT_ROWS
			if( ($remaining_rows >= MAX_INSERT_ROWS && $rows_processed >= MAX_INSERT_ROWS) || ($remaining_rows < MAX_INSERT_ROWS && $remaining_rows <= 0 )){
				
				$table_sql[] = implode(",\n", $data_sql).";\n";
				if(WORK_MODE == WORK_MODE_INSERT){
					$mysql_sql = implode("\n", $table_sql);
					$this->insertMySQL($mysql_sql);
				}
				if(WORK_MODE == WORK_MODE_FILE){
					$sql_out[] = implode("\n", $table_sql);
					file_put_contents($output_file, implode("\n", $sql_out), FILE_APPEND);
					echo 'Exported '.$rows_processed." rows. Remaining rows: $remaining_rows\n";
				}
				
				$table_sql = $data_sql = $sql_out = array();
				$table_sql[] = "-- More data for table `$rdj_table`";
				$table_sql[] = $insert_part;
				$rows_processed = 0;
			}
		}

	}

	/**
	 * Get RDJ genre id from string
	 */
	private function getGenreID($genre = ''){
		$id = 0;	
		if(empty($this->genre_mapping)){
			$query = $this->RDJ_DB->query("SELECT * FROM `genre`");
			$genre_mapping = $query->fetchAll(PDO::FETCH_ASSOC);
			foreach($genre_mapping as $row){
				$this->genre_mapping[strtolower($row['name'])] = (int)$row['id'];
			}
		}
		$genre = strtolower(trim($genre));
		if(isset($this->genre_mapping[$genre])){
			$id = $this->genre_mapping[$genre];
		}
		return $id;
	}
	
	/**
	 * Get category id from string
	 */
	 private function getCatID($cat){
	 	$id = 0;
		if(empty($this->cat_mapping)){
			$query = $this->RDJ_DB->query("SELECT * FROM `category`");
			$cat_mapping = $query->fetchAll(PDO::FETCH_ASSOC);
			foreach($cat_mapping as $row){
				$this->cat_mapping[strtolower($row['name'])] = (int)$row['id'];
			}
		}
		$cat = strtolower(trim($cat));
		if(isset($this->cat_mapping[$cat])){
			$id = $this->cat_mapping[$cat];
		}
		return $id;
	 }
	
	/**
	 * Get subcategory id from string
	 */
	 private function getSubcatID($cat){
	 	$id = 0;
		if(empty($this->subcat_mapping)){
			$query = $this->RDJ_DB->query("SELECT * FROM `subcategory`");
			$subcat_mapping = $query->fetchAll(PDO::FETCH_ASSOC);
			foreach($subcat_mapping as $row){
				$this->subcat_mapping[strtolower($row['name'])] = (int)$row['id'];
			}
		}
		$cat = strtolower(trim($cat));
		if(isset($this->subcat_mapping[$cat])){
			$id = $this->subcat_mapping[$cat];
		}
		return $id;
	 }
	
	/**
	 * Get xfade to cue_times mapping
	 */
	private function getCueTimes($xfade, $duration){
		parse_str(trim($xfade,'&'),$xfade);
		$cue_times = array();
		if(empty($xfade)){
			return $xfade;
		}
		foreach($xfade as $k => $val){
			if(isset($this->xfade_map[$k]) && !empty($this->xfade_map[$k])){
				$ct = $this->xfade_map[$k];
				if($k != 'bmp') $val = $val/1000;
				if($val < 0) $val = $duration + $val;
				$cue_times[$ct] = $val;
			}
		}
		return $cue_times;
	}
	
	/**
	 * Execute MySQL insert statement
	 * @param string mysql_sql Valid SQL statement
	 */
	private function insertMySQL($mysql_sql){
		$this->RDJ_DB->beginTransaction();
		$query = $this->RDJ_DB->prepare($mysql_sql);
		try{
			$result = $query->execute();
		} catch (PDOException $e) {
			echo $e->getMessage()."\n";
			echo "[!!!]\nFAIL: doing rollback!\n";
			$this->RDJ_DB->rollBack();
			return false;
		}
		echo 'Inserted '.$query->rowCount()." rows\n";
		return $this->RDJ_DB->commit();
	}

}

?>