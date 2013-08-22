<?php

class Api2Db_Converts
{
	
	public $storage = [];

	final public function __construct( $functions )
	{
		$this->storage 		= Api2Db_Storage::Instance();
		$this->functions 	= $functions;
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