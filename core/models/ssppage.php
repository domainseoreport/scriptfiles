<?php

/*
* @name: Turbo Website Reviewer
*
*/

class SSPPAGE {
	/**
	 * Create the data output array for the DataTables rows
	 * 
	 *  @param  array $columns Column information array
	 *  @param  array $data    Data from the SQL get
	 *  @return array          Formatted data in a row based format
	 */
	static function data_output ( $columns, $data )
	{
		$out = array();
        if($data === NULL) $data = array();
		for ( $i=0, $ien=count($data) ; $i<$ien ; $i++ ) {
			$row = array();

			for ( $j=0, $jen=count($columns) ; $j<$jen ; $j++ ) {
				$column = $columns[$j];

				// Is there a formatter?
				if ( isset( $column['formatter'] ) ) {
					$row[ $column['dt'] ] = $column['formatter']( $data[$i][ $column['db'] ], $data[$i] );
				}
				else {
					$row[ $column['dt'] ] = $data[$i][ $columns[$j]['db'] ];
				}
			}

			$out[] = $row;
		}

		return $out;
	}


	/**
	 * Paging
	 *
	 * Construct the LIMIT clause for server-side processing SQL query
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array $columns Column information array
	 *  @return string SQL limit clause
	 */
	static function limit ( $request, $columns )
	{
		$limit = '';

		if ( isset($request['start']) && $request['length'] != -1 ) {
			$limit = "LIMIT ".intval($request['start']).", ".intval($request['length']);
		}

		return $limit;
	}


	/**
	 * Ordering
	 *
	 * Construct the ORDER BY clause for server-side processing SQL query
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array $columns Column information array
	 *  @return string SQL order by clause
	 */
	static function order ( $request, $columns )
	{
		$order = '';

		if ( isset($request['order']) && count($request['order']) ) {
			$orderBy = array();
			$dtColumns = self::pluck( $columns, 'dt' );

			for ( $i=0, $ien=count($request['order']) ; $i<$ien ; $i++ ) {
				// Convert the column index into the column data property
				$columnIdx = intval($request['order'][$i]['column']);
				$requestColumn = $request['columns'][$columnIdx];

				$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $columns[ $columnIdx ];

				if ( $requestColumn['orderable'] == 'true' ) {
					$dir = $request['order'][$i]['dir'] === 'asc' ?
						'ASC' :
						'DESC';

					$orderBy[] = '`'.$column['db'].'` '.$dir;
				}
			}

			$order = 'ORDER BY '.implode(', ', $orderBy);
		}

		return $order;
	}

    /*
    * @name: Turbo - Website - Reviewer
    *
    */

	/**
	 * Searching / Filtering
	 * This file part of Turbo Website Reviewer
	 * Construct the WHERE clause for server-side processing SQL query.
	 *
	 * NOTE this does not match the built-in DataTables filtering which does it
	 * word by word on any field. It's possible to do here performance on large
	 * databases would be very poor
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array $columns Column information array
	 *  @param  array $bindings Array of values for PDO bindings, used in the
	 *    sql_exec() function
	 *  @return string SQL where clause
	 */
	static function filter ( $request, $columns, &$bindings )
	{
		$globalSearch = array();
		$columnSearch = array();
		$dtColumns = self::pluck( $columns, 'dt' );
       if(!isset($request['columns']))
            $request['columns'] = array();
		if ( isset($request['search']) && $request['search']['value'] != '' ) {
			$str = $request['search']['value'];

			for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
				$requestColumn = $request['columns'][$i];
				$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $columns[ $columnIdx ];

				if ( $requestColumn['searchable'] == 'true' ) {
					$binding = self::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
					$globalSearch[] = "`".$column['db']."` LIKE ".$binding;
				}
			}
		}

		// Individual column filtering
		for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
			$requestColumn = $request['columns'][$i];
			$columnIdx = array_search( $requestColumn['data'], $dtColumns );
			$column = $columns[ $columnIdx ];

			$str = $requestColumn['search']['value'];

