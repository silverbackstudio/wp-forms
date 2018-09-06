<?php
namespace Svbk\WP\Forms;

use Exception;
use DateTime;
use Svbk\WP\Email;
use Svbk\WP\Helpers\Networking\IpAddress;

class Subscribe extends Submission {

	public $field_prefix = 'sbs';
	public $action = 'svbk_subscribe';
	
	public $transactional;
	public $marketing;
	public $sender;
	
	public $marketing_lists = array();
	public $user_template = '';
	
	public $policyMarketing = '';
	
	public function init(){

		$this->policyMarketing = $this->policyMarketing ?: __( 'I have read the [privacy-policy-link] and agree to the processing of my data to receive personalized promotional materials based on my browsing data.', 'svbk-forms' );
	
		$this->policyTerms[ 'policy_marketing' ] = array(
			'label' => do_shortcode( $this->policyMarketing ),
			'type' => 'checkbox',
			'error' => __( 'The marketing policy must be accepted to continue', 'svbk-forms' ),
			'priority' => 30,
			'required' => false,
			'filter' => self::$defaultPolicyFilter,
		);	
	
		parent::init();		
		
	}
	
	protected function mainAction( $flags = array() ) {

		$this->sendUserEmail();		

		if ( !empty( $this->marketing ) && !empty( $this->marketing_lists ) ) {
		
			$user = $this->getUser();
			
			do_action( 'svbk_forms_user_created', $user, $this );

			try { 
				$subscribed_user = $this->marketing->createContact( $user );
				
				do_action('svbk_forms_subscribed_user', $subscribed_user, $user, $this );
			} catch( Email\Marketing\Exceptions\ContactAlreadyExists $e ) {
				
				$this->marketing->saveContact( $user );
				
				do_action('svbk_forms_updated_user', $user, $this );
			} catch( Exception $e ) {
				$this->addError( $e->getMessage() );
				$this->log( 'error', 'Error in subscribing form user to marketing: {error}', array( 'error' => $e->getMessage() ) );
			}
			
		}

	}

	protected function sendUserEmail( $tags = array() ){
		
		if( $this->transactional && $this->user_template ) {
	
			$email = $this->getEmail();
			$email->to = $this->getUser();
			
			$email->tags = array_merge( $email->tags, $tags, array('user-email') );

			try { 
				$this->transactional->sendTemplate( apply_filters( 'svbk_forms_user_email', $email, $this ), $this->user_template );
			} catch( Exception $e ) {
				$this->addError( $e->getMessage() );
				$this->log( 'error', 'Error in sending form user email: {error}', array( 'error' => $e->getMessage(), 'template' => $this->user_template ) );
			}		
			
		}		
		
	}
	
	protected function getUser(){
		
		$user = new Email\Contact([
			'email' => trim( $this->getInput( 'email' ) ),
			'first_name' => ucfirst( $this->getInput( 'fname' ) ),
		]);
		
		if( $this->getInput( 'lname' ) ) {
			$user->last_name = ucfirst( $this->getInput( 'lname' ) );
		}		
		
		$user->addAttribute('SVBK_UID', $user->uuid() );
		$user->addAttribute('LANGUAGE', get_bloginfo('language') );
		
		$user->lists = $this->marketing_lists;

		$user->addAttribute('OPTIN_NEWSLETTER', 1 );	
		$user->addAttribute('OPTIN_NEWSLETTER_DATE', $this->marketing->formatDate( new DateTime() ) );
		$user->addAttribute('OPTIN_NEWSLETTER_IP', sha1( IpAddress::getClientAddress() ) );		
		
		if ( $this->checkPolicy('policy_marketing') ) {
			$user->addAttribute('OPTIN_MARKETING', 1 );	
			$user->addAttribute('OPTIN_MARKETING_DATE',  $this->marketing->formatDate( new DateTime() ) );
			$user->addAttribute('OPTIN_MARKETING_IP', IpAddress::getClientAddress() );					
		}
		
		/**
		 * Add attribution and conversion parameters 
		 */
		$attribution_params = $this->attributionParams;
		$attribution_form_params = array();
		
		if ( isset( $attribution_params['additional_params_map'] ) ) {
			$attribution_form_params = array_values( $attribution_params['additional_params_map'] );
			unset( $attribution_params['additional_params_map'] );
		}		
		
		if ( $this->checkPolicy('policy_marketing') && $attribution_params ) {
			$attribution_form_params = 	array_merge( 
				$attribution_form_params, 
				array_values( $attribution_params )
			);
		}		
		
		if ( !empty( $attribution_form_params ) ) {
			$attribution_values = filter_input_array ( INPUT_POST, array_fill_keys( $attribution_form_params, FILTER_SANITIZE_SPECIAL_CHARS ) );
	
			foreach( $attribution_values as $attribution_param => $attribution_value ) {
				$user->addAttribute( $attribution_param, urldecode( $attribution_value ) );
			}		
		}
		
		return $user;
	}	
	
	protected function getEmail(){
		
		$email = new Email\Message();
		$email->attributes = $this->inputData;
		
		if( $this->sender ) {
			$email->from = $this->sender;
		}
		
		return $email;
	}	
	

}
