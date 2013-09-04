<?php

class Api2Db_Db
{

	static private $_instance = null;

	public static function & Instance()
	{
		if (is_null(self::$_instance))
		{
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private $connections    	= [];
	private $currentConnection;
	private $currentConnectionName;
	private $currentDB;

	private function __construct(){

		$this->storage 		= Api2Db_Storage::Instance();

	}

	public function __destruct(){}

	public function __clone(){
		trigger_error('Cloning instances of this class is forbidden.', E_USER_ERROR);
	}

	public function __wakeup(){
		trigger_error('Unserializing instances of this class is forbidden.', E_USER_ERROR);
	}


	final public function connect( $name_connect = false ){


		$db_conf = $this->storage->get_config()['db'];

		if( !$name_connect )
			$name_connect = $db_conf['defaultConnection'];

		$db_type = 'mysql';

		if( !empty( $db_conf['connections'][ $name_connect ]['type'] ) ){

			if( in_aray( $db_conf['connections'][ $name_connect ]['type'], ['mysql','oracle'] ) )
				$db_type = $db_conf['connections'][ $name_connect ]['type'];
		}



		if( !empty( $this->storage->get_config()['db']['connections'][ $name_connect ] ) ){

			switch ( $db_type ) {
				case 'mysql':
					return $this->connect_mysql( $db_conf['connections'][ $name_connect ], $name_connect );
					break;
				
			}

		}


		return true;
	}

	final public function connect_mysql( $config, $name_connect ){
		


		// Создание коннекта
		if( empty( $this->connections[$name_connect] ) ){

			try{

				$this->connections[$name_connect] = new PDO ( 
					'mysql:host=' . $config['server'] . ';dbname=' . $config['database'], $config['user'], $config['password'],
					[
						PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
					]
				);
				

				$this->currentDB							 					= $config['database'];
				$this->currentConnectionName									= $name_connect;
				$this->currentConnection										= &$this->connections[$name_connect];
				$this->storage->push_debug_db("create connect $name_connect");

				return true;
			}

			catch( PDOException $e ){
			


				$this->storage->push_debug_db( [
					'error' => ['message' => $e->getMessage(), 'code' => $e->getCode()],
					'text' 	=> "Failed connection to $name_connect"
				]);


				return false;
			}

		}

		return true;

	}


	final public function execute( $sql, $whence ){

		if( empty( $this->currentConnection ) )
			return false;
		

		$sql 	= $this->currentConnection->prepare( $sql );
		$start 	= microtime(true);

		$sql->execute();
		
		$time 	= round( microtime(true)-$start, 2 );
		$errsql = $sql->errorInfo();

		$log 	= [
			'sql' 			=> $sql->queryString,
			'connection' 	=> $this->currentConnectionName,
			'time' 			=> $time,
			'code' 			=> $errsql[0],
			'whence'		=> $whence
		];


		if( $errsql[0] != '00000')
			$log['error'] = $errsql[2];

		$this->storage->push_debug_db( $log );

		if( isset( $log['error'] ) )
			return false;
		else
			return $sql;
	}


	final public function select( $sql, $whence ){


		$sql = $this->execute( $sql, $whence );

		if( !empty( $sql ) )
			return $sql->fetchAll( PDO::FETCH_ASSOC );
		else
			return false;

	}




	final public function update( $sql, $whence ){

		if( $this->execute( $sql, $whence ) )
			return true;
		else
			return false;

	}


	final public function insert( $sql, $whence ){

		$sql = $this->execute( $sql, $whence );

		if( !empty( $sql ) )
			return $this->currentConnection->lastInsertId();
		else
			return false;

	}

	final public function delete( $sql, $whence ){

		if( $this->execute( $sql, $whence ) )
			return true;
		else
			return false;

	}

	final public function clear( $class ){

		if( get_parent_class( $class ) == 'Api2Db' ){

			$this->connections 				= [];
			$this->currentConnection   		= '';
			$this->currentConnectionName    = '';
			$this->currentDB				= '';
				
		}

	}

}