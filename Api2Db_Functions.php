<?php


class Api2Db_Functions
{

	final public function __construct()
	{
		$this->storage 		= Api2Db_Storage::Instance();
		$this->db 			= Api2Db_Db::Instance();
	}

	// подстановка значений в sql запрос
	final public function put_values( $string, $values, $escape = true ){

		if( !is_string( $string ) )
			return false;

		if( !is_array( $values ) )
			return $string;

		if( !is_bool( $escape ) )
			$escape = true;


		$arrayPathToValue = $this->make_recurcive_path( $values );

		return strtr($string, $arrayPathToValue);
	}

	final public function make_recurcive_path( $values, $path = '', $paths = [], $escape = true ){

		if( is_array( $values ) ){

			foreach ( $values as $key => $value ) {

				if( $path )
					$add = $path."->".$key;
				else
					$add = $key;

				if( is_array( $value ) ){

					$paths[":" . $add . "->array"] = $value;
				
					$paths = array_merge( $paths, $this->make_recurcive_path( $value, $add, $paths ) );
				
				}else if( is_string( $value ) || is_integer( $value ) ){

					if( $escape && is_string( $value ) )
						$value = addcslashes( trim( $value ), '\'"' );

					$paths[":" . $add] = $value;
				}
			}
			
			return $paths;

		}else{
			return [];
		}
	}


	// Замена file_get_contents()
	final public function read_file( $file ){

		$file_handle = fopen( $file, "r" );
		$ret 		 = '';

		while ( !feof( $file_handle ) )
		   $ret .= fread( $file_handle, 100000 );


		
		fclose( $file_handle );	

		return $ret;

	}

	final public function sql_escape( $val ){
		return addcslashes( trim( $val ), '\'"' );
	}


	// Проверка строки на печатные символы 
	final public function is_alphabet( $string ){
		
		$IsOK = true;
		
		if ( strlen( $string ) == 0 )
			return false;

		for ( $i = 0; $i < strlen( $string ); $i++ ){
			
			$c = $string{$i};
			
			// сломай мозги. Начало...
			if(
				!( 
					   (($c >= 'a') && ($c <= 'z')) 
					|| (($c >= 'A') && ($c <= 'Z')) 
					|| ($c >= '0' && $c <= '9') 
					|| $c == '.' 
					|| $c == '-' 
					|| $c == '_'
				) 
			){
				$IsOK = false;
			}
		}

		return $IsOK;
	}



	final public function is_float( $string ){
		
		$IsOK = true;

		for ( $i = 0; $i < strlen( $string ); $i++ ){
			
			if ( 
				( ( $string{$i} < '0' ) || ( $string{$i} > '9' ) )
				&&	( $string{$i} <> '.' ) 
				&&	( $string{$i} <> '-' )
			)
				$IsOK = false;	
		}

		return $IsOK;
	}


	final public function is_domain( $email ){
		
		$p = '/^([-a-z0-9]+\.)+([a-z]{2,3}';
		$p .= '|info|arpa|aero|coop|name|museum|mobi)$/ix';
		
		return preg_match( $p, $email );		 
	}



	final public function is_email( $email ){
		
		$p = '/^[a-z0-9!#$%&*+-=?^_`{|}~]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*';
		$p .= '@([-a-z0-9]+\.)+([a-z]{2,3}';
		$p .= '|info|arpa|aero|coop|name|museum|mobi)$/ix';

		return preg_match( $p, $email );		 
	}


	final public function is_date( $date ){
		
		$p = '/^([0-9]{2})[\.-]([0-9]{2})[\.-]([0-9]{2,4}';
		$p .= '$/ix';
		
		return preg_match( $p, $date );  
	}


	final public function is_valid_date($data, $format = 'Y-m-d') {
	    if (date($format, strtotime($data)) == $data) {
	        return true;
	    } else {
	        return false;
	    }
	}

	// Допускается использование * в адресе
	final public function is_email_mask( $email ){
		 
		if( $email == '*' ) 
		 	return 1;
			 
		$p = '/^[a-z0-9!#$%&*\*+-=?^_`{|}~]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*';
		$p .= '@([-a-z0-9]+\.)+([a-z]{2,3}';
		$p .= '|info|arpa|aero|coop|name|museum|mobi)$/ix';
			
		return preg_match( $p, $email );		 
	}

	// Допускается использование формата @domain.ru 
	final public function is_address( $email ){
		
		$p = '/^[a-z0-9!#$%&*\*+-=?^_`{|}~]*(\.[a-z0-9!#$%&*+-=?^_`{|}~]*)*';
		$p.= '@([-a-z0-9]+\.)+([a-z]{2,3}';
		$p.= '|info|arpa|aero|coop|name|museum|mobi)$/ix';
		
		return preg_match( $p, $email );		 
	}

	final public function is_phone( $phone ){
		$p = '/^\+{0,1}\s*\d{1,2}\s*\(\d{2,6}\)[\d-\s]{3,10}$/ix';
		return preg_match( $p, $phone );
	}


	final public function is_digits( $value ){
		$p = '/^[0-9]+$/ix';
		return preg_match( $p, $value );
	}

	final public function is_legal( $value ){
		$p = '/^[0-9A-Za-z_\-\.]+$/ix';
		return preg_match( $p, $value );
	}

	final public function is_ip( $ip ){
		$p = '/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/ix';
		return preg_match( $p, $ip );
	}

	final public function is_host( $host ){
		return $this->is_domain( $host ) or $this->is_ip( $host );
	}

	// Определяет, является ли строка sql запросом
	final public function is_sql( $str ){
		
		$str 	= mb_strtolower( $str );
		$finder = array( 'select', 'insert', 'update', 'delete' );
		$ret 	= false;

		foreach( $finder as $value ) {
			
			if( preg_match( '#^' . $value . ' .*$#', $str ) ){

				// если это select или delete, то полюбому должен быть from
				if( $value == 'select' or $value == 'delete' ){

					if( preg_match( '#^.* from .*$#', $str) )
						$ret = true;

				// У insert'a обязателен into
				}elseif( $value == 'insert' ){

					if( preg_match( '#^.* into .*$#', $str ) )
						$ret = true;

				// У updat'a обязателен set
				}elseif( $value == 'update' ) {

					if( preg_match( '#^.* set .*$#', $str ) )
						$ret = true;
				}

			}

		}

		return $ret;
	}

}
