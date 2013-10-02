<?php

class Api2Db_Storage
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

	private $names    = [];
	private $errors   = [];
	private $debug    = [];
	private $modules  = [];
	private $config   = [];

	private function __construct(){}

	public function __destruct(){}

	public function __clone(){
		trigger_error('Cloning instances of this class is forbidden.', E_USER_ERROR);
	}

	public function __wakeup(){
		trigger_error('Unserializing instances of this class is forbidden.', E_USER_ERROR);
	}

	/* Names */

	final public function add_names( $names ){
		
		if( !empty( $names ) )
			$this->names = array_merge( $this->names, $names );
	}

	final public function get_names(){
		return $this->names;
	}

	final public function get_name( $name ){

		if( !empty( $name ) && !empty( $this->names[ $name ] ) )
			return $this->names[ $name ];
		else
			return false;

	}

	/* Errors */

	final public function add_errors( $errors ){
		
		if( !empty( $errors ) )
			$this->errors = array_merge( $this->errors, $errors );
	}

	final public function get_errors(){
		return $this->errors;
	}


	final public function get_error( $errorid ){
		
		if( !empty( $this->errors[ $this->get_config()['lang'] ][ $errorid ] ) )
			return $this->errors[ $this->get_config()['lang'] ][ $errorid ];
	
	}

	/* Modules */

	final public function add_modules( $modules ){
		
		if( empty( $this->modules ) && !empty( $modules ) ){
			
			$this->modules = $modules;
			$this->extend_modules();
		
		} else
			return false;
	
	}

	final public function get_module( $name_mod ){

		if( !empty( $this->modules[ $name_mod ] ) ){

			$module_structure = [
				'join' 		=> '',
				'groupby'	=> '',
				'orderby' 	=> ''
			];
			
			return array_replace_recursive( $module_structure, $this->modules[ $name_mod ] );

		}else
			return false;

	}


	final public function is_module( $name_mod ){
		
		if( isset( $this->modules[ $name_mod ] ) )
			return true;
		else
			return false;
		
	}

	final public function get_modules(){
		return $this->modules;
	}

	private function extend_modules(){

		if( !empty( $this->modules ) )
			foreach( $this->modules as $modKey => $modValue ) {

				if( isset( $modValue['extend'] ) ){

					if( isset( $this->modules[ $modValue['extend'] ] ) ){

						$this->modules[$modKey] = array_replace_recursive( $this->modules[ $modValue['extend'] ], $this->modules[$modKey] );

					}

				}

			}

	}



	private function check_modules(){
		return true;
	}


	/* Config */

	final public function set_config( $config ){

		if( empty( $this->config ) && !empty( $config ) ){

			$def_config = [
				'cachepath' 	=> '../cache',
				'logpath'		=> '../logs',
				'log' 			=> true,
				'debug'			=> false,
				'lang' 			=> 'ru',
				'perpage'		=> 10,
				'maxperpage'	=> 20
			];

			$this->config = array_replace_recursive( $def_config, $config );
		}else
			return false;

	}

	final public function get_config(){
		return $this->config;
	}


	/* Debug */

	final public function push_debug_db( $log ){
		$this->debug['db'][] = $log;
	}


	final public function push_debug_mem( $log ){
		$this->debug['mem'][] = $log;
	}

	final public function push_debug_by_key( $key, $log ){

		if( empty( $this->debug[$key] ) )
			$this->debug[$key] = $log;

	}

	final public function get_debug(){
		return $this->debug;
	}


	final public function get_debug_db(){
		return $this->debug['db'];
	}

	final public function get_debug_mem(){
		return $this->debug['mem'];
	}

	final public function is_empty_debug(){
		if( !empty( $this->debug ) )
			return true;
		else
			return false;
	}

	final public function clear( $class ){

		if( get_parent_class( $class ) == 'Api2Db' ){

			$this->names    = [];
			$this->errors   = [];
			$this->debug    = [];
			$this->modules  = [];
			$this->config   = [];

			
		}

	}

}
