<?php

class Api2Db
{

	public $storage = array();

	function __construct($params)
	{
		// Временно
		error_reporting(E_ERROR | E_WARNING | E_PARSE);

		$this->params = $params;

		$this->db_connect();
		
	}

	protected function db_connect(){

		$connectionName = ($this->params['module']['connect']) ? $this->params['module']['connect'] : $this->params['config']['defaultConnection'];
		//$connection 	= array();
		$connection 	= $this->params['config']['connections'][$connectionName];



		// Создание коннекта
		if( !$this->params['connections'][$connectionName]['connect'] ){

			try{
				$this->params['connections'][$connectionName]['connect'] = new PDO ( 
					'mysql:host=' . $connection['host'] . ';dbname=' . $connection['db'], $connection['user'], $connection['password'],
					array(
						PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
					)
				);
				

				$this->params['connections'][$connectionName]['currentDB'] 	= $connection['db'];
				$this->params['connections']['currentConnect'] 				= $this->params['connections'][$connectionName]['connect'];
				$this->params['connections']['currentConnectionName'] 		= $connectionName;

				$this->storage['debug']['connection'][$connectionName]['log'][] 	=  "create connect $connectionName";

			}

			catch( PDOException $e ){
			
				$this->params['ret']['code'] 	= 'failed';
				$this->params['ret']['error'] 	= 'dberror';

				$this->storage['debug']['connection'][$connectionName]['error'][] = "Failed connection to $connectionName";
				
				return $params;

			}

		}

		// Смена коннекта
		if( $this->params['connections'][$connectionName]['connect'] and $this->params['connections']['currentConnectionName'] != $connectionName 
		){
			$this->params['connections']['currentConnectionName'] 	= $connectionName;
			$this->params['connections']['currentConnect'] 			= $this->params['connections'][$connectionName]['connect'];

		}

		// Выбор базы данных
		if( $this->params['connections'][$connectionName]['currentDB'] != $this->params->module['database'] && $this->params->module['database'] ){


			/*
			$result = db_query( $params, 'use database `' . $this->params->module['database'] . '`' );


			if($result->ret['code'] == 'success')
				$this->params['connections'][$connectionName]['currentDB'] = $this->params->module['database'];
			
			else{

				$this->params->ret['code'] 	= 'failed';
				$this->params->ret['error'] = 'dberror';

				//$this->params->storage->push( "Failed change database to {$this->params->module['database']} in $connectionName", 'debug->database->' . $this->params->query_count . "->error" );
			
			}*/
		}

	}


}