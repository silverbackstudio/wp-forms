<?php
namespace Svbk\WP\Forms;

use Exception;
use Svbk\WP\Email;

class Subscribe extends Submission {

	public $field_prefix = 'sbs';
	public $action = 'svbk_subscribe';
	
	public $transactional;
	public $marketing;
	public $sender;
	
	public $marketing_lists = array();
	public $user_template = '';
	
	public function init(){
	
		$this->policyTerms[ 'policy_marketing' ] = array(
			'label' => __( 'I have read the policy and agree to the processing of my data to receive personalized promotional materials', 'svbk-forms' ),
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
		
		if ( $this->checkPolicy('policy_marketing') ) {
			$user->addAttribute('OPTIN_MARKETING', 1 );	
			$user->addAttribute('OPTIN_MARKETING_DATE', date('c') );
		}
		
		if ( $this->checkPolicy('policy_marketing') && $this->attributionParams ) {
			
			$utm_params = filter_input_array ( INPUT_POST, array_fill_keys( array_values( $this->attributionParams ), FILTER_SANITIZE_SPECIAL_CHARS ) );

			foreach( $utm_params as $utm_param => $utm_value ) {
				$user->addAttribute( $utm_param, $utm_value );
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
