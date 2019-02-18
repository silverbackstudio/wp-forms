<?php
namespace Svbk\WP\Forms;

use Svbk\WP\Email;
use Exception;

class Contact extends Subscribe {

	public $field_prefix = 'cnt';
	public $action = 'svbk_contact';

	public $policyNewsletter = '';

	public $admin_subject = '';
	public $admin_template = '';
	
	public $recipient;

	public function init() {

		$this->policyNewsletter = $this->policyNewsletter ?: __( 'I have read the [privacy-policy-link] and agree to the processing of my data to receive informative material', 'svbk-forms' );

		$this->inputFields[ 'request' ] = array(
			'required' => true,
			'label' => __( 'Message', 'svbk-forms' ),
			'type' => 'textarea',
			'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
			'error' => __( 'Please write a brief description of your request', 'svbk-forms' ),
			'priority' => 30,
		);
		
		$this->policyTerms[ 'policy_newsletter' ] = array(
			'label' => do_shortcode( $this->policyNewsletter ),
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

		if ( $this->checkPolicy('policy_newsletter') ){
			parent::mainAction( $flags );
		} else {
			$this->sendUserEmail( array('contact-form') );
		}
		
	}

	protected function sendAdminEmail( $tags = array() ){
		
		if( !$this->transactional ) {
			$this->addError( __( 'Unable to send email, please contact the website owner', 'svbk-forms' ) );
			$this->log( 'warning', 'Missing transactional handler in form {form}' );
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
		$email->addRecipient( $this->recipient );
		$email->setReplyTo( $this->getUser() );
		
		if( $this->admin_template ) {
	
			try { 
				$this->transactional->sendTemplate( $this->admin_template, apply_filters( 'svbk_forms_admin_email', $email, $this ) );
			} catch( Exception $e ) {
				$this->addError( $e->getMessage() );
				$this->log( 'error', 'Error in sending admin email: {error}', array( 'error' => $e->getMessage(), 'template' => $this->admin_template ) );
			}		
			
		} else {
			$this->log( 'warning', 'Missing admin template for form: {form}' );
			
			$email->subject = $this->admin_subject ?: __('Contact Request (no-template)', 'svbk-forms');
			$email->text_body = $this->getInput('request');
			$email->html_body = '<p>' . $this->getInput('request') .  '</p>';
			
			if( !$email->from ) {
				$email->setFrom( new Email\Contact(
					[
						'email' => get_bloginfo('admin_email'),
						'first_name' => 'Website Admin',
					]				
				) );
			}

			try { 
				$this->transactional->send( $email );
			} catch( Exception $e ) {
				$this->addError( $e->getMessage() );
				$this->log( 'error', 'Error in sending text admin email: {error}', array( 'error' => $e->getMessage() ) );
			}			
			
		}		
		
	}

}
