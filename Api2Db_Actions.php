<?php


class Api2Db_Actions 
{
	final public function __construct( $Api2Db )
	{

		$this->Api2Db 	= $Api2Db;
		$this->storage 	= Api2Db_Storage::Instance();
		$this->db 		= Api2Db_Db::Instance();

	}

	final public function execute( $p ){



		$this->purge_data( $p );

		if( !in_array( 'module', array_keys( $p->input ) ) ){
			
			$p->output['request'] = $p->input;
			$p->error = "bad_param_module";
			return false;

		}



		if( !in_array( 'action', array_keys( $p->input ) ) ){

			$p->output['request'] = $p->input;
			$p->error = "bad_param_action";
			return false;

		}

		
		if( !$this->storage->is_module( $p->input['module'] ) ){

			$p->output['request'] = $p->input;
			$p->error = "module_not_exist";
			return false;

		}

		
		$p->module				= $this->storage->get_module( $p->input['module'] );
		$p->module['modname']	= $p->input['module'];
		$p->action 				= $p->input['action'];

		if( !method_exists( $this, 'action_' . $p->action ) )
		{
			$p->error = "action_not_exist";
			return false;
		}


		if( !in_array( $p->action, (array)array_keys( $p->module['actions'] ) ) ){

			$p->error = "action_not_available";
			return false;

		}



		if( $this->{ 'action_' . $p->action }( $p ) ){

			if( isset( $this->Api2Db->triggers ) ){

				if( !empty( $p->module['triggers'][ $p->action ] ) ){

					$trigger = $p->module['triggers'][ $p->action ];

					if( method_exists( $this->Api2Db->triggers, $trigger ) )
						$this->Api2Db->triggers->{ $trigger }( $p );

				}

			}

			$this->make_extra_info( $p );

			if( isset( $p->module['submodules'] ) ){
				
				$this->make_submodules( $p );
			}


			

			return true;
		}else
			return false;
	}



	private function make_submodules( $p_parent ){

		foreach( $p_parent->module['submodules'] as $keymod => $submod ){
			
			if( is_array( $submod[ $p->action ] ) )
				$p_parent->output['submodules'][ $keymod ] = $this->make_submodule( $submod[ $p->action ], $p_parent );

		}

	}

	private function make_submodule( $request, $p_parent ){


		$p = (object)[ 
			'parent' => clone $p_parent,
			'output'	=> ['code' => 'success'],
			'input' 	=> [
				'search' => [], 
				'autoSearch' => '', 
				'rowheads' => 0,
				'heads' => 0,
			],

		];



		$p->local = &$p->parent->local;

		foreach ( $request as $key => $value) 
			if( is_string( $value ) )
				$p->input[ $key ] = $this->Api2Db->functions->put_values( $value, [ 'input' => $p_parent->input ] );
			else
				$p->input[ $key ] = $value;


		$p->putvalues['input'] = &$p->input;
		$p->putvalues['local'] = &$p_parent->local;


		if( !$this->execute( $p ) ){

			$p->output['code']	= 'failed';
			$p->output['error'] = $p->error;
			$p->output['request'] = $p->input;

			if(  $this->storage->get_error( $p->error )  )
				$p->output['text'] = $this->storage->get_error( $p->error );
		
		}

		return $p->output;

	}

	private function make_extra_info( $p ){


		$make = function(&$val, $key, $p){

			if( is_string( $val ) ){

				$val = $this->Api2Db->functions->put_values( $val, $p->putvalues);

				if( !empty( $key ) ){

					switch ( $key ) {
						case 'options':
							


							$result = $this->db->select( $val, 'create_options_in_extra_to_key_'.$key );

							if( !empty( $result ) )
								$val = $result;

							break;
						
						case 'action':
							$val = $this->Api2Db->functions->put_values( $val, $p->putvalues);
							break;

						default;
							break;
					}
				}
			}

		};


		$call_submodule_recursive = function( $arr, $p ) use ( &$call_submodule_recursive ) {

			if( is_array( $arr ) ){

				foreach ($arr as $key => $val) {
					

					if( isset( $val['type'] ) ){

						if( $val['type'] == 'subquery' && is_array( $val[ $p->action ] ) ){
							
							
							$arr[$key] = $this->make_submodule( $val[ $p->action ], $p );

						}

					}else{
						$arr[$key] = $call_submodule_recursive( $arr[$key], $p );
					}

				}


			}

			return $arr;

		};


		if( !empty( $p->module['extra_info'][ $p->action ] ) ){
		
			if( isset( $p->module['extra_info'][ $p->action . '_convert' ] ) )
				$convert_name = $p->module['extra_info'][ $p->action . '_convert' ];
			else
				$convert_name = '';

			if( method_exists( $this->Api2Db->converts,  $convert_name ) ){

				$result = $this->Api2Db->converts->{ $convert_name }( $p->module['extra_info'][ $p->action ], $p->output  );

				if( !empty( $result ) )
					$p->module['extra_info'][ $p->action ] = $result;
			}

			array_walk_recursive( $p->module['extra_info'][ $p->action ], $make, $p );

			$p->module['extra_info'][ $p->action ] = $call_submodule_recursive( $p->module['extra_info'][ $p->action ], $p );



			$p->output['extra'] = $p->module['extra_info'][ $p->action ];
		}

	}


