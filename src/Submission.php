<?php
namespace Svbk\WP\Forms;

use Svbk\WP\Helpers;
use Svbk\WP\Helpers\Assets\Script;
use Svbk\WP\Helpers\Assets\Style;
use Svbk\WP\Email;

class Submission extends Form {

	public static $defaultPolicyFilter = array(
		'filter' => FILTER_VALIDATE_BOOLEAN,
		'flags' => FILTER_NULL_ON_FAILURE,
	);

	public $field_prefix = 'sub';
	public $action = 'svbk_submission';
	
	public $policyScope = '';
	public $policyService = '';
	public $policyTerms = array();
	public $policyFlagAll = true;
	public $policyAllText = '';
	public $policyAllToggleText = '';	
	
	public $policyUnsubscribe = '';
	
	public $attributionParams = array();
	
	public $recaptchaKey = '';
	public $recaptchaSecret = '';
	public $recaptchaScore = 0.5;
	
	public function init() {
	
		$this->policyScope = $this->policyScope ?: __('<b>[privacy-controller-name]</b> will process your information to respond to your request.', 'svbk-forms');
		$this->policyUnsubscribe = $this->policyUnsubscribe ?: __('You can unsubscribe at any time by clicking on the link at the bottom of each email.', 'svbk-forms');
		$this->policyAllText = $this->policyAllText ?: __( 'I have read and accept the [privacy-policy-link] and agree to receive personalized promotional informations.', 'svbk-forms' );;
		$this->policyAllToggleText = $this->policyAllToggleText ?: __( 'To select partial consents %s', 'svbk-forms' );
		$this->policyService = $this->policyService ?: __( 'I have read and agree to the [privacy-policy-link]', 'svbk-forms' );
	
		$this->attributionParams = apply_filters( 'svbk_forms_attribution_params', array(
		    "utm_source_field"			=> "UTM_SOURCE",
			"utm_medium_field"          => "UTM_MEDIUM",
			"utm_campaign_field"        => "UTM_CAMPAIGN",
			"utm_content_field"         => "UTM_CONTENT",
			"utm_term_field"            => "UTM_TERM",
			"initial_referrer_field"    => "REFERRER",
			"last_referrer_field"       => "LAST_REFERRER",
			"initial_landing_page_field"=> "LANDING_PAGE",
			"visits_field"              => "VISITS_COUNT",
			"additional_params_map" => array( 
				'gclid' => 'GCLID' 
			)			
		), $this );
	
		$this->inputFields['fname'] = array(
			'required' => true,
			'label' => __( 'First Name', 'svbk-forms' ),
			'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
			'error' => __( 'Please enter first name', 'svbk-forms' ),
			'priority' => 10,
		);
		
		$this->inputFields['email'] = array(
			'required' => true,
			'label' => __( 'Email Address', 'svbk-forms' ),
			'type' => 'email', 
			'filter' => FILTER_VALIDATE_EMAIL,
			'error' => __( 'Invalid email address', 'svbk-forms' ),
			'priority' => 20,
		);
		
		$this->policyTerms['policy_service'] = array(
			'label' => do_shortcode( $this->policyService ),
			'required' => true,
			'type' => 'checkbox',
			'priority' => 10,
			'error' => __( 'Privacy Policy terms must be accepted', 'svbk-forms' ),
			'filter' => self::$defaultPolicyFilter,
		);			
		
		if ( $this->recaptchaSecret && ! $this->recaptchaKey ) {
			$this->log( 'error', 'reCAPTCHA secret specified but no key' ); 
		}
		
		parent::init();
	}
	
	protected function validateInput() {

		parent::validateInput();
	
		foreach ( $this->policyTerms as $term => $field ) {

			$value = $this->getInput( $term );

			if ( ! $value && $this->fieldRequired( $field ) ) {
				$this->addError( $this->fieldError( $field, $term ), $term );

				if ( $this->policyFlagAll ) {
					$this->addError( $this->fieldError( $field, $term ), 'policy_all' );
				}				
				
				$this->log( 'debug', 'Form error in field {form}.{field}: {error}', array( 'field' => $term, 'error' => $this->fieldError( $field, $term ) ) ); 
			}
			
			if ( $value && $this->getInput('email') ) {
				$this->log( 'info', 'Form policy accepted for {email}', array( 'term' => $term, 'email' => $this->getInput('email') ) ); 
			} 
		}	
		
		$this->validateAntispam();

	}	
	
	public function validateAntispam(){

		if ( ! $this->recaptchaSecret ) {
			return;
		}
		
		$recaptchaResponse = filter_input( INPUT_POST, 'g-recaptcha-response', FILTER_DEFAULT );
		
        if (empty($recaptchaResponse)) {
	    	$this->addError( __('Unable to perform antispam check. The administrator has been notified. Please contact us direcly or try again later', 'svbk-forms') );
        }

		$ip_address = Helpers\Networking\IpAddress::getClientAddress();

        $params = new \ReCaptcha\RequestParameters($this->recaptchaSecret, $recaptchaResponse, $ip_address, \ReCaptcha\ReCaptcha::VERSION );
        $requestMethod = new \ReCaptcha\RequestMethod\Post();
        $rawResponse = $requestMethod->submit($params);
		$responseData = json_decode( $rawResponse, true );
		
		$responseData['ip'] = $ip_address;
		
  		$this->log( 'debug', 'reCAPTCHA validation response', array( 'rawResponse' => $rawResponse ) ); 

		$response =  \ReCaptcha\Response::fromJson( $rawResponse );	
	
		if ($response->isSuccess()) {
		   
			if ( empty( $responseData['score'] ) ) {
    	  		$this->log( 'warning', 'No score reported by reCAPTCHA, please check API version', array( 'rawResponse' => $rawResponse, 'ip' => $ip_address ) ); 
		    }
		   
		    if( $response->getHostName() !== $_SERVER['SERVER_NAME'] ) {
		    	$this->addError( __('Your antispam check did not validate, please reload the page and try again', 'svbk-formss') );
    	  		$this->log( 'notice', 'reCAPTCHA challenge not passed: invalid hostname reported: {hostname}', $responseData ); 
		    } elseif ( empty( $responseData['action'] ) || ( $responseData['action'] !== $this->action ) ) {
		    	$this->addError( __('Your antispam check did not validate, please reload the page and try again', 'svbk-forms') );
    	  		$this->log( 'notice', 'reCAPTCHA challenge not passed: invalid action reported: [{action}] instad of ' . $this->action , $responseData ); 
		    } else if( ! empty( $responseData['score'] ) && ( floatval( $responseData['score'] ) < $this->recaptchaScore ) ) {
		    	$this->addError( __('Google reCAPTCHA reported you as a probable spammer', 'svbk-forms') );
    	  		$this->log( 'notice', 'reCAPTCHA challenge not passed: insufficient score [{score}] for IP {ip}', $responseData ); 
		    } else {
		    	$this->log( 'info', 'reCAPTCHA challenge passed: Score {score}, IP: {ip}', $responseData ); 
		    }	    
		    
		    
		} else {
	    	$errors = $response->getErrorCodes();
	    	
	    	$this->addError( __('Unable to perform antispam check. The administrator has been notified. Please contact us direcly or try again later', 'svbk-forms') );
	    	
	    	foreach( $errors as $error ) {
	    		$this->log( 'warning', 'Error in validating reCAPTCHA: {error}', array( 'error' => $error, 'rawResponse' => $rawResponse ) ); 
	    	}
	    	
		}	
		
	}
	
	public function checkPolicy( $policyTerm = 'policy_service' ) {
		
		if ( $this->getInput( $policyTerm ) ) {
			return true;
		}

		return false;
	}	
	
	public function processInput( $input_filters = array() ) {


		$input_filters['policy_all'] = self::$defaultPolicyFilter;

		if ( $this->policyTerms ) {
			$input_filters = array_merge(
				$input_filters,
				wp_list_pluck( $this->policyTerms, 'filter' )
			);
		}
		
		parent::processInput( $input_filters );

	}

	protected function getField( $fieldName ) {

		$field = parent::getField( $fieldName );

		if ( ( false === $field ) && isset( $this->policyTerms[ $fieldName ] ) ) {
			$field = $this->policyTerms[ $fieldName ];
		} 
		
		return $field;
	}	
	
	public function renderParts( $args = array() ) {

		$defaults = array(
			'policy_all_text' => $this->policyAllText,
			'policy_all_toggle_text' => $this->policyAllToggleText,
			'policy_scope_text' => $this->policyScope,
			'policy_unsubscribe_text' => $this->policyUnsubscribe,
		);
		
		$args = wp_parse_args( array_filter( $args ), $defaults );		

		$output = parent::renderParts( $args );

		$output['policy']['begin'] = '<div class="policy-agreements">';
		
		if( $args['policy_scope_text']  ) {
			$output['policy']['scope'] = '<div class="policy-scope">' . do_shortcode( $args['policy_scope_text'] ). '</div>';
		}

		$groupTerms = $this->policyFlagAll && (count( $this->policyTerms ) > 1);

		if ( $groupTerms ) {
			
			$policyFlagsId = 'policy-flags-' . $this->action . self::PREFIX_SEPARATOR . $this->index;
			
			$policy_all_toogle = '<a class="policy-flags-open disable-anchor" href="#' . esc_attr( $policyFlagsId ) . '">' . __( 'click here', 'svbk-forms' ) . '</a>.';
			$policy_all_toogle_text = sprintf( $args['policy_all_toggle_text'], $policy_all_toogle );
			$policy_all_text = $args['policy_all_text'] . '</label>&nbsp;<label class="show-policy-parts">' . $policy_all_toogle_text;

			$output['policy']['global'] = $this->renderField( 'policy_all', array(
					'label' => do_shortcode( apply_filters('svbk_forms_policy_all_text', $policy_all_text, $policy_all_toogle_text, $policyFlagsId, $this ) ),
					'type' => 'checkbox',
					'required' => true,
					'class' => 'policy-flags-all',
				)
			);
			
			$output['policy']['flags']['begin'] = '<div class="policy-flags" id="' . esc_attr( $policyFlagsId ) . '" style="display:none;" >';
		}

		$policyTermSort = wp_list_sort( $this->policyTerms, 'priority', 'ASC', true );

		foreach ( $policyTermSort as $policy_part => $policyAttr ) {
			$output['policy']['flags'][ $policy_part ] = $this->renderField( $policy_part, $policyAttr );
		}

		if ( $groupTerms ) {
			$output['policy']['flags']['end'] = '</div>';
		}

		if ( $args['policy_unsubscribe_text'] ) {
			$output['policy']['unsubscribe'] = '<div class="unsubscribe-notice">' . $args['policy_unsubscribe_text'] . '</div>';
		}

		$output['policy']['end'] = '</div>';

		return $output;
	}

	public function enqueue_scripts() {
		
		Script::enqueue( 'silverbackstudio/wp-forms', 'assets/js/forms.js', [ 'version' => '2.2.11', 'deps' => array( 'jquery', 'jquery-ui-widget' ), 'source_options' => ['source' => 'gh'], 'defer' => true ] );
		wp_add_inline_script( 'silverbackstudio/wp-forms', "(function($){ $('.svbk-form').svbkForm(); })(jQuery);" );
		
		if( $this->recaptchaKey ) {
			Script::enqueue( 'recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . $this->recaptchaKey, [ 'source' => false, 'defer' => true, 'version' => 3 ] );
			Script::enqueue( 'silverbackstudio/wp-forms', 'assets/js/recaptcha.js', [ 
				'handle' => 'wp-forms-recaptcha',
				'version' => '2.2.3', 
				'deps' => array( 'jquery-ui-widget', 'silverbackstudio/wp-forms', 'recaptcha-v3' ), 
				'source_options' => ['source' => 'gh'],
				'defer' => true
			] );
			
			wp_localize_script( 'wp-forms-recaptcha', 'reCAPTCHA', array(  
				'key' => $this->recaptchaKey,
				'version' => 3,
			) );			
			
		}
	
		Script::enqueue( 'silverbackstudio/utm-form', 'dest/utm_form-1.0.4.min.js', [ 'source_options' => [ 'source' => 'gh'], 'profiling' => true, 'async' => true, 'defer' => true ] );
		
		$utm_forms_params = array_merge( 
			array(
				"form_query_selector"		=>'form.svbk-form',
				//"add_to_form"				=> "none"
			),
			$this->attributionParams
		);
		
		wp_localize_script( 'silverbackstudio/utm-form', '_uf', apply_filters( 'svbk_forms_utm_params', $utm_forms_params, $this ) );		
		
		parent::enqueue_scripts();
	}

}
