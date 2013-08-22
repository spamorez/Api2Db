<?php

require_once( dirname(__FILE__) . '/Api2Db_Functions.php');
require_once( dirname(__FILE__) . '/Api2Db_Storage.php');
require_once( dirname(__FILE__) . '/Api2Db_Converts.php');
require_once( dirname(__FILE__) . '/Api2Db_Actions.php');


class Api2Db {

	public  $config		= []; 		// Конфигурация
	public  $initErrors = []; 		// Ошибки инициализации
	public  $storage 	= [];		// Хранилище
	private $output 	= [];		// Вывод данных
	private $action; 				// текущее действие
	private $currentConnectionName; // Имя текущего соединения
	private $connections;			// Соединения с БД
	private $init;					// прошла ли инициализация
	private $checkStructure;		// Структура модуля



	function __construct( $params ){


		$def_params = [
			'actions' 	=> '',
			'converts' 	=> '',
			'functions' => ''
		];

		$this->storage 	= Api2Db_Storage::Instance();
		$this->storage->clear( $this );
		$params 		= array_replace_recursive( $def_params, $params );
		$this->init 	= true;

		if( isset( $params['convert_fields'] ) )
			$this->convert_fields = $params['convert_fields'];

		$this->storage->set_config( $params['config'] );

		$this->storage->add_modules( $params['modules'] );
		$this->storage->add_names( $params['names'] );
		$this->storage->add_errors( $params['errors'] );



		if( get_parent_class( $params['functions'] ) == 'Api2Db_Functions' )
			$this->functions = $params['functions'];
		else
			$this->functions = new Api2Db_Functions();


		if( get_parent_class( $params['converts'] ) == 'Api2Db_Converts' )
			$this->converts = $params['converts'];
		else
			$this->converts = new Api2Db_Converts( $this->functions );
		

		if( get_parent_class( $params['actions'] ) == 'Api2Db_Actions' )
			$this->actions = $params['actions'];
		else
			$this->actions = new  Api2Db_Actions( $this );



	}

/*	final public function __destruct(){

		$this->storage->clear( $this );
	}*/


	public function before_request($p) {
		return true;
	}

	public function after_request($p) {
		return true;
	}


	private function do_request( $p ) {

		if( !in_array( 'module', array_keys( $p->input ) ) ){

			$p->error = "bad_param_module";
			return false;

		}

		if( !in_array( 'action', array_keys( $p->input ) ) ){

			$p->error = "bad_param_action";
			return false;

		}


		if( !method_exists( $this->actions, 'action_' . $p->input['action'] ) )
		{
			$p->error = "action_not_exist";
			return false;
		}


		
		if( !in_array( $p->input['module'], array_keys( $this->storage->get_modules() ) ) ){

			$p->error = "module_not_exist";
			return false;

		}

		
		$p->module				= $this->storage->get_module( $p->input['module'] );
		$p->module['modname']	= $p->input['module'];
		$p->action 				= $p->input['action'];


		if( !in_array( $p->action, (array)$p->module['actions']) ){

			$p->error = "action_not_available";
			return false;

		}


		if( !$this->actions->{ 'action_'.$p->action }( $p ) )
			return false;


		return true;
	}




	final public  function execute( $p ) {


		if( $this->init ){

			$this->output = [];

			$p_structure = [
				'local'		=> [],
				'output'	=> ['code' => 'success'],
				'input' 	=> [
					'search' => [], 
					'autoSearch' => '', 
					'rowheads' => 0
				],
				'before_request_break' => false
			];

			$p  = (object)array_replace_recursive( $p_structure, $p );


			$this->actions->make_put_values( $p );


			if( !$this->before_request($p) )
				return $this->return_error( $p ); 
			
			if( $p->before_request_break ){

				if( !isset( $p->output['code'] ) )
					$p->output['code'] = 'success';


				if( $this->storage->is_empty_debug() && $this->storage->get_config()['debug'] )
					$p->output['debug'] = $this->storage->get_debug();	

				$this->output = $p->output;

				return $this;
			}


			$this->actions->make_put_values( $p );

			if( !$this->do_request($p) ) 	   	
				return $this->return_error( $p );
			
			if( !$this->after_request($p) ) 
				return $this->return_error( $p );
			

			if(  $this->storage->is_empty_debug() && $this->storage->get_config()['debug'] )
				$p->output['debug'] = $this->storage->get_debug();	


			$this->output = $p->output;

			

		}else{

			$this->output =  $this->initErrors;
		}



		return $this;
	}

	final public function output(){
		return $this->output;
	}

	public function output_json(){
		return json_encode( $this->output );
	}


	final public function return_error( $p ) {
		
		$ret = [ 'code'	=> 'failed' ];

		if( $this->storage->is_empty_debug() )
			$ret['debug'] = $this->storage->get_debug();

		if( isset( $p->error ) ){
			$ret['error'] = $p->error;


			if(  $this->storage->get_error( $p->error )  )
				$ret['text'] = $this->storage->get_error( $p->error );
		}


		$this->output = array_merge($p->output, $ret);

		return $this;
	
	}


	private function db_connect( $p ){

		$connectionName = ( isset( $p->module['connect'] ) ) ? $p->module['connect'] : $this->storage->get_config()['db']['defaultConnection'];
		$connection 	= $this->storage->get_config()['db']['connections'][$connectionName];

		// Создание коннекта
		if( !$this->connections[$connectionName]['connect'] ){

			try{

				$this->connections[$connectionName]['connect'] = new PDO ( 
					'mysql:host=' . $connection['server'] . ';dbname=' . $connection['database'], $connection['user'], $connection['password'],
					[
						PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
					]
				);
				

				$this->connections[$connectionName]['currentDB'] 					= $connection['database'];
				$this->currentConnectionName										= $connectionName;
				$this->currentConnection											= &$this->connections[$connectionName]['connect'];
				$this->storage->push_debug_db("create connect $connectionName");


			}

			catch( PDOException $e ){
			
				$p->error 	= 'dberror';

				$this->storage->push_debug_db( [
					'error' => ['message' => $e->getMessage(), 'code' => $e->getCode()],
					'text' 	=> "Failed connection to $connectionName"
				]);


				return fasle;
			}

		}

		// Смена коннекта
		if( $this->connections[$connectionName]['connect'] and $this->currentConnectionName != $connectionName ){
			$this->currentConnectionName = $connectionName;
			$this->currentConnection 	 = &$this->connections[$connectionName]['connect'];
		}
		

		/*		
		// Выбор базы данных
		if( $this->config['db']['connections'][$connectionName]['currentDB'] != $this->params->module['database'] && $this->params->module['database'] ){


			
			$result = db_query( $p, 'use database `' . $this->params->module['database'] . '`' );


			if($result->ret['code'] == 'success')
				$this->config['db']['connections'][$connectionName]['currentDB'] = $this->params->module['database'];
			
			else{

				$this->params->ret['code'] 	= 'failed';
				$this->params->ret['error'] = 'dberror';

				//$this->params->storage->push( "Failed change database to {$this->params->module['database']} in $connectionName", 'debug->database->' . $this->params->query_count . "->error" );
			
			}
		}
		*/
		return true;
	}


	final public function db_query($p, $sql = false, $type = 'select' ){

		if(!$this->currentConnectionName)
			$this->db_connect( $p );

		if(!$sql)
			$sql = $p->db['lastQuery'];

		$sql 	= $this->currentConnection->prepare( $sql );
		$start 	= microtime(true);

		$sql->execute();
		
		$time = round( microtime(true)-$start, 2 );

		// Так написал, потому что так $sql->errorInfo()[0][0] не дадут обратиться, 
		// как обратиться нормально?
		$errsql = $sql->errorInfo();


		$log = [
			'sql' 			=> $sql->queryString,
			'connection' 	=> $this->currentConnectionName,
			'time' 			=> $time,
			'code' 			=> $errsql[0],
			'whence'		=> $p->db['whence']
		];

		$p->db['lastResult'] = [];

		if($errsql[0] == '00000'){
			
			switch ($type) {

				case 'select':
					$p->db['lastResult'] = $sql->fetchAll( PDO::FETCH_ASSOC );
				break;
				
				case 'insert':
					$p->db['lastInsertId'] = $this->connections[$this->currentConnectionName]['connect']->lastInsertId();
				break;

			}

		
		}else{

			$p->error = 'dberror';

			$log['error'] = $errsql[2];
			
		}

		$this->storage->push_debug_db( $log );

		if( isset( $log['error'] ) )
			return false;
		else
			return true;
	}

}




