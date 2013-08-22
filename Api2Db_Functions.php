<?php


class Api2Db_Functions
{


	// подстановка значений в sql запрос
	final public function put_values( $sqlString, $values, $escape = true ){

		if( !is_string( $sqlString ) )
			return false;

		if( !is_array( $values ) )
			return $sqlString;

		if( !is_bool( $escape ) )
			$escape = true;


		$makeRecurcivePath = function( $values, $path = '', $paths = [], $escape = true) use ( &$makeRecurcivePath ){

			foreach ( $values as $key => $value ) {

				if( $path )
					$add = $path."->".$key;
				else
					$add = $key;

				if( is_array( $value ) ){
				
					$paths = array_merge( $paths, $makeRecurcivePath( $value, $add, $paths ) );
				
				}else if( is_string( $value ) ){

					if( $escape )
						$value = addcslashes( trim( $value ), '\'"' );

					$paths[":" . $add] = $value;
				}
			}
			
			return $paths;
		};

		$arrayPathToValue = $makeRecurcivePath($values);

		return strtr($sqlString, $arrayPathToValue);
	}

	// Проверка строки на символы, отличные от цифр: 
	final public function is_digits( $string ){
		
		$ret = true;

		for ( $i = 0; $i < strlen( $string ); $i++ ){
			
			if ( ($string{$i} < '0') || ( $string{$i} > '9' ) )
				$ret = false;
		
		}

		return $ret;
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


}
