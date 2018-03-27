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
	
	protected function mainAction( $flags = array() ) {

		if ( $this->checkPolicy( 'policy_newsletter' ) && !empty( $this->marketing ) && !empty( $this->marketing_lists ) ) {
		
			$user = $this->getUser();
			$user->lists = $this->marketing_lists;
		
			try { 
				$subscribed_user = $this->marketing->createContact( $user );
				
				do_action('svbk_forms_subscribed_user', $subscribed_user, $user, $this );
				
			} catch( Email\Marketing\Exceptions\ContactAlreadyExists $e ) {
				
				$this->marketing->saveContact( $user );
				do_action('svbk_forms_updated_user', $user, $this );	
				
			} catch( Exception $e ) {
				$this->addError( $e->getMessage() );
			}
			
			setcookie("mktUserId", $user->uuid(), time() + 6 * MONTH_IN_SECONDS );
			setcookie("mktUser", base64_encode( $user->email ), time() + 6 * MONTH_IN_SECONDS );
		}

		if( empty( $flags['disable_user_email'] ) ) {
			$this->sendUserEmail( array('subscribe-form') );
		}

	}

	protected function sendUserEmail( $tags = array() ){
		
		if( $this->transactional && $this->user_template ) {
	
			$email = $this->getEmail();
			$email->to = $this->getUser();
			
			$email->tags = array_merge( $email->tags, $tags, array('user-email') );

			try { 
				$this->transactional->sendTemplate( $email, $this->user_template );
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
		
		$user->addAttribute('SVBK_UID', $user->uuid() );

		if( $this->getInput( 'lname' ) ) {
			$user->last_name = ucfirst( $this->getInput( 'lname' ) );
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
