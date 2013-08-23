<?php


class Api2Db_Actions 
{
	final public function __construct( $Api2Db )
	{

		$this->Api2Db 	= $Api2Db;
		$this->storage 	= Api2Db_Storage::Instance();
		$this->db 	= Api2Db_Db::Instance();

	}



	final public function action_list( $p )
	{
		
		$this->purge_data( $p );
		$this->make_heads( $p );
		$this->make_filter( $p );

		$p->db['request']['query']	= 'select';

		if( !$this->is_requare( $p ) )
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

		// Нет записей
		if(  $p->output['records'] == 0 )
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
					
		$this->purge_data( $p );

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

	final public function action_defrec( $p ){

		$this->purge_data( $p );

		$p->db['whence'] = "action_defrec";

		if( is_array( $p->module['defrec'] ) ){
			
			foreach ($p->module['defrec'] as $keyview => $defrec) {

				if( is_array( $defrec ) ){
			

					if( is_string( $defrec['fields'] ) and $defrec['fields'] == 'all' ){
						$view = array_keys($p->module['fields']);

						if( is_array( $defrec['exclude'] ) ){

							foreach ($view as $key => $del) 
								if( in_array($del, $defrec['exclude'] ) )	
									unset($view[$key]);
							
						}
					}

					$p->make_row	= $view;
					$rows 			= $this->make_row( $p );
					
					unset($p->make_row);		
					
				
				}else{
				
					$p->error = 'view_bad_format';
					return false;
				
				}
			
			}

		}else{

			$p->error = 'view_bad_format';
			return false;

		}


		$p->output['rows'] = $rows;
		return true;

	} // defrec


	private function purge_data( $p ){

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
			$p->output['records'] = $count;
		else
			$p->output['records'] = $records;
	


		return true;
	}

	final public function make_put_values( $p ){

		$putvalues = [];

		$putvalues['input'] = $p->input;

		$values = $p->input;

		if( isset( $p->values ) )
			$values = $p->values;

		// put fields 
		if( isset( $p->module ) )
			if( is_array( $p->module['fields'] ) )
				foreach( $p->module['fields'] as $keyField => $field ){


					if( array_key_exists( $keyField, $values) )
						$putvalues['input'][$keyField] = [
							'key' 	=> $this->set_key( $field, $keyField ),
							'value'	=> $values[$keyField]
						];


					if( isset( $values['search'] ) )
						if( array_key_exists( $keyField, (array)$values['search'] ) && isset( $field['search'] ) )
							$putvalues['input']['search'][$keyField] = [
								'key' 	=> $this->set_key( $field, $keyField ),
								'value'	=> $values['search'][$keyField]
							];
				}

		if( is_array( $p->local ) )
			$putvalues['local'] = $p->local;


		$p->putvalues = $putvalues;



	}


	public function extend_make_where( $p ){
		return true;
	}

	private function is_requare( $p ){

		if( !empty($p->module['require']) ){

			$requires = [];

			foreach ( (array)$p->module['require'] as $require ) {
				
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

	private function make_where( $p ){

		$p->db['request']['where'] = ['1' => ' 1 '];

		if( !$this->extend_make_where( $p ) )
			return false;


		$this->make_put_values( $p );

		$search = $p->input['search'];

		// Для модуля жестко установлено where
		if( isset( $p->module['where']['every'] ) ){
			$where['every'] = "and ".$this->Api2Db->functions->put_values( $p->module['where']['every'], $p->putvalues );

		}


	
		$delimetr = 'and';



		// проверяем переданные параметры
		if( isset( $p->module['fields'] ) && is_array( $p->module['fields'] )/* && ( !empty( $p->input['search'] ) || !empty( $p->input['autoSearch'] ) )*/ ) {

			$i = 0;

			foreach( $p->module['fields'] as $keyField => $field ){

				$extraWhere = '';
				$key 		= $this->set_key( $field, $keyField );


				if( !empty( $p->input['search']['autoSearch'] ) ){
					$val 		= $p->input['search']['autoSearch'];
					$keyField	= 'autoSearch';
				}

				elseif( isset( $p->input['search'][$keyField] ) )
					$val = $p->input['search'][$keyField];

				

				if( isset( $field['search'] ) ){


					// иначе потом ругаться будет
					if( !isset( $field['search']['require'] ) ) 
						$field['search']['require'] = 'no';

					// разрешен поиск по этому полю

					// Передан параметр в запросе
					if( isset($search[$keyField]) && $search[$keyField]!='') {




						$this_values = [
							'key' 	=> $key,
							'value' => $val
						];


						$p->putvalues['this'] = $this_values;
							
						if( isset($field['search']['type']) ) {

							
							// Тут мне надо тщательно подумать как хранить эти данные
							switch( $field['search']['type'] ) {

								case 'field':
								case 'like':

									// Устанвлено правило поиска в бд
									if( isset( $field['search']['field'] ) ){
										
										$extraWhere = $this->Api2Db->functions->put_values( $field['search']['field'] , $p->putvalues );

								
									}else{

										$this->storage['debug']['make_where'][] = [
											'error'	=> 'undefined',
											'param'	=> $keyField,
											'where' => 0
										];

										return false;
									
									}

								break;
								case 'key':

									$extraWhere = $key . '="' . $val . '"';
								
								break;

								case 'select':
								
									// Тут у нас какое-то дублирование проверок, подумать над вариантами исправления
									if( in_array( $field['options'] ) ) { 

										// Если установлен массив значений
										if( in_array( $field['options'][$val] ) ) {
										
											if(  isset( $field['options'][$val]['where'] ) )
												$extraWhere = $this->Api2Db->functions->put_values( $field['options'][$val]['where'], $p->putvalues );
										
											else
												$extraWhere = $key . '="' . $field['options'][$val] . '"';
										

										}else{

											$this->storage['debug']['make_where'][]= [
												'error' => 'undefined',
												'param' => $keyField, 
												'value' => $val,
												'where' => 1
											];

											return false;
										
										}
									}else{
										// Если задан как запрос к БД
										//
										//
										//
										//
										// тут еще не реализовано
									}

								break;

								default:
									$extraWhere = $key . '="' . $val . '"';
							}

						}else{
							$extraWhere = $key . '="' . $val . '"';
						}
									
						// Добавляем параметр к запросу
						if( $extraWhere != '' ){


								$where[] 	= $delimetr;

							$where[$key] = $extraWhere;


							$i++;
						}


					}elseif( $field['search']['require'] === 'yes' ){

						// Необходимо, что-бы переменная была установлена
						$this->storage->push_debug_by_key( 'make_where', [
							'error'	=> 'required',
							'param'	=> $keyField,
							'where' => 2
						]);
						
						return false;

					}// if( isset($values[$keyFiled]))


				} // if( isset($field['search']))
				
			} // foreach

		}


		if( isset( $where ) )
			$p->db['request']['where'] = array_merge( $p->db['request']['where'], $where );

		return true;

	}

	private function make_fields( $p ){



		if( $p->module['fields'] ){
			
			foreach( $p->module['fields'] as $keyField => $field ) {
			
				if( isset($field['field']) )
					$fields[$keyField] = $field['field'] . ' as ' . $keyField;

				elseif( isset($field['key']) )
					$fields[$keyField] = $field['key'] . ' as ' . $keyField;
				
				else
					$fields[$keyField] = $keyField;

			}
		
		}else{

			$this->storage->push_debug_by_key( 'make_fields', [
				'error' => 'bad_module',
				'module' => $p->module['modname']
			]);


			return false;
		}




		$p->db['request']['fields'] = $fields;


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

			if( !isset( $p->ret['records'] ) ){ 

				// Если передан номер страницы и мы не занем количество записей
				$limit 	= [ $start, $perpage ];
				$page 	= $p->input['page'];

			}elseif( ( $p->input['page'] - 1 ) * $perpage >= $p->ret['records'] ) {
			
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

		if( !isset( $p->make_row  ) )
			return array();
		
		
		$row = array();

		foreach( $p->make_row as $key => $val) {

			// Если надо заполнить по пустым полям, а не из базы
			if( is_integer( $key ) ){
				$key = $val;
				$val = '';
			}

			if( $p->module['fields'][$key]['type'] != 'search' ) {

				$row[$key]['key'] = $key;
				
				// квотируем левое
				$quot = array(
					'"' => '&quot;',
					"'" => '&apos;'
				);	

				$row[$key]['val'] = strtr( $val, $quot );
			
				// Конвертация вывода
				if( !empty( $p->module['fields'][$key]['convert'][$p->action] ) )
					$convert_name_by_action = $p->module['fields'][$key]['convert'][$p->action];
				
				if( !empty( $p->module['fields'][$key]['convert']['*'] ) )
					$convert_name_by_all = $p->module['fields'][$key]['convert']['*'];

				if( isset( $convert_name_by_action ) and isset( $row[$key]['val'] )  )	{

					if( method_exists( $this->Api2Db->converts,  $convert_name_by_action ) )
						$convert = $this->Api2Db->converts->{ $convert_name_by_action }( $row[$key], $p->make_row  );


				} elseif( isset( $convert_name_by_all ) and isset( $row[$key]['val'] ) )	{

					if( method_exists( $this->Api2Db->converts,  $convert_name_by_all ) )
						$convert = $this->Api2Db->converts->{ $convert_name_by_all }( $row[$key], $p->make_row  );		
						
				}


				if( !empty( $convert ) )
					$row[$key] = $convert;
			
				$extend_row	= (array) $this->make_extend_row( $key, $p );
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

		$row = array();
		
		if( isset( $p->module['fields'][$key] ) ) {

			if( $p->module['fields'][$key]['type'] != 'search' ) {

				$keyval 		= $p->module['fields'][$key];
				$row['key'] 	= $key;
				$row['name']	= $key;


				if( !isset( $keyval['edit'] ) )
					$keyval['edit'] = 'no';

				if( isset( $keyval['name'] ) )
					$row['name'] = $keyval['name'];

				elseif( !empty( $this->names[ $key ] ) )
					$row['name'] = $this->names[ $key ];
				
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

	private function make_extend_row( $key, $p ){

		$row = array();

		
		if( isset( $p->module['fields'][$key] ) ) {

			if( $p->module['fields'][$key]['type'] != 'search' ) {

				$keyval = $p->module['fields'][$key];
				
				if( isset( $keyval['type'] ) ){

					// Его может не быть, если не указан rowheads

					$row['type'] = $keyval['type'];
					
					switch( $keyval['type'] ) {
					
						case 'select':
					
							$row['options'] = $this->make_key_options( $key, $p );
					
						break;
					
					}
				}else{
					$row['type'] = 'text';
				} 
				

			}

		}
		
		return $row;

	}

	private function make_key_options( $key, $p ){
		
		$options = array();

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

				$sql = $this->Api2Db->functions->put_values( $p->module['fields'][$key]['options'], $p->putvalues);


				$result = $this->db->select( $sql, 'create_options_to_key_'.$key );

				if( !empty( $result ) )
					$options = $p->db['lastResult'];


			}

		}

		return $options;
	}

	private function make_heads( $p ){

		$heads = array();

		if( isset( $p->module['fields'] ) && is_array( $p->module['fields'] ) ) 

			foreach($p->module['fields'] as $key => $keyval )

				if( $p->module['fields'][$key]['type'] != 'search' )

					$heads[$key] = $this->make_head_row( $key, $p );		
		
		if( isset( $p->input['heads'] ) )
			$p->output['heads'] = $heads;
	}

	private function make_filter( $p ){

		$search = array();

		if( isset( $p->module['fields'] ) && is_array( $p->module['fields'] ) ) {

			foreach( $p->module['fields'] as $key => $keyval ) {
			
				if( isset( $p->module['fields'][$key]['search'] ) ) { 
					
					$search[$key]['key'] 	= $key;
					$search[$key]['name'] 	= $key;
				
					if( isset( $keyval['name'] ) ) 
						$search[$key]['name'] = $keyval['name'];
					
					
					if( isset($keyval['type'])){

						// Установлен тип поля
						$search[$key]['type'] = $keyval['type'];
						
						switch($keyval['type']) {
						
							case 'select':
						
								$search[$key]['options'] = make_key_options( $key, $params );
						
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
		
		if( isset( $p->input['filters'] ) )
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
				'set'		=> 'set',
				'table'		=> '',
				'join'		=> '',
				'where'		=> 'where',
				'groupby'	=> 'group by',
				'order'		=> 'order by',
				'limit'		=> 'limit'
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

				if( $request[ $sqlElem ]['order'] )
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


