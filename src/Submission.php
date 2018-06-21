<?php
namespace Svbk\WP\Forms;

use Svbk\WP\Helpers;
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
	public $policyFlagAll = false;
	
	public $attributionParams = array();
	
	public $policyUnsubscribe = '';

	public function init() {
	
		$this->policyUnsubscribe = $this->policyUnsubscribe ?: __('You can unsubscribe at any time by clicking on the link at the bottom of each email', 'svbk-forms');
	
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
			'label' => sprintf( $this->policyService ?: __( 'I have read and agree to the "%s"', 'svbk-forms' ), $this->privacyButton() ),
			'required' => true,
			'type' => 'checkbox',
			'priority' => 10,
			'error' => __( 'Privacy Policy terms must be accepted', 'svbk-forms' ),
			'filter' => self::$defaultPolicyFilter,
		);			
		
		parent::init();
	}
	
	protected function validateInput() {

		parent::validateInput();
	
		foreach ( $this->policyTerms as $term => $field ) {

			$value = $this->getInput( $term );

			if ( ! $value && $this->fieldRequired( $field ) ) {
				$this->addError( $this->fieldError( $field, $term ), $term );

				if ( ! $this->policyFlagAll ) {
					$this->addError( $this->fieldError( $field, $term ), 'policy_all' );
				}				
				
				$this->log( 'debug', 'Form error in field {form}.{field}: {error}', array( 'field' => $term, 'error' => $this->fieldError( $field, $term ) ) ); 
			}
			
			if ( $value && $this->getInput('email') ) {
				$this->log( 'info', 'Form policy accepted for {email}', array( 'term' => $term, 'email' => $this->getInput('email') ) ); 
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
	
	public function privacyButton() {
		
		if( function_exists('get_the_privacy_policy_link') ) {
			$policy_link = get_the_privacy_policy_link();
		} else{
			$policy_link = __('Privacy Policy', 'svbk-forms');
		}
		
		return apply_filters( 'svbk_forms_privacy_link', $policy_link, $this );
	}

	public function renderParts( $args = array() ) {

		$defaults = array(
			'policy_scope_text' => $this->policyScope,
			'policy_unsubscribe_text' => $this->policyUnsubscribe,
		);
		
		$args = wp_parse_args( array_filter( $args ), $defaults );		

		$output = parent::renderParts( $args );

		$output['policy']['begin'] = '<div class="policy-agreements">';
		
		$privacy_policy_button = $this->privacyButton();
		
		if( $args['policy_scope_text']  ) {
			$output['policy']['scope'] = '<div class="policy-scope">' . str_replace( '{{privacy-policy}}', $privacy_policy_button, $args['policy_scope_text'] ). '</div>';
		}

		$groupTerms = $this->policyFlagAll && (count( $this->policyTerms ) > 1);
	
		if ( $groupTerms ) {
			
			$policyFlagsId = 'policy-flags-' . $this->field_prefix . self::PREFIX_SEPARATOR . $this->index;
			
			$policy_all_toogle_text = sprintf( __( 'If you do not want to give consent for promotional activities click %s', 'svbk-forms' ),
					'<a class="policy-flags-open disable-anchor" href="#' . esc_attr( $policyFlagsId ) . '">' . __( 'here', 'svbk-forms' ) . '</a>'
			);
			
			$policy_all_text = sprintf( __( 'I declare I have read and accept the %s and consent to receive personalized promotional informations.', 'svbk-forms' ), $privacy_policy_button ) . 
			'</label> <label class="show-policy-parts">' . $policy_all_toogle_text;

			$output['policy']['global'] = $this->renderField( 'policy_all', array(
					'label' => apply_filters('svbk_forms_policy_all_text', $policy_all_text, $privacy_policy_button, $policy_all_toogle_text, $this ),
					'type' => 'checkbox',
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
		wp_enqueue_script('iubenda-consent', 'https://cdn.iubenda.com/consent_solution/iubenda_cons.js');
		
		Helpers\Theme\Script::enqueue( 'silverbackstudio/wp-forms', 'assets/js/forms.js', [ 'version' => '1.2', 'deps' => array( 'jquery', 'iubenda-consent' ) , 'source' => 'gh'  ] );
		Helpers\Theme\Script::enqueue( 'silverbackstudio/utm-form', 'dest/utm_form-1.0.4.min.js', [ 'source' => 'gh', 'profiling' => true ] );
		
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
