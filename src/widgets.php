<?

if( date("N") != 1 ){ // this cron only runs on monday
	exit;
}

ini_set( 'display_errors', 0 );
error_reporting( 0 );

if( !class_exists( "DB" ) ){
	class DB{
		var $server = "liveserver";
		var $user = "liveuser";
		var $pass = "livepass";
		var $database;
		var $dblink;
		var $results;
		var $numRows;
		var $lastinsertid;
		
		//MySQL Connect Function
		//Performs the connection, database select and query.
		//Stored the number of rows on a successful query.
		public function DB( $set_db = '' ){
			if( empty( $set_db ) ){
				$this->database = 'db';
			}
		}
		
		function databaseArray( $obj ){
			return mysql_fetch_array( $obj['result'] );
		}
		
		function databaseQuery( $query, $database = '' ){
			$ret = array(
				'result' => false,
				'number' => 0,
				'query' => $query,
				'error' => ''
			);
			
			if( !empty( $database ) ){
				$this->database = $database;
			}
			
			if( $_SERVER['HTTP_HOST'] == 'localhost' ){
				$this->server = "localserver";
				$this->user = "localuser";
				$this->pass = "localpass";
			}
			
			$success = true;
			
			if($this->dblink = mysql_connect($this->server, $this->user, $this->pass)){
				if(mysql_select_db($this->database, $this->dblink)){
					if($this->results = @mysql_query($query, $this->dblink)){
						$ret['result'] = $this->results;
						$this->lastinsertid = @mysql_insert_id();

						$this->numRows = @mysql_num_rows($this->results);
						$ret['number'] = $this->numRows;
						$success = true;
					}
					
					if( $this->results === false ){
						$ret['error'] = mysql_error();
					}

				}

				else $success = false;
			}

			else $success = false;
			
			return $ret;
		}

		//MySQL get number of rows function
		//Returns the number of rows in a dataset.
		function mysqlGetNumRows(){
			return $this->numRows;
		}

		public function mysql_clean( $str ){
			return is_array($str) ? array_map(array($this, 'mysql_clean'), $str) : str_replace("\\", "\\\\", htmlspecialchars((get_magic_quotes_gpc() ? stripslashes($str) : $str), ENT_QUOTES)); 
			
		}
		
		function mysqlGetResults(){
			return $this->results;
		}
		
		function LastInsertID(){
			return $this->lastinsertid;	
		}
		
		function __destruct(){
			@mysql_close($this->dblink);
		}
	}
}

function getRawHTML( $url = '' ){
		$ch = curl_init( $url );
		ob_start();
		
		// grab URL and pass it to the browser
		curl_exec( $ch );
		$x = ob_get_contents();
		
		ob_end_clean();
		curl_close( $ch );
		return $x;
}

function ALERT_THE_TROOPS( $subject, $data ){
	@mail( 'user@example.com', $subject, $data, 'From: noreply@example.com' );
}

function grabwidget( $url = '' ){
	$ret = array(
		'data' => array(),
		'errors' => array()
	);
	
	if( !empty( $url ) ){
		$doc = new DOMDocument();
		
		$doc->loadHTML( getRawHTML( $url ) );	
		
		$items = $doc->getElementsByTagName( 'select' );
		
		foreach( $items as $item ){
			if( preg_match( "/^widget widgetPropertys*$/", $item->getAttribute('name') ) ){
				$options = $item->getElementsByTagName( 'option' );
				foreach( $options as $option ){
					if( strtolower( $option->getAttribute( 'value' ) ) != 'select' ){
						$ret['data'][] = $option->getAttribute( 'value' );
					}
				}
				unset( $option );
				
				break;
			}
		}
		unset( $item );
	}
	
	return $ret;
}

function grabNano( $url = '' ){
	$ret = array(
		'data' => array(),
		'errors' => array()
	);
	
	if( !empty( $url ) ){
		$doc = new DOMDocument();
		
		$doc->loadHTML( getRawHTML( $url ) );	
		
		$primary = $doc->getElementById( 'primary' );
		
		$items = $primary->getElementsByTagName( 'table' );
		
		foreach( $items as $item ){
			$items2 = $item->getElementsByTagName( 'h3' );
			$items3 = $item->getElementsByTagName( 'span' );
			$ret['data'][] = trim( $items2->item( 0 )->textContent ) . ' ' . trim( $items3->item( 0 )->textContent );
		}
	}
	
	return $ret;
}

$output = "";

$DB = new DB; // make our db connection

$last_seen = $DB->databaseQuery( "
	SELECT timestamp 
	FROM scrape_tracker
	WHERE scrape_type = 'widget' 
	AND scrape_hit = 1 
	ORDER BY id DESC 
	LIMIT 1
" );

$last_seen_text = "Widget Property has never been discovered.";

if( $last_seen['number'] ) {
	$last_seen_row = $DB->databaseArray( $last_seen );

	$last_seen_text = "Widget Property was last seen on " . date( 'l, F jS, Y', strtotime( $last_seen_row['timestamp'] ) ) . ".";
}

// grab out widget lists
$items1 = grabwidget( 'http://www.example.com/url1' );
$items2 = grabwidget( 'http://www.example.com/url2' );

$inswidgetStr = "widgetPropertys:\n" . implode( "\n", $items1['data'] ) . "\n\nWeekenders:\n" . implode( "\n", $items2['data'] );

// grab last record for science!
$query = $DB->databaseQuery( "
	SELECT scrape_output 
	FROM scrape_tracker 
	WHERE scrape_type = 'widget'
	ORDER BY id DESC
	LIMIT 1
" );

$widgetsAsText = '';

// if we have a last inserty
if( $query['number'] ){
	$row = $DB->databaseArray( $query );
	$widgetsAsText = $row['scrape_output'];
	$widgetsAsText = str_replace( '&amp;', '&', $widgetsAsText );
}

$foundwidgetProperty = stristr( $inswidgetStr, 'Widget Property' ) ? 1 : 0;

$inswidgetStr .= "\n\nFound Widget Property: " . ( $foundwidgetProperty ? 'Yes' : 'No' );

// same as previous, do nothing
if( !( $widgetsAsText && $widgetsAsText == $inswidgetStr ) ){
	if( $foundwidgetProperty ){
		ALERT_THE_TROOPS( '**** Widget Property On Sale!', $inswidgetStr );
	}
	
	$ins = $DB->databaseQuery( "
		INSERT 
		INTO scrape_tracker ( scrape_type, scrape_output, scrape_hit )
		values( 'widget', '" . $DB->mysql_clean( $inswidgetStr ) . "', $foundwidgetProperty )
	" );
	
	$inswidgetStr .= "\n\n(scrape inserted: yes)";
}else{
	$inswidgetStr .= "\n\n(scrape inserted: no)";
}

$output .= $inswidgetStr . "\n\n" . $last_seen_text;
echo $output;
exit;

?>