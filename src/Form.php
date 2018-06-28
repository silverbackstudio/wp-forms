<?php

namespace Svbk\WP\Forms;

use Svbk\WP\Helpers;

add_action( 'after_setup_theme', __NAMESPACE__ . '\\Form::load_texdomain', 15 );

class Form {

	public $index = 0;
	public static $next_index = 1;

	public $field_prefix = 'frm';
	
	public $submitUrl = '';
	public $submitButtonText = '';		
	
	public $inputFields = array();	
	protected $inputData = array();
	protected $inputErrors = array();	

	public $confirmMessage = '';

	public static $defaults = array();
	
	public $action = 'svbk_submission';	
	
	public $errors = array();

	const PREFIX_SEPARATOR = '-';

	public function __construct( $properties = array() ) {
		
		$this->index = self::$next_index++;

		self::configure( $this, array_merge( Form::$defaults, self::$defaults, $properties ) );
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'init', array( $this, 'init' ), 20 );		
		add_action( 'init', array( $this, 'processSubmission' ), 100 );		
		add_action( 'wp', array( $this, 'ready' ) );
	}

	public function ready(){
		do_action( 'svbk_forms_ready', $this );
	}

	protected static function configure( &$target, $properties ) {
		
		foreach ( $properties as $property => $value ) {
			if ( ! property_exists( $target, $property ) ) {
				continue;
			}

			if ( is_array( $target->$property ) ) {
				$target->$property = array_merge( $target->$property, (array)$value );
			} else {
				$target->$property = $value;
			}
		}
		
	}

	public function init() {
		
		$this->submitButtonText = $this->submitButtonText ?: __('Submit', 'svbk-forms');		
		
		do_action( 'svbk_forms_init', $this );
	} 
	
	public static function load_texdomain() {
		load_textdomain( 'svbk-forms', dirname( __DIR__ ) . '/languages/svbk-forms-' . get_locale() . '.mo' );
	}	

	public function addInputFields( $fields, $key = '', $position = 'after' ) {
		$this->inputFields = Renderer::arraykeyInsert( $this->inputFields, $fields, $key, $position );
	}

	public function removeInputFields() {
		$this->inputFields = array();
	}
	
	public function removeInputField( $field ) {
		if ( array_key_exists( $field, $this->inputFields ) ) {
			unset( $this->inputFields[$field] );
		}
	}	

	public function insertInputField( $fieldName, $fieldParams, $after = null ) {

		if ( $after ) {
			$this->inputFields = Renderer::arrayKeyInsert( $this->inputFields, array(
				$fieldName => $fieldParams,
			), $after );
		} else {
			$this->inputFields[ $fieldName ] = $fieldParams;
		}

	}
	
	public function submitUrl() {

		return home_url(
			add_query_arg(
				array(
					'svbkSubmit' => $this->action,
				)
			)
		);

	}	
	
	public function getInput( $field = null ) {
		
		if (null === $field){
			$value = $this->inputData;
		} else {
			$value = isset( $this->inputData[ $field ] ) ? $this->inputData[ $field ] : null;
		}
		
		return apply_filters( 'svbk_forms_input_value', $value );
	}

	protected function getField( $fieldName ) {

		if ( isset( $this->inputFields[ $fieldName ] ) ) {
			return $this->inputFields[ $fieldName ];
		} else {
			return false;
		}

	}

	protected function validateInput() {

		foreach ( $this->inputFields as $name => $field ) {

			$value = $this->getInput( $name );

			if ( ! $value && $this->fieldRequired( $field ) ) {
				$this->addError( $this->fieldError( $field, $name ), $name );
				$this->log( 'debug', 'Form error in field {form}.{field}: {error}', array( 'field' => $name, 'error' => $this->fieldError( $field, $name ) ) ); 
			}
		}
		
		do_action( 'svbk_forms_validate', $this );
	}
	
	public function processSubmission() {

		$submitAction = filter_input( INPUT_GET, 'svbkSubmit', FILTER_SANITIZE_SPECIAL_CHARS );

		if ( $submitAction !== $this->action ) {
			return;
		}

		if( filter_input( INPUT_POST, 'ajax', FILTER_VALIDATE_BOOLEAN ) && ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		$this->log( 'debug', 'Form <{form}> submitted', array( 'input' => $this->getInput() ) ); 

		$this->processInput();
		$this->validateInput();

		do_action( 'svbk_forms_submit_before', $this );

		if ( empty( $this->errors ) ) {
			
			$this->mainAction();
			
			do_action( 'svbk_forms_submit_success', $this );
		}
		
		do_action( 'svbk_forms_submit_after', $this );
		
		$redirect_to = filter_input( INPUT_POST, $this->fieldName('redirect_to'), FILTER_VALIDATE_INT );
		$redirect_url = null;
		
		if ( $redirect_to ) {
			$redirect_url = get_permalink( $redirect_to );
		}

		if( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			@header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
			@header( 'Content-Type: application/json' );
			send_nosniff_header();
			echo $this->formatResponse( $redirect_url );
			exit;
		}

		//self::$form_errors = $errors;

		if( empty( $this->errors ) && $redirect_url ) {
			wp_redirect( $redirect_url );
			exit;
		}		
	}

	public function formatResponse( $redirect_url = null ) {

		$errors = $this->getErrors();

		if ( ! empty( $errors ) ) {

			return json_encode(
				array(
					'prefix' => $this->field_prefix,
					'status' => 'error',
					'errors' => $errors,
				)
			);

		}

		$response = array(
			'prefix' => $this->field_prefix,
			'status' => 'success',
			'message' => $this->confirmMessage(),
		);
		
		if ( $redirect_url ) {
			$response['redirect'] = $redirect_url;
		}

		return apply_filters('svbk_forms_response', json_encode( $response ), $this );
	}

	public function confirmMessage() {
		return $this->confirmMessage ?: __( 'Thanks for your request, we will reply as soon as possible.', 'svbk-forms' );
	}

	protected function mainAction(){ 
		do_action('svbk_forms_main_action', $this);
	}

	protected function addError( $error, $field = null ) {

		if ( $field ) {
			$this->errors[ $field ][] = $error;
		} else {
			$this->errors[] = $error;
		}

	}

	public static function setDefaults( $defaults ){
		self::$defaults = $defaults;
	}

	public function getErrors( $field = '' ) {
		
		if( ! $field )  {
			return $this->errors;
		}
		
		if ( isset( $this->errors[ $field ] ) ) {
			return $this->errors[ $field ];
		} 
		
		return array();
	}

	public function processInput( $input_filters = array() ) {

		$index = filter_input( INPUT_POST, 'index', FILTER_VALIDATE_INT );

		if ( $index === false ) {
			$this->addError( __( 'Input data error', 'svbk-forms' ) );
			return;
		} else {
			$this->index = $index;
		}

		$input_filters = array_merge(
			$input_filters,
			wp_list_pluck( $this->inputFields, 'filter' )
		);

		$hashed_fields = array();
		$inputs = array();

		foreach ( $input_filters as $field => $filter ) {
			$hashed_field_name = $this->fieldName( $field );
			$hashed_filters[ $hashed_field_name ] = $filter;
			$input[ $field ] = $hashed_field_name;
		}

		$hashed_inputs = filter_input_array( INPUT_POST, $hashed_filters );

		foreach ( $input as $field => $hashed_field_name ) {
			$input[ $field ] = $hashed_inputs[ $hashed_field_name ];
		}

		$this->inputData = apply_filters('svbk_forms_input', $input, $input_filters, $hashed_filters, $hashed_inputs );
	}

	public function fieldName( $fieldName, $hash = true ) {

		$clearText = $this->index . '_' . $fieldName;

		if ( ! $hash ) {
			return $this->field_prefix . '_' . $clearText;
		}

		return $this->field_prefix . '_' . wp_create_nonce( $clearText, $this->action );
	}

	protected static function fieldRequired( $fieldAttr ) {
		return (bool) ( isset( $fieldAttr['required'] ) ? $fieldAttr['required'] : false );
	}

	protected static function fieldError( $fieldAttr, $name = '' ) {
		return ( isset( $fieldAttr['error'] ) ? $fieldAttr['error'] : sprintf( __( 'Empty or invalid field [%s]', 'svbk-forms' ), $name )  );
	}

	public function renderField( $fieldName, $fieldAttr, $errors = array() ) {

		if ( ! is_array( $fieldAttr ) ) {
			return $fieldAttr;
		}

		$type = isset( $fieldAttr['type'] ) ? $fieldAttr['type'] : 'text';
		$fieldLabel = isset( $fieldAttr['label'] ) ? $fieldAttr['label'] : '';
		$value = isset( $fieldAttr['default'] ) ? $fieldAttr['default'] : '';

		$fieldClass = preg_replace( '/\[\d+\]/i', '', $fieldName );
		$fieldClass = preg_replace( '/\[(\w+)\]/i', '-$1', $fieldClass );

		$classes = array_merge(
			array(
				$this->field_prefix . self::PREFIX_SEPARATOR . $fieldClass . '-group',
				'field-group',
			),
			isset( $fieldAttr['class'] ) ? (array) $fieldAttr['class'] : array()
		);

		if ( $this->fieldRequired( $fieldAttr ) ) {
			$classes[] = 'required';
		}

		$fieldNameHash = esc_attr( $this->fieldName( $fieldName ) );
		$fieldId = esc_attr( $this->fieldName( $fieldName, false ) );
		$fieldId = preg_replace( '/\[(\d+)\]/i', '-$1', $fieldId );
		$fieldId = preg_replace( '/\[(\w+)\]/i', '-$1', $fieldId );

		$labelElement = '<label for="' . $fieldId . '">' . $fieldLabel . '</label>';

		$output = '<div class="' . esc_attr( join( ' ', $classes ) ) . '">';
		$output .= apply_filters('svbk_forms_before_field', '', $fieldName, $fieldAttr, $this);

		if ( 'hidden' === $type ) {
			$output .= '<input class="'. esc_attr($fieldClass) .'" type="' . esc_attr( $type ) . '" name="' . $fieldNameHash . '" id="' . $fieldId . '" value="' . esc_attr( $value ) . '" />';
		} elseif ( 'checkbox' === $type ) {
			$output .= '<input class="'. esc_attr($fieldClass) .'" type="' . esc_attr( $type ) . '" name="' . $fieldNameHash . '" id="' . $fieldId . '" value="1" />' . $labelElement;
		} elseif ( 'textarea' === $type ) {
			$output .= $labelElement . '<textarea type="' . esc_attr( $type ) . '" name="' . $fieldNameHash . '" id="' . $fieldId . '">' . esc_html( $value ) . '</textarea>';
		} elseif ( ('select' === $type) && ! empty( $fieldAttr['choices'] ) ) {
			$output .= $labelElement;
			$output .= '<select class="'. esc_attr($fieldClass) .'" name="' . $fieldNameHash . '" id="' . $fieldId . '" >';
			foreach ( $fieldAttr['choices']  as $cValue => $cLabel ) {
				$output .= '  <option value="' . esc_attr( $cValue ) . '" ' . selected( $value, $cValue, false ) . '>' . esc_html( $cLabel ) . '</option>';
			}
			$output .= '</select>';
		} elseif ( ('checkboxes' === $type) && ! empty( $fieldAttr['choices'] ) ) {
			$output .= '<label >' . $fieldLabel . '</label>';
			$output .= '<div name="' . $fieldNameHash . '" id="' . $fieldId . '" >';
			foreach ( $fieldAttr['choices']  as $cValue => $cLabel ) {
				$output .= '  <div class="select field-pair">';
				$output .= '  <input class="'. esc_attr($fieldClass) .'" id="' . $fieldId . '_' . esc_attr( $cValue ) . '"  name="' . $fieldNameHash . '[' . $cValue . ']" type="checkbox" value="1" ' . checked( $value, $cValue, false ) . '  />';
				$output .= '  <label for="' . $fieldId . '_' . esc_attr( $cValue ) . '">' . esc_html( $cLabel ) . '</label>';
				$output .= '  </div>';
			}
			$output .= '</div>';
		} elseif ( ('radio' === $type) && ! empty( $fieldAttr['choices'] ) ) {
			$output .= '<label >' . $fieldLabel . '</label>';
			$output .= '<div name="' . $fieldNameHash . '" id="' . $fieldId . '" >';
			foreach ( $fieldAttr['choices']  as $cValue => $cLabel ) {
				$output .= '  <div class="radio field-pair">';
				$output .= '  <input class="'. esc_attr($fieldClass) .'" id="' . $fieldId . '_' . esc_attr( $cValue ) . '"  name="' . $fieldNameHash . '" type="radio" value="' . esc_attr($cValue) . '" ' . selected( $value, $cValue, false ) . '  />';
				$output .= '  <label for="' . $fieldId . '_' . esc_attr( $cValue ) . '">' . esc_html( $cLabel ) . '</label>';
				$output .= '  </div>';
			}
			$output .= '</div>';
		} elseif ( 'image' === $type ) {
			$output .= $labelElement . '<input class="'. esc_attr($fieldClass) .'" type="file" name="' . $fieldNameHash . '" id="' . $fieldId . '" />';
			$output .= wp_get_attachment_image( $value, 'thumb' );
		} else {
			$output .= $labelElement . '<input class="'. esc_attr($fieldClass) .'" type="' . esc_attr( $type ) . '" name="' . $fieldNameHash . '" id="' . $fieldId . '" value="' . esc_attr( $value ) . '" />';
		}

		if ( $errors !== false ) {
			$output .= '<span class="field-errors"></span>';
		}

		$output .= apply_filters('svbk_forms_after_field', '', $fieldName, $fieldAttr, $this);
		$output .= '</div>';

		return $output;
	}

	public function renderParts( $args = array() ) {

		$defaults = array(
			'submit_button_label' => $this->submitButtonText,
		);
		
		$args = wp_parse_args( array_filter( $args ), $defaults );		

		$output = array();

		$form_id = $this->field_prefix . self::PREFIX_SEPARATOR . $this->index;

		$output['formBegin'] = '<form class="svbk-form" data-form-action="' . esc_attr( $this->action ) . '" action="' . esc_url( $this->submitUrl() . '#' . $form_id ) . '" id="' . esc_attr( $form_id ) . '" method="POST">';

		$inputFieldSort = wp_list_sort( $this->inputFields, 'priority', 'ASC', true );

		foreach ( $inputFieldSort as $fieldName => $fieldAttr ) {
			$output['input'][ $fieldName ] = $this->renderField( $fieldName, $fieldAttr );
		}

		$output['requiredNotice'] = '<div class="required-notice">' . __( 'Required fields', 'svbk-forms' ) . '</div>';

		$output['input']['index']  = '<input type="hidden" name="index" value="' . $this->index . '" >';
		$output['submitButton'] = '<button type="submit" name="' . $this->fieldName( 'subscribe' ) . '" class="button">' . esc_html( $args['submit_button_label'] ) . '</button>';
		$output['messages'] = '<div class="messages"><ul></ul><div class="close"><span>' . __( 'Close', 'svbk-forms' ) . '</span></div></div>';
		$output['formEnd'] = '</form>';

		return $output;
	}

	public function enqueue_scripts() {
	
	}
	
	public function log( $level, $message, $context = [] ) {
		do_action( 'log', $level, $message, array_merge( array( 'component' => 'wp-forms', 'form' => $this->action ), $context ) );
	}

}
