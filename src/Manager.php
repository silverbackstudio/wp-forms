<?php

namespace Svbk\WP\Forms;

use Svbk\WP\Helpers;

class Manager {

	public static $forms = array();
	
	public static function create( $id, $class, $properties = array() ){
		
		$defaults = array(
			'action' => $id,
		);
		
		$form = new $class( array_merge($defaults, $properties) );
		
		self::store( $id, $form );
			
		return $form;
	}
	
	public static function store( $id, Form $form ) {
		self::$forms[ $id ] = $form;
	}	
	
	public static function is( $id, $form ){
		return (self::get( $id ) !== false) && ( self::get( $id ) === $form );
	}	
	
	public static function has( $id ){
		return isset( self::$forms[ $id ] );
	}
	
	public static function get( $id ){
		
		if ( self::has( $id ) ) {
			return self::$forms[ $id ];
		}
		
		return false;
	}
	
}
