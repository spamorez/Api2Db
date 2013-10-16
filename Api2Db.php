<?php

require_once( dirname(__FILE__) . '/Api2Db_Functions.php');
require_once( dirname(__FILE__) . '/Api2Db_Storage.php');
require_once( dirname(__FILE__) . '/Api2Db_Converts.php');
require_once( dirname(__FILE__) . '/Api2Db_Checks.php');
require_once( dirname(__FILE__) . '/Api2Db_Actions.php');
require_once( dirname(__FILE__) . '/Api2Db_Db.php');


class Api2Db {

	public  $initErrors = []; 		// Ошибки инициализации
	public  $storage 	= [];		// Хранилище
	private $output 	= [];		// Вывод данных
	private $init;					// прошла ли инициализация
	public  $db;



	function __construct( $params ){


		$def_params = [
			'actions' 	=> '',
			'converts' 	=> '',
			'functions' => '',
			'checks'	=> ''
		];

        $this->Api2DbPath = dirname(__FILE__) . '/'; 

		$this->storage 	= Api2Db_Storage::Instance();

		$params 		= array_replace_recursive( $def_params, $params );
		$this->init 	= true;
		
		$this->storage->clear( $this );


		if( isset( $params['convert_fields'] ) )
			$this->convert_fields = $params['convert_fields'];

		$this->storage->set_config( $params['config'] );

		$this->storage->add_modules( $params['modules'] );
		$this->storage->add_names( $params['names'] );
		$this->storage->add_errors( $params['errors'] );
		
		$this->db = Api2Db_Db::Instance();
		$this->db->clear( $this );
		$this->db->connect();

		if( get_parent_class( $params['functions'] ) == 'Api2Db_Functions' )
			$this->functions = $params['functions'];
		else
			$this->functions = new Api2Db_Functions();


		if( get_parent_class( $params['converts'] ) == 'Api2Db_Converts' )
			$this->converts = $params['converts'];
		else
			$this->converts = new Api2Db_Converts( $this->functions );


		if( get_parent_class( $params['checks'] ) == 'Api2Db_Checks' )
			$this->checks = $params['checks'];
		else
			$this->checks = new Api2Db_Converts( $this->functions );


		if( get_parent_class( $params['actions'] ) == 'Api2Db_Actions' )
			$this->actions = $params['actions'];
		else
			$this->actions = new  Api2Db_Actions( $this );


		if( is_object( $params['triggers'] ) )
			$this->triggers = $params['triggers'];

	}


	public function before_request($p) {
		return true;
	}

	public function after_request($p) {
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
					'rowheads' => 0,
					'heads' => 0,
				],
				'before_request_break' => false
			];

			$p  = (object)array_replace_recursive( $p_structure, $p );

			$p->putvalues['input'] = &$p->input;
			$p->putvalues['local'] = &$p->local;



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



			if( !$this->actions->execute( $p ) ) 	   	
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


    final public function log_to_file( $filename, $content ){

        if( !$this->storage->get_config()['logs'] )
            return false;

        $logpath = $this->Api2DbPath . $this->storage->get_config()['logpath'];

        if( !file_exists( $logpath ) )
            mkdir( $logpath, 0777 );

        file_put_contents( $logpath . '/' . $filename, $content, FILE_APPEND );

    }

}