	final public function action_list( $p )
	{
		
		$this->make_heads( $p );
		$this->make_filter( $p );

		$p->db['request']['query']	= 'select';

		if( !$this->is_requare( $p ) )
			return false;

		if( isset( $p->input['list'] ) ){
			$request = (array)$p->input['list'];
		}else{
			$request = [];
		}

		if( !$this->check_row( $p, $request, 'list' ) )
			return false;

		// Формируем where
		if( !$this->make_where( $p ) ) 
			return false;

		// Формируем fields
		if( !$this->make_fields( $p ) ) 
			return false;

		// Запрос общего количества из БД
		if( !$this->get_records( $p ) )
			return false;

		$p->output['records'] = $p->records;

		// Нет записей
		if(  $p->records == 0 )
			return true;



		// Формируем limit
		if( !$this->make_limit( $p ) ) 
			return false;

		// Формируем sql запрос
		if( !$this->make_sql_str( $p ) )
			return false;

		// Совершаем запрос
		$result = $this->db->select( $p->db['lastQuery'], 'action_list' );
		
		if( empty( $result ) ){
			$p->error = 'dberror';
			return false;
		}else{
			$p->db['lastResult'] = $result;
		}


		$p->output['rows'] = $this->make_rows( $p );


		return true;

	}

	final public function action_view( $p )
	{


		$p->db['request']['query']	= 'select';
		$p->db['request']['limit']  = '1';


		$this->make_heads( $p );


		if( !$this->is_requare( $p ) )
			return false;

		// Формируем where
		if( !$this->make_where( $p ) ) 
			return false;

		// Формируем fields
		if( !$this->make_fields( $p ) ) 
			return false;

		// Формируем sql запрос
		if( !$this->make_sql_str( $p ) )
			return false;

		// Совершаем запрос
		$result = $this->db->select( $p->db['lastQuery'], 'action_view' );
		
		if( empty( $result ) ){
			$p->error = 'dberror';
			return false;
		}else{
			$p->db['lastResult'] = $result;
		}


		$rows = $this->make_rows( $p );

		if( empty( $rows ) ){
			$p->error = "notfound";
			return false;
		}else{

			$this->make_heads( $p );
			$p->output['rows'] = $rows[1];
		
		}

		return true;

	}


	final public function action_add( $p ){



		if( !$p->module['actions'][ $p->action ] ){

			$p->error = "action_not_available";
			return false;

		}

		if( !isset( $p->input['defrec_type'] ) ){

			$p->error = 'param_defrec_notfound';
			return false;

		}


		if( isset( $p->module['actions']['defrec'][ $p->input['defrec_type'] ] ) ){



			$p->db['request']['query']	= 'insert';

			$p->module['actions']['add'] = $p->module['actions']['defrec'][ $p->input['defrec_type'] ];



			if( !$this->make_values( $p ) )
				return false;




			foreach( $p->module['fields'] as $field => $fieldval ) {

				if( !empty( $fieldval['convert']['add'] ) ){

					$convert_name = $fieldval['convert']['add'];

					if( !empty( $p->input['add'][$field] ) && !empty($convert_name) ){

						if( method_exists( $this->Api2Db->converts,  $convert_name ) )
							$convertval = $this->Api2Db->converts->{ $convert_name }( $p->input['add'][$field]  );

						if( isset( $convertval ) ) {
				   			$p->values[$field] = $convertval;
				   		}

					}
				}
			}
				


			if( !$this->check_row( $p, $p->values, 'add' ) )
				return false;



			if( !$this->make_set( $p ) )
				return false;

			if( !$this->make_sql_str( $p ) )
				return false;

			
			// Совершаем запрос
			$id = $this->db->insert( $p->db['lastQuery'], 'action_add' );
			
			if( empty( $id ) ){

				$p->error = 'dberror';
				return false;

			}else{

				$p->output['recordId'] = $id;
				return true;
		
			}

		}else{
			$p->error = 'defrec_'.$p->input['defrec_type'].'_not_define';
			return false;
		}

		return true;
	}

	final public function action_save( $p ){


		if( !$p->module['actions'][ $p->action ] ){

			$p->error = "action_not_available";
			return false;

		}

		if( !isset( $p->input['defrec_type'] ) ){

			$p->error = 'param_defrec_notfound';
			return false;

		}


		if( isset( $p->module['actions']['defrec'][ $p->input['defrec_type'] ] ) ){


			$p->module['actions']['save'] = $p->module['actions']['defrec'][ $p->input['defrec_type'] ];


			if( !$this->is_requare( $p ) )
				return false;

			// Формируем where
			if( !$this->make_where( $p ) ) 
				return false;

			// Запрос общего количества из БД
			if( !$this->get_records( $p ) )
				return false;



			// Нет записей
			if(  $p->records == 0 ){

				$p->error = 'notfound';
				return false;
			}


			$p->db['request']['query']	= 'update';

			if( !$this->make_values( $p ) )
				return false;
			
			foreach( $p->module['fields'] as $field => $fieldval ) {

				if( !empty( $fieldval['convert']['save'] ) ){

					$convert_name = $fieldval['convert']['save'];

					if( !empty( $p->input['save'][$field] ) && !empty($convert_name) ){

						if( method_exists( $this->Api2Db->converts,  $convert_name ) )
							$convertval = $this->Api2Db->converts->{ $convert_name }( $p->input['save'][$field]  );

						if( isset( $convertval ) ) {
				   			$p->values[$field] = $convertval;
				   		}

					}
				}
			}


			if( !$this->check_row( $p, $p->values, 'save' ) )
				return false;


			if( !$this->make_set( $p ) )
				return false;




			if( !$this->make_sql_str( $p ) )
				return false;

			
			if( !$this->db->update( $p->db['lastQuery'], 'action_save' ) ){

				$p->error = 'dberror';
				return false;

			}else
				return true;
		
		}else{
			$p->error = 'defrec_'.$p->input['defrec_type'].'_not_define';
			return false;
		}
		


		return true;
	}



	final public function action_defrec( $p ){


		if( !isset( $p->input['defrec_type'] ) ){

			$p->error = 'param_defrec_notfound';
			return false;

		}


		$p->db['whence'] = "action_defrec";

		if( $p->module['actions']['defrec'][ $p->input['defrec_type'] ] ){

			$defrec = $p->module['actions']['defrec'][ $p->input['defrec_type'] ];
	

			if( is_string( $defrec['fields'] ) and $defrec['fields'] == 'all' ){
				$view = array_keys($p->module['fields']);


				if( isset( $defrec['exclude'] ) ){

					foreach ($view as $key => $del) 
						if( in_array($del, (array)$defrec['exclude'] ) )	
							unset($view[$key]);
					
				}
			}else{
				$view = $defrec['fields'];
			}	

			$p->make_row	= $view;
			$rows 			= $this->make_row( $p );
			
			unset($p->make_row);		
			
		
		}else{
		
			$p->error = 'defrec_bad_format';
			return false;
		
		}



		$p->output['rows'] = $rows;
		return true;

	} // defrec


	private function action_delete( $p ){


		if( !$p->module['actions'][ $p->action ] ){

			$p->error = "action_not_available";
			return false;

		}

		if( !$this->is_requare( $p ) )
			return false;

		// Формируем where
		if( !$this->make_where( $p ) ) 
			return false;


		// Запрос общего количества из БД
		if( !$this->get_records( $p ) )
			return false;

		// Нет записей
		if(  $p->records == 0 ){

			$p->error = 'notfound';
			return false;
		}

		$p->db['whence'] = "action_delete";
		$p->db['request']['query']	= 'delete';

		if( !$this->make_sql_str( $p ) )
			return false;

			
		if( !$this->db->delete( $p->db['lastQuery'], 'action_delete' ) ){

			$p->error = 'dberror';
			return false;

		}else
			return true;

		return true;

	}

	private function check_row( $p, $values, $type ){

		$errors = [];


		// Проверка полей
		foreach( $p->module['fields'] as $field => $fieldval ) {


			if( !isset( $values[$field] ) )
				$value = '';
			else
				$value = $values[$field];

			$arg = [
				'field'  => $field, 
				'value'  => $value, 
				'edit' 	 => ( empty( $p->module['fields'][$field]['edit'] ) ) ? 'no' : 'yes', 
				'values' => (array)$values 
			];


			if( isset( $values[$field] ) )
				$arg['isset'] = true;
			else
				$arg['isset'] = false;

			if( isset($p->module['fields'][$field]['check'][$type]) )
					$arg['checks'] = $p->module['fields'][$field]['check'][$type];




			if( isset( $p->module['fields'][$field]['check']['*'] ) ){

				if( !empty( $arg['checks'] ) )
					$arg['checks'] = array_replace_recursive( $p->module['fields'][$field]['check']['*'], $arg['checks'] );
				else
					$arg['checks'] = $p->module['fields'][$field]['check']['*'];

			}



			$fld = clone $p;


			
			if( !$this->check_field_by_value( $fld, $arg ) )
				$errors[$field] = $fld->errors;

			unset($fld->ret);
			unset($fld->errors);


		}


		if( !empty( $errors ) ) {

			$p->output['errors'] = $errors;
			return false;
		
		}else{
			return true;
		}

	}


