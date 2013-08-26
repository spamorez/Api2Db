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
		
		if( empty( $arg['value'] ) )
			return array( 'error' => 'require' );
		else
			return true;
	}

	final public function sql_unique( $arg ){


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
}