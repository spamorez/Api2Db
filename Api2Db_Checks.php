<?php

class Api2Db_Checks
{

	final public function __construct( $functions )
	{
		$this->storage 		= Api2Db_Storage::Instance();
		$this->db 			= Api2Db_Db::Instance();
		$this->functions 	= $functions; // TODO сделать проверку на класс родитель
	}

	
	final public function single_email( $arg ){
		if( $arg['value'] != ''  && $this->functions->is_email($arg['value']) == 0 )
		   	return array( 'error' => 'bademail', 'val' => $arg['value'] );
		else
			return true;
	}

	final public function single_email_mask( $arg ){
		if( $arg['value'] != ''  && $this->functions->is_email_mask( $arg['value'] ) == 0 )
		   	return array( 'error' => 'bademail', 'val' => $arg['value'] );
		else
			return true;

	}

	final public function single_require( $arg ){


		if( mb_strlen($arg['value']) == 0 )
			return array( 'error' => 'require' );
		else
			return true;
	}

	final public function sql_unique( $arg ){

		if( empty( $arg['value'] ) )
			return true;


		$sql = $this->db->execute( $arg['sql'], 'sql_unique_' . $arg['field'] );

		if( $sql->errorInfo()[0] = '0000' ){

			if( !empty( $sql->fetchAll( PDO::FETCH_ASSOC )[0] ) )
				return [
					'error' => 'exist',
					'val'	=> $arg['value']
				];

			else
				return true;
		}



		return true;
	}

	final public function sql_exist( $arg ){

		if( empty( $arg['value'] ) )
			return true;


		$sql = $this->db->execute( $arg['sql'], 'sql_exist_' . $arg['field'] );

		if( $sql->errorInfo()[0] = '0000' ){

			if( empty( $sql->fetchAll( PDO::FETCH_ASSOC )[0] ) )
				return [
					'error' => 'not_exist',
					'val'	=> $arg['value']
				];

			else
				return true;
		}



		return true;
	}


	final public function single_empty_value( $arg ){
		
		if( empty($arg['value']) and $arg['isset'] )
			return array( 'error' => 'empty_value' );
		else
			return true;
	}
}