	// Проверка на значений полей
	private function check_field_by_value( $p, $arg ){

		$err = [];


		// Проверка пустая
		if( empty( $arg['checks'] ) ) {
			$arg['checks'] = [];	
		}



		if( !isset( $arg['field'] ) )
			$err[] = 'no field';

		if( !is_array( $arg['values'] ) )
			$err[] = 'bad param values';

		if( empty( $err ) ){


			foreach ( $arg['checks'] as $type_check => $type_check_el ){
				
				if( in_array( $type_check, ['single','sql'] ) ){



					foreach ( $type_check_el as $key => $check ){

						if( $type_check == 'sql' ){
						
							$check_key 	= $key;


							$arg['sql'] = $this->Api2Db->functions->put_values( $check, array_merge( $p->putvalues, [ 'this' => [ 'value' => $arg['value'] ] ] ) );


						
						}else{
							
							$check_key = $check;
						
						}

						if( method_exists( $this->Api2Db->checks,  $type_check . "_" . $check_key ) ){

							$result = $this->Api2Db->checks->{ $type_check . "_" . $check_key }($arg);

							if( !empty( $result['error'] ) ){
							
								$result['check_type']  = $type_check . "_" . $check_key;
								$err[] = $result;
							
							}

						}else{

							$err[] = [ 'error' => 'bad_check_key', 'val' => $type_check . "_" . $check_key  ];
						
						}

					}//endforeach;
				}

			}

			if( empty( $err ) ){
				return true;
			}else{

				$p->errors = $err;
				return false;
			}


		}else{
			$p->errors = [ 'error' => 'bad_in_param','val' => $err ];
			return false;
		}
	
		
		return true;
	}

	private function purge_data( $p ){
		
		$p->db = [];
		unset($p->values, $p->records);
	
	}


	private function get_records( $p ){
		

		$request = [
			'query' 	=> 'select',
			'fields'	=> [ 'count(*) as count' ],
			'limit' 	=> '1000',
			'order' 	=> '',
			'where' 	=> $p->db['request']['where'],
		];


		if( !$this->make_sql_str( $p, $request ) ) 
			return false;


		// Совершаем запрос
		$result = $this->db->select( $p->db['lastQuery'], 'get_records' );
		
		if( empty( $result ) ){
			$p->error = 'dberror';
			return false;
		}


		$count 		= 0;
		$records 	= 0;

		if( !empty( $result ) ){
		
			foreach( $result as $rows_c ) {
				$records += $rows_c['count'];
				$count++;
			}

		}


		if( !empty( $p->module['groupby'] ) )
			$p->records = $count;
		else
			$p->records = $records;
	


		return true;
	}


	final public function make_values( $p ){


		$p->values = [];

		$action  = $p->module['actions'][ $p->action ]; 

		$exclude = ( isset( $action['exclude'] ) ) ? $action['exclude'] : [] ;
		$fields  = $action['fields'];

		if( $fields == 'all' )
			$fields = array_keys( $p->module['fields'] );


		foreach( $fields as $field ) {

			$fieldval = $p->module['fields'][$field];

			if( !isset( $fieldval['edit'] ) )
					continue;



			if( !empty( $fieldval ) && !in_array( $field, $exclude ) ){



				if( !empty( $fieldval['convert'][ $p->action ] ) ){

					$convert_name = $fieldval['convert'][ $p->action ];

					if( !empty( $p->input[$field][ $p->action ] ) && !empty($convert_name) ){

						if( method_exists( $this->Api2Db->converts,  $convert_name ) )
							$convertval = $this->Api2Db->converts->{ 'add_' . $convert_name }( $p->input[$field][ $p->action ]  );

						if( isset( $convertval ) ) 
				   			$p->values[$field] = $convertval;
				   		

					}
				
				}else{

					if( isset( $p->input[ $p->action ][ $field ] ) )
						$p->values[$field] = $p->input[ $p->action ][ $field ];
				//	else
				//		$p->values[$field] = '';
				}

			}
		}




		return true;
	}


	final public function make_set( $p ){

		if( !empty( $p->values ) ){

			foreach( $p->values as $key => $val ){

				$field = $p->module['fields'][$key];

				if( !empty( $field ) ){

					if( isset( $field['key'] ) ){

						$key = $field['key'];
						$ex_key = explode(".", $key);

						if( count( $ex_key ) == 2 )
							$key = $ex_key[1];
						
					}

					$fields_sql[] = $key . '="' . $this->Api2Db->functions->sql_escape( $val )  . '"';

				}
			}
			
			if( isset( $p->module['actions'][ $p->action ]['set'] ) ){
				$def_set = $p->module['actions'][ $p->action ]['set'];


				if( is_array( $def_set ) ){
					$fields_sql = array_merge($fields_sql,$def_set);
				}
			}

			$p->db['request']['set'] = $fields_sql;

		}else{

			$p->error = 'nothing_' . $p->action;
			return false;

		}

		return true;
	}


