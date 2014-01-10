<?php
/**
 * @package Migrate SAMBC data to Radio DJ v0.1
 * @author Andis Grosšteins
 * @copyright (C) 2014 - Andis Grosšteins (http://axellence.lv)
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Migration class for SAM Broadcaster to Radio DJ data migration
 * Currently supports only history data migration
 * Work in progres...
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
			'id' => 'id', // I'll rely on autoincrement
			'date_played' => 'date_played',
			'songtype' => 'song_type', // needs mapping
			//'id_subcat', // not in SAM
			'genre' => 'id_genre', // Can be intepreted from SAMBC songlist, but needs more work
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
			'ISRC' => 'isrc',
			
			// To avoid MySQL warnings
			'subcat' => 'id_subcat',
			'original_artist' => 'original_artist',
			'copyright' => 'copyright',
		), 
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
	);
	 
	private $genre_mapping = array();
	
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
		
		$sambc_sql = "SELECT * FROM $sam_table ORDER BY ID ASC";
		$sambc_sql = "SELECT h.*, LOWER(s.genre) genre
						FROM historylist h
						LEFT JOIN songlist s ON s.ID = h.songID;";
		
		$query = $this->SAM_DB->prepare($sambc_sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
		$query->execute();
		
		$rows_processed = 0;
		$sql_out = array();
		
		if(WORK_MODE == WORK_MODE_FILE){
			file_put_contents(OUTPUT_FILE, '');
			$sql_out[] = "-- SAMBC Firebird to MySQL migration v".VERSION;
			$sql_out[] = "-- Generated at: ".date('Y-m-d H:i:s');
			$sql_out[] = "-- Host: localhost    Database: ".RDJ_DATABASE;
			$sql_out[] = "--------------------------------------------------------";
			$sql_out[] = "USE `".RDJ_DATABASE."`;";
			$sql_out[] = "\n";
			// Clear the output file
			file_put_contents(OUTPUT_FILE, implode("\n", $sql_out));
		}
		if(WORK_MODE == WORK_MODE_INSERT){
			// Set MySQL connection charset
			$mysql_charset = mysql_charset(TARGET_CHARSET);
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
				var_dump($query);
				break;
			}
			$row = array_change_key_case($row, CASE_LOWER);
			
			// Convert encoding
			if( TARGET_CHARSET != SAM_CHARSET ){
				$row = iconv_deep(SAM_CHARSET, TARGET_CHARSET.'//TRANSLIT', $row);
			}
			if(empty($insert_part)){
				$insert_part = "INSERT INTO `$rdj_table`";
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
				
				var_dump($remaining_rows, $rows_processed, ($remaining_rows >= MAX_INSERT_ROWS && $rows_processed >= MAX_INSERT_ROWS), ($remaining_rows < MAX_INSERT_ROWS && $remaining_rows < 1 ));
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
		//var_dump($table_sql);
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