			if ( $requestColumn['searchable'] == 'true' &&
			 $str != '' ) {
				$binding = self::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
				$columnSearch[] = "`".$column['db']."` LIKE ".$binding;
			}
		}

		// Combine the filters into a single string
		$where = '';

		if ( count( $globalSearch ) ) {
			$where = '('.implode(' OR ', $globalSearch).')';
		}

		if ( count( $columnSearch ) ) {
			$where = $where === '' ?
				implode(' AND ', $columnSearch) :
				$where .' AND '. implode(' AND ', $columnSearch);
		}

		if ( $where !== '' ) {
			$where = 'WHERE '.$where;
		}

		return $where;
	}


	/**
	 * Perform the SQL queries needed for an server-side processing requested,
	 * utilising the helper functions of this class, limit(), order() and
	 * filter() among others. The returned array is ready to be encoded as JSON
	 * in response to an SSP request, or can be modified if needed before
	 * sending back to the client. SS CODE: B--ALAJ--I
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array $sql_details SQL connection details - see sql_connect()
	 *  @param  string $table SQL table to query
	 *  @param  string $primaryKey Primary key of the table
	 *  @param  array $columns Column information array
	 *  @return array          Server-side processing response array
	 */
	static function simple ( $request, $sql_details, $table, $primaryKey, $columns, $columns2 )
	{
	   $bindings = array();
	   $db = self::sql_connect( $sql_details );

		// Build the SQL query string from the request 
		$limit = self::limit( $request, $columns );
		$order = self::order( $request, $columns );
		$where = self::filter( $request, $columns, $bindings );

  		// Main query to actually get the data
		$data = self::Ssql_exec($db, $bindings,
			"SELECT SQL_CALC_FOUND_ROWS `".implode("`, `", self::pluck($columns, 'db'))."`
			 FROM `$table`
			 $where
			 $order
			 $limit"
		);
                
		// Data set length after filtering
		$resFilterLength = self::sql_exec( $db,
			"SELECT FOUND_ROWS()"
		);
		$recordsFiltered = $resFilterLength[0][0];
		// Total data set length
		$resTotalLength = self::sql_exec( $db,
			"SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table`"
		);
		$recordsTotal = $resTotalLength[0][0];
        
		/*
		 * Output
		 */   
		return array(
			"draw"            => intval( $request['draw'] ),
			"recordsTotal"    => intval( $recordsTotal ),
			"recordsFiltered" => intval( $recordsFiltered ),
			"data"            => self::data_output( $columns2, $data )
		);
	}


	/**
	 * Connect to the database
	 *
	 * @param  array $sql_details SQL server connection details array, with the
	 *   properties:
	 *     * host - host name
	 *     * db   - database name
	 *     * user - user name
	 *     * pass - user password
	 * @return resource Database connection handle
	 */
	static function sql_connect ( $sql_details )
	{
		try {
			$db = @new PDO(
				"mysql:host={$sql_details['host']};dbname={$sql_details['db']}",
				$sql_details['user'],
				$sql_details['pass'],
				array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION )
			);
			      $db->query("SET NAMES 'utf8'");
		}
		catch (PDOException $e) {
			self::fatal(
				"An error occurred while connecting to the database. ".
				"The error reported by the server was: ".$e->getMessage()
			);
		}

		return $db;
	}


	/**
	 * Execute an SQL query on the database
	 *
	 * @param  resource $db  Database handler
	 * @param  array    $bindings Array of PDO binding values from bind() to be
	 *   used for safely escaping strings. Note that this can be given as the
	 *   SQL query string if no bindings are required.
	 * @param  string   $sql SQL query to execute.
	 * @return array         Result from the query (all rows)
	 */
	static function sql_exec ( $db, $bindings, $sql=null )
	{
		// Argument shifting
		if ( $sql === null ) {
			$sql = $bindings;
		}

		$stmt = $db->prepare( $sql );
		//echo $sql;

		// Bind parameters
		if ( is_array( $bindings ) ) {
			for ( $i=0, $ien=count($bindings) ; $i<$ien ; $i++ ) {
				$binding = $bindings[$i];
				$stmt->bindValue( $binding['key'], $binding['val'], $binding['type'] );
			}
		}

		// Execute
		try {
			$stmt->execute();
		}
		catch (PDOException $e) {
			self::fatal( "An SQL error occurred: ".$e->getMessage() );
		}
       $result= $stmt->fetchAll();
		return $result;
	}
    
	static function Ssql_exec ($db, $bindings, $sql=null )
	{
		// Argument shifting
		if ( $sql === null ) {
			$sql = $bindings;
		}

		$stmt = $db->prepare( $sql );

		// Bind parameters
		if ( is_array( $bindings ) ) {
			for ( $i=0, $ien=count($bindings) ; $i<$ien ; $i++ ) {
				$binding = $bindings[$i];
				$stmt->bindValue( $binding['key'], $binding['val'], $binding['type'] );
			}
		}

		// Execute
		try {
			$stmt->execute();
		}
		catch (PDOException $e) {
			self::fatal( "An SQL error occurred: ".$e->getMessage() );
		}
        $loop = 0;
        $result = array();

        while ($arr = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $myPage = $editLink = ''; $myStatus = false;
        $result[$loop]['sort_order']= $arr['sort_order']; 
        $result[$loop]['page_name']= shortCodeFilter($arr['page_name']);
        $result[$loop]['page_title']= shortCodeFilter($arr['page_title']);
        $result[$loop]['posted_date']= date('jS F Y', strtotime($arr['posted_date']));  
        $myid = $arr['id'];
        
        if($arr['type'] == 'page'){
            $result[$loop]['type']= 'Internal Page';
            $myPage = createLink('page/'.$arr['page_url'],true);
            $editLink = adminLink('manage-pages/page/edit/'.$myid,true);
        } elseif($arr['type'] == 'internal'){
            $result[$loop]['type'] = 'Internal Link';
            $myPage = shortCodeFilter($arr['page_url']);
            $editLink = adminLink('manage-pages/link/edit/'.$myid,true);
        } elseif($arr['type'] == 'external'){
            $result[$loop]['type']= 'External Link';
            $myPage = $arr['page_url'];
            $editLink = adminLink('manage-pages/link/edit/'.$myid,true);
        }
        
        $myStatus = filter_var($arr['status'], FILTER_VALIDATE_BOOLEAN);
        
        $result[$loop]['status'] = ($myStatus ? '<a href="'.adminLink('manage-pages/status/disable/'.$myid,true).'" class="label label-success">Published</a>' : '<a href="'.adminLink('manage-pages/status/enable/'.$myid,true).'" class="label label-danger">Unpublished</a>');
        $result[$loop]['actions'] =  "<a class='btn btn-info btn-xs' href=".$editLink."> <i class='fa fa-edit'></i> &nbsp; Edit </a> ".
        "<a target='_blank' class='btn btn-success btn-xs' href=".$myPage."> <i class='fa fa-external-link'></i> &nbsp; Preview </a> ".
        '<a class="btn btn-danger btn-xs" onclick="return confirm(\'Are you sure you want to delete this item?\');" href='.adminLink('manage-pages/delete/'.$myid,true).'> <i class="fa fa-trash-o"></i> &nbsp; Delete </a>';
        $loop = $loop +1;
        }
		// Return all
		return $result;
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 * Internal methods
	 */

	/**
	 * Throw a fatal error.
	 *
	 * This writes out an error message in a JSON string which DataTables will
	 * see and show to the user in the browser.
	 * 
	 * @param  string $msg Message to send to the client
	 */
	static function fatal ( $msg )
	{
		echo json_encode( array( 
			"error" => $msg
		) );

		exit(0);
	}

	/**
	 * Create a PDO binding key which can be used for escaping variables safely
	 * when executing a query with sql_exec()
	 *
	 * @param  array &$a    Array of bindings
	 * @param  *      $val  Value to bind
	 * @param  int    $type PDO field type
	 * @return string       Bound key to be used in the SQL where this parameter
	 *   would be used.
	 */
	static function bind ( &$a, $val, $type )
	{
		$key = ':binding_'.count( $a );

		$a[] = array(
			'key' => $key,
			'val' => $val,
			'type' => $type
		);

		return $key;
	}


	/**
	 * Pull a particular property from each assoc. array in a numeric array, 
	 * returning and array of the property values from each item.
	 *
	 *  @param  array  $a    Array to get data from
	 *  @param  string $prop Property to read
	 *  @return array        Array of property values
	 */
	static function pluck ( $a, $prop )
	{
		$out = array();

		for ( $i=0, $len=count($a) ; $i<$len ; $i++ ) {
			$out[] = $a[$i][$prop];
		}

		return $out;
	}
}

?>