	private function is_requare( $p ){

		$require = [];

		if( isset( $p->module['actions'][ $p->action ]['require'] ) )
			$require = $p->module['actions'][ $p->action ]['require'];
		
		else if( isset( $p->module['require'] ) )
			$require = $p->module['require'];

		if( !empty( $require ) ){

			$requires = [];

			foreach ( (array)$require as $require ) {
				
				if( !in_array( $require, array_keys( $p->input ) ) )
					$requires[] = $require;
			
			}

			if( !empty( $requires ) ){

				$p->output['requires'] = $requires;
				$p->error = 'require';
				return false;
			}
		
		}

		return true;
	}

	public function extend_make_where( $p ){
		return true;
	}

	private function make_where( $p ){

		$p->db['request']['where'] = ['1' => ' 1 '];

		if( !$this->extend_make_where( $p ) )
			return false;

		$where = [];


		// Для модуля жестко установлено where
		if( isset( $p->module['where']['every'] ) ){
			$where['every'] = "and ".$this->Api2Db->functions->put_values( $p->module['where']['every'], $p->putvalues );

		}



		if( isset( $p->module['where'][ $p->action ] ) ){
			$where[ $p->action ] = "and ".$p->module['where'][ $p->action ];
		}


		if( isset( $p->input['filters'] ) ){

			if( isset( $p->module['actions'][ $p->action ]['filter'] ) )
				$filters = (array)$p->module['actions'][ $p->action ]['filter'];
			else
				$filters = [];

			$filter = [];

			foreach ( $filters as $key ) {
				


				if( !isset( $p->module['fields'][ $key ] ) )
					continue;


				if( !isset( $p->input['filters'][ $key ] ) )
					continue;



				$sql 	= '';
				$filter = $p->module['fields'][ $key ]['filter'];



				if( isset( $filter['type'] ) ){
					
					if( $filter['type'] == 'like' )
						$sql = ':this->key like "%:this->value%"';

					if( $filter['type'] == 'key' )
						$sql = ':this->key=":this->value"';
				}

				if( !empty( $filter['sql'] ) ){
					$sql = $filter['sql'];
				}

				if( empty( $sql ) )
					continue;

				if( isset( $p->module['fields'][ $key ]['key'] ) ){
					$keyfield = $p->module['fields'][ $key ]['key'];
				}else{
					$keyfield = $key;
				}

				$this_values = [
					'key' 	=> $keyfield,
					'value' => $p->input['filters'][ $key ]
				];


				$sql = $this->Api2Db->functions->put_values( $sql , array_merge( $p->putvalues, ['this' => $this_values] ) );


				$filter_out[] = $sql;
			}
	
			if( !empty( $filter_out ) ){
				$where['filter'] = 'and ( '.implode( ' and ', $filter_out ).' )';
			}

		}

		if( !empty( $p->input['search'] ) ){


			if( isset( $p->module['actions'][ $p->action ]['search'] ) )
				$search = (array)$p->module['actions'][ $p->action ]['search'];
			else
				$search = [];



			foreach ( $search as $key ) {
				
				if( !isset( $p->module['fields'][ $key ] ) )
					continue;


				$sql = ':this->key like "%:this->value%"';


				if( isset( $p->module['fields'][ $key ]['key'] ) ){
					$keyfield = $p->module['fields'][ $key ]['key'];
				}else{
					$keyfield = $key;
				}

				$this_values = [
					'key' 	=> $keyfield,
					'value' => $p->input['search']
				];


				$sql = $this->Api2Db->functions->put_values( $sql , array_merge( $p->putvalues, ['this' => $this_values] ) );

				
				$search_out[] = $sql;
				
			}

			if( !empty( $search_out ) ){
				$where['filter'] = 'and ( '.implode( ' or ', $search_out ).' )';
			}
	

		}
	
		$p->db['request']['where'] = array_merge($p->db['request']['where'], $where);	

		return true;

	}

	private function make_fields( $p ){

		$fields = $p->module['actions'][ $p->action ]['fields'];

		if( $fields == 'all' )
			$fields = array_keys( $p->module['fields'] );


		if( isset( $p->module['actions'][ $p->action ]['exclude'] ) )
			$exclude = $p->module['actions'][ $p->action ]['exclude'];
		else
			$exclude = [];


		if( is_array( $fields ) ){
			
			foreach( $fields as $keyField ) {

				if( in_array( $keyField, $exclude ) )
					continue;

				if( !isset( $p->module['fields'][$keyField] ) )
					continue;

				$field = $p->module['fields'][$keyField];

				if( isset( $field['type'] ) )
					if( $field['type'] == 'submodule' )
						continue;

				if( isset($field['field']) )
					$outfields[$keyField] = $field['field'] . ' as ' . $keyField;

				elseif( isset($field['key']) )
					$outfields[$keyField] = $field['key'] . ' as ' . $keyField;
				
				else
					$outfields[$keyField] = $keyField;

			}
		
		}else{


			$p->error = 'not_defined_fields';
			return false;
		}




		$p->db['request']['fields'] = $outfields;


		return true;
	}


	private function make_limit( $p ){

		//Установка глобального ограничения либо дефолтного
		$perpage = ( isset( $this->config['perpage'] ) ) ? $this->config['perpage'] : 10;
		$maxperpage = ( isset( $this->config['maxperpage'] ) ) ? $this->config['maxperpage'] : 20;

		// Установка ограничения для модуля
		if( isset( $p->module['limit'] ) ) 
			$perpage = $p->module['limit'];

	   	if( isset( $p->module['maxlimit'] ) ) 
	   		$maxperpage = $p->module['maxlimit'];

		// Постраничное разбиение записей,
		// формируем максимальное количество записей
		if( isset( $p->input['perpage'] ) &&  $this->Api2Db->functions->is_digits( $p->input['perpage'] ) && $p->input['perpage'] <= $maxperpage )
			$perpage = $p->input['perpage'];


		if( isset( $p->input['page'] ) &&  $p->input['page'] < 1 ) {
		
			$this->storage->push_debug_by_key( 'make_limit', [
				'error' => 'badpage',
			]);

			return false;
		}

		// Постраничный вывод,
		// формируем номер страницы для селекта
		
		$limit 	= [0, $perpage];
		$page 	= 1;

		if( isset( $p->input['page'] ) && $this->Api2Db->functions->is_digits( $p->input['page'] ) && $p->input['page'] >= 1 ) {

			/*
				TODO - тут с условиями ахинея. Переписать.
			*/

			$start = ceil( ( $p->input['page'] - 1 ) * $perpage );

			if( !isset( $p->output['records'] ) ){ 

				// Если передан номер страницы и мы не занем количество записей
				$limit 	= [ $start, $perpage ];
				$page 	= $p->input['page'];

			}elseif( ( $p->input['page'] - 1 ) * $perpage >= $p->output['records'] ) {
			
				$this->storage->push_debug_by_key( 'make_limit', [
					'error' => 'badpage',
				]);

				return false;
			
			}else{
		
				$limit 	= [ $start, $perpage ];
				$page 	= $p->input['page'];		
		
			}
		
		}

		// Ставим в вывод
		$p->output['perpage']		= $perpage;
		$p->output['page'] 	 		= $page;
		$p->db['request']['limit'] 	= $limit;

		return true;
	}

	private function make_rows( $p ){

		if( !isset( $p->db['lastResult'] ) ) 
			return [];
		
		$rows 	 = [];
		$row_key = 1;
		


		foreach( $p->db['lastResult'] as $row ) {

			$p->putvalues['fields'] = $row;			
			$p->make_row 			= $row;
			$rows[$row_key++] 		= $this->make_row( $p );
			
			unset($p->make_row);		
		
		}

		return $rows;
	}


	private function make_row( $p ){

		$row = [];

		if( !isset( $p->make_row  ) )
			return $row;

		foreach( $p->make_row as $key => $val) {

			// Если надо заполнить по пустым полям, а не из базы
			if( is_integer( $key ) ){
				$key = $val;
				$val = '';
			}


			if( !empty( $p->input[ $p->action ][$key] ) )
				$val = $p->input[ $p->action ][$key];



			if( isset( $p->module['fields'][$key]['type'] ) )
			if( $p->module['fields'][$key]['type'] != 'search' ) {

				$row[$key]['key'] = $key;
				
				// квотируем левое
				$quot = [
					'"' => '&quot;',
					"'" => '&apos;'
				];	

				$row[$key]['val'] = strtr( $val, $quot );
				
				$convert_name_by_action = '';
				$convert_name_by_all 	= '';
				$convert 				= '';

				// Конвертация вывода
				if( !empty( $p->module['fields'][$key]['convert'][$p->action] ) )
					$convert_name_by_action = $p->module['fields'][$key]['convert'][$p->action];
				
				if( !empty( $p->module['fields'][$key]['convert']['*'] ) )
					$convert_name_by_all = $p->module['fields'][$key]['convert']['*'];



				if( !empty( $convert_name_by_action ) )	{

					if( method_exists( $this->Api2Db->converts,  $convert_name_by_action ) )
						$convert = $this->Api2Db->converts->{ $convert_name_by_action }( $row[$key], $p->make_row  );


				} elseif( !empty( $convert_name_by_all )  )	{


					if( method_exists( $this->Api2Db->converts,  $convert_name_by_all ) )
						$convert = $this->Api2Db->converts->{ $convert_name_by_all }( $row[$key], $p->make_row  );		
						
				}


				if( !empty( $convert ) )
					$row[$key] = $convert;
			
				$extend_row	= (array) $this->make_extend_row( $key, $p, $val );
				$row[$key]	= array_merge( (array) $row[$key], $extend_row );



				if(  $p->input['rowheads']  ) {
				
					$heads 		= (array) $this->make_head_row( $key, $p );
					$row[$key]	= array_merge( (array) $row[$key], $heads );

				}


			}
		}

		return $row;
	}

