<?php

class Api2Db_Converts
{

	final public function __construct( $functions )
	{
		$this->storage 		= Api2Db_Storage::Instance();
		$this->db 			= Api2Db_Db::Instance();
		$this->functions 	= $functions; // TODO сделать проверку на класс родитель
	}

	public function test_convert( $field, $row ){

		$field['val'] = $field['val'] . 'test-convert';



		return $field;
	}



	public function to_number( $field, $row ){

		$field['val'] = number_format( $field['val'], 0, ".", " " );

		return $field;
	}

}