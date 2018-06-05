<?php
namespace Svbk\WP\Forms;

use Exception;
use Svbk\WP\Email;

class Download extends Subscribe {

	public $field_prefix = 'dl';
	public $action = 'svbk_download';

	public function init() {

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

	public function processInput( $input_filters = array() ) {

		$input_filters['fid'] = FILTER_VALIDATE_INT;

		return parent::processInput( $input_filters );
	}
	
	protected function getUser(){ 

		$user = parent::getUser();
		
		$user->addAttribute( 'DOWNLOAD', 1 );
		
		return $user;
	}

	protected function getEmail() {

		$email = parent::getEmail();

		$email->attributes['DOWNLOAD'] = 1;
		$email->attributes['DOWNLOAD_URL'] = esc_url( $this->getDownloadLink() );

		return $email;
	}
	
	protected function mainAction( $flags = array() ) {

		if ( $this->checkPolicy('policy_newsletter') ) {
			parent::mainAction( $flags );
		} else {
			$this->sendUserEmail( array('download-form') );
		}

	}	


	protected function getDownloadLink() {
		return wp_get_attachment_url( $this->getInput( 'fid' ) );
	}

	protected function sendUserEmail( $tags = array() ){
		
		if( !$this->transactional ) {
			$this->addError( __( 'Unable to send email, please contact the website owner', 'svbk-forms' ) );
			return;
		}
		
		$tags = array( 'download-request' );		
		
		if( !$this->user_template ) {

			$email = $thia->getEmail();
			$email->subject = $this->admin_subject ?: __('Contact Request (no-template)', 'svbk-forms');
			
			$body = sprintf( __(' Thanks for your request, please download your file <a href="%s">here</a>', 'svbk-forms' ) , $this->getDownloadLink() );
			
			$email->text_body = $body;
			$email->html_body = '<p>' . $body .  '</p>';
			$email->tags = array_merge( $email->tags, $tags, array('user-email') );	
			
			if( $email->from ) {
				$email->from = new Email\Contact(
					[
						'email' => $_SERVER['SERVER_ADMIN'] ?: 'webmaster@silverbackstudio.it',
						'first_name' => 'Website Admin',
					]				
				);
			}

			try { 
				$this->transactional->send( $email );
			} catch( Exception $e ) {
				$this->addError( $e->getMessage() );
			}		
			
		} else {
			return parent::sendUserEmail( $tags );
		}
	
	}

	public function renderParts( $attr = array() ) {

		$output = parent::renderParts( $attr );
		$output['input']['file'] = '<input type="hidden" name="' . $this->fieldName( 'fid' ) . '" value="' . $attr['file'] . '" >';

		return $output;
	}

	protected function validateInput() {

		parent::validateInput();

		$post = get_post( (int) $this->getInput( 'fid' ) );

		if ( ! $post || ('attachment' != $post->post_type) ) {
			$this->addError( __( 'The specified download doesn\'t exists anymore. Please contact site owner', 'svbk-forms' ) );
		}

	}

}