	private function make_head_row( $key, $p ){

		$row = [];
		
		if( isset( $p->module['fields'][$key] ) ) {

			if( isset( $p->module['fields'][$key]['type'] ) )
			if( $p->module['fields'][$key]['type'] != 'search' ) {

				$keyval 		= $p->module['fields'][$key];
				$row['key'] 	= $key;
				$row['name']	= $key;
				$row['edit'] 	= 'no';
				$row['type']	= ( isset( $keyval['type'] ) ) ? $keyval['type'] : 'text';

				if( !isset( $keyval['edit'] ) )
					$keyval['edit'] = 'no';

				if( isset( $keyval['name'] ) )
					$row['name'] = $keyval['name'];

				elseif(  $this->storage->get_name( $key ) ) 
					$row['name'] = $this->storage->get_name( $key );
				
				if( isset( $keyval['check']['save'] ) or $keyval['edit'] == 'yes' )
					$row['edit'] = 'yes';



				
				// дополнительные поля, отображаемые в heads
				if( isset( $keyval['extra']['heads'] ) ){

					foreach( $keyval['extra']['heads'] as $extra => $extraval ){

							$row[$extra] = $extraval;

					}
				}

			}

		}
		
		return $row;

	}

	private function make_extend_row( $key, $p, $val = false ){


		$row = [];

		
		if( isset( $p->module['fields'][$key] ) ) {

			if( $p->module['fields'][$key]['type'] != 'search' ) {

				$keyval = $p->module['fields'][$key];
				
				if( isset( $keyval['type'] ) ){

					// Его может не быть, если не указан rowheads

					$row['type'] = $keyval['type'];
					
					switch( $keyval['type'] ) {
					
						case 'select':
					
							$row['options'] = $this->make_key_options( $key, $p, $val );
					
						break;
					
					}
				}else{
					$row['type'] = 'text';
				} 
				

			}

		}
		
		return $row;

	}

	private function make_key_options( $key, $p, $rowvalue = false ){
		

		$options = [];

		if( isset( $p->module['fields'][$key]['options'] )  ) {
		
		
			if( is_array( $p->module['fields'][$key]['options'] ) ){
				

				/*
				// Закоментил потому что пока не понятно зачем стандартизировать options
				// В sql селекте не получится красиво стандартизировать все это
				foreach( $p->module['fields'][$key]['options'] as $option => $opvalue ){
				
					$options[$option] = array(
						'id'	=> $option,
						'name'	=> $opvalue['name'],
						'extra' => $opvalue['extra']
					);
				
				} */

				$options = $p->module['fields'][$key]['options'];

			}else if( is_string( $p->module['fields'][$key]['options'] ) ){

				$this_values = [
					'value' => $rowvalue
				];
	


				$sql = $this->Api2Db->functions->put_values( $p->module['fields'][$key]['options'], array_merge( $p->putvalues, ['this' => $this_values] ));


				$result = $this->db->select( $sql, 'create_options_to_key_'.$key );

				if( !empty( $result ) )
					$options = $result;


			}

		}

		return $options;
	}

	private function make_heads( $p ){

		$heads = [];
		$exclude = [];
		$fields = [];

		if( isset( $p->module['actions'][ $p->action ]['fields'] ) )
			$fields = $p->module['actions'][ $p->action ]['fields'];


		if( $fields == 'all' ){
			$fields = array_keys( $p->module['fields'] );

			if( isset( $p->module['actions'][ $p->action ]['exclude'] ) ){

				$exclude = $p->module['actions'][ $p->action ]['exclude'];
			}
		}


		if( !empty( $fields ) )
			foreach($fields  as $key ){

				if( !in_array($key, $exclude ) )
					$heads[$key] = $this->make_head_row( $key, $p );		
			}
		
		if( $p->input['heads']  )
			$p->output['heads'] = $heads;
	}

