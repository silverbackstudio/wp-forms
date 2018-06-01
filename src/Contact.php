<?php
namespace Svbk\WP\Forms;

use Svbk\WP\Email;
use Exception;

class Contact extends Subscribe {

	public $field_prefix = 'cnt';
	public $action = 'svbk_contact';

	public $admin_subject = '';
	public $admin_template = '';
	
	public $recipient;

	public function init() {

		$this->inputFields[ 'request' ] = array(
			'required' => true,
			'label' => __( 'Message', 'svbk-forms' ),
			'type' => 'textarea',
			'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
			'error' => __( 'Please write a brief description of your request', 'svbk-forms' ),
			'priority' => 30,
		);
		
		$this->policyTerms[ 'policy_newsletter' ] = array(
			'label' => __( 'I have read the Policy and agree to periodically receive informative material', 'svbk-forms' ),
			'required' => false,
			'error' => __( 'The newsletter policy must be accepted to continue', 'svbk-forms' ),
			'priority' => 20,
			'type' => 'checkbox',
			'filter' => self::$defaultPolicyFilter,
		);		

		parent::init();
		
	}

	protected function mainAction( $flags = array() ) {
		
		$this->sendAdminEmail( array('contact-form') );
		$this->sendUserEmail( array('contact-form') );			

		if ( $this->checkPolicy('policy_newsletter') ){
			parent::mainAction( $flags );
		} 
		
	}

	protected function sendAdminEmail( $tags = array() ){
		
		if( !$this->transactional ) {
			$this->addError( __( 'Unable to send email, please contact the website owner', 'svbk-forms' ) );
			return;
		}
		
		if( !$this->recipient ) {
			$this->recipient = new Email\Contact( 
				[
					'email' => get_bloginfo('admin_email'),
					'first_name' => 'Website Admin',
				]
			);
		}		
		
		$email = $this->getEmail();
		$email->tags = array_merge( $email->tags, $tags, array('admin-email') );
		$email->to = $this->recipient;
		$email->reply_to = $this->getUser();
		
		if( $this->admin_template ) {
	
			try { 
				$this->transactional->sendTemplate( apply_filters( 'svbk_forms_admin_email', $email, $this ), $this->admin_template );
			} catch( Exception $e ) {
				$this->addError( $e->getMessage() );
			}		
			
		} else {
			
			$email->subject = $this->admin_subject ?: __('Contact Request (no-template)', 'svbk-forms');
			$email->text_body = $this->getInput('request');
			$email->html_body = '<p>' . $this->getInput('request') .  '</p>';
			
			if( !$email->from ) {
				$email->from = new Email\Contact(
					[
						'email' => get_bloginfo('admin_email'),
						'first_name' => 'Website Admin',
					]				
				);
			}

			try { 
				$this->transactional->send( $email );
			} catch( Exception $e ) {
				$this->addError( $e->getMessage() );
			}			
			
		}		
		
	}

}