	private function make_filter( $p ){

		$search = [];

		if( isset( $p->module['actions'][ $p->action ]['filter'] ) )
			$fields = $p->module['actions'][ $p->action ]['filter'];
		else
			$fields = [];

		if( !empty( $fields ) ) {

			foreach( $fields as $key ) {

				if( !isset( $p->module['fields'][ $key ] ) )
					continue;

				$keyval = $p->module['fields'][ $key ];
			
				if( isset( $p->module['fields'][$key]['sql'] ) || isset( $p->module['fields'][$key]['type'] ) ) { 
					
					$search[$key]['key'] 	= $key;
					$search[$key]['name'] 	= $key;

					if( isset( $p->input['filters'] ) )
					if( !empty( $p->input['filters'][$key] ) )
						$search[$key]['val'] 	= $p->input['filters'][$key];
				
					if( isset( $keyval['name'] ) ) 
						$search[$key]['name'] = $keyval['name'];
					
					
					if( isset($keyval['type'])){

						// Установлен тип поля
						$search[$key]['type'] = $keyval['type'];
						
						switch($keyval['type']) {
						
							case 'select':
						
								$search[$key]['options'] = $this->make_key_options( $key, $p );
						
							break;
						
						} 
					
					}
					
					if( isset( $keyval['extra']['search']) ){
						
						// дополнительные поля, отображаемые в heads
						foreach( $keyval['extra']['search'] as $extra => $extraval){
							
							$search[$key][$extra] = $extraval;

						}
						 
					} 

				} 

			} //  foreach($params->module['fields']

		} // if( isset($params->module['fields']
		
		if( isset( $p->input['filter'] ) )
			$p->output['filters'] = $search;
	}

	private function make_sql_str( $p, $request = [] ){

		if( empty( $request ) )
			$request = $p->db['request'];


		if( !in_array( $request['query'], ['select', 'update', 'insert', 'delete'] ) ){

			$this->storage->push_debug_by_key( 'make_sql_str', [
				'error' 	=> 'bad_query',
				'query' 	=> $request['query'],
			]);
		
		}


		$request['table']  		= $p->module['table'];
		$request['join'] 		= $p->module['join'];
		$request['groupby']  	= $p->module['groupby'];
		$request['orderby']  	= $p->module['orderby'];


		// Структура запроса
		if( $request['query'] == 'update' ) {
			
			$structure	 = [
				'table'		=> '',
				'set'		=> 'set',
				'where'		=> ['prefix' => 'where', 'implode' => ' '],

			];

			$requred = [ 'set', 'table' ];
		
		}elseif( $request['query'] == 'insert' ) {
			
			$structure	 = [
				'table'		=> 'into',
				'set'		=> 'set',

			];

			$requred = [ 'set', 'table' ];
		
		
		}elseif( $request['query'] == 'delete' ) {
			
			$structure	 = [
				'table'		=> 'from',
				'where'		=> ['prefix' => 'where', 'implode' => ' '],

			];

			$requred = [ 'set', 'table' ];
		
		}else{
			
			$structure	 = [
				'fields'	=> '',
				'table'		=> 'from',
				'join'		=> '',
				'where'		=> ['prefix' => 'where', 'implode' => ' '],
				'groupby'	=> 'group by',
				'orderby'	=> 'order by',
				'limit'		=> 'limit'
			];
			
			$requred = [ 'fields', 'table' ];
		
		}



		$toSql = '';

		foreach( $structure as $sqlElem => $elemParam ){

			if( is_string( $elemParam ) ){
				$implode = ', ';
				$prefix = $elemParam;
			}else{
				$prefix = $elemParam['prefix'];
				$implode = $elemParam['implode'];
			}


			$value = '';


			if( in_array( $sqlElem, $requred ) and !isset( $request[ $sqlElem ] ) ){

				$this->storage->push_debug_by_key( 'make_sql_str', [
					'error' 	=> 'requred_sqlElem',
					'element' 	=> $sqlElem,
				]);

				return false;

			}

			if( $sqlElem == 'orderby' && is_array( $request[ $sqlElem ] ) ){

				$value = implode( $implode, $request[ $sqlElem ]['by']);

				if( !empty( $p->module['fields'][ $value ]['key'] ) )
					$value = $p->module['fields'][ $value ]['key'];

				if( isset( $request[ $sqlElem ]['order'] ) )
					$order = $request[ $sqlElem ]['order'];
				else
					$order = 'ASC';

				if( $value )
					$toSql .= " $prefix " . $value .' '. $order;

				continue;

			}

			if( is_array( $request[ $sqlElem ] ) )
				$value = implode( $implode, $request[ $sqlElem ] ); 

			elseif( is_int( $request[ $sqlElem ] ) or is_string( $request[ $sqlElem ] ) )
				$value = $request[ $sqlElem ];

			if( $value )
				$toSql .= " $prefix " . $value;

			
		}


		$p->db['lastQuery'] = $this->Api2Db->functions->put_values( $request['query'] . " " . $toSql, $p->putvalues );


		return true;

	}

	// Определение правильного ключа для запроса к бд
	private function set_key( $field, $key ){

		if( isset( $field['key'] ) )
			$key = $field['key'];

		return $key;
	}


}


