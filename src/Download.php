<?php
namespace Svbk\WP\Forms;

use Exception;
use Svbk\WP\Email;

class Download extends Subscribe {

	public $field_prefix = 'dl';
	public $policyNewsletter = '';
	public $action = 'svbk_download';
	public $user_subject = '';

	public function init() {

		$this->policyNewsletter = $this->policyNewsletter ?: __( 'I have read the [privacy-policy-link] and agree to the processing of my data to receive informative material', 'svbk-forms' );

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

	public function processInput( $input_filters = array() ) {

		$input_filters['fid'] = FILTER_VALIDATE_INT;

		return parent::processInput( $input_filters );
	}
	
	protected function getUser(){ 

		$user = parent::getUser();
		
		$user->setAttribute( 'DOWNLOAD', 1 );
		
		return $user;
	}

	protected function getEmail() {

		$email = parent::getEmail();

		$email->setAttribute('DOWNLOAD', 1);
		$email->setAttribute('DOWNLOAD_URL', esc_url( $this->getDownloadLink() ) );

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
			$this->log( 'critical', 'Missing transactional setting in Download form' );
			return;
		}
		
		$tags = array( 'download-request' );		
		
		if( !$this->user_template ) {
			
			$this->log( 'warning', 'Missing user template for form: {form}' );

			$email = $this->getEmail();
			$email->addRecipient( $this->getUser() );
			$email->subject = $this->user_subject ?: __('Download your file', 'svbk-forms');
			
			$body = sprintf( __(' Thanks for your request, please download your file <a href="%s">here</a>', 'svbk-forms' ) , $this->getDownloadLink() );
			
			// $email->text_body = $body;
			$email->html_body = '<p>' . $body .  '</p>';
			$email->tags = array_merge( $email->tags, $tags, array('user-email') );	
			
			if( ! $email->from ) {
				
				$this->log( 'warning', 'Missing from address for form {form}, using wordpress default' );
				
				$email->setFrom(new Email\Contact(
						[
							'email' => get_bloginfo( 'admin_email' ),
							'first_name' => 'Website Admin',
						]				
					)
				);
			}

			try { 
				$this->transactional->send( $email );
			} catch( Exception $e ) {
				$this->addError( $e->getMessage() );
				$this->log( 'error', 'Form user email send error: {error}', array( 'error' => $e->getMessage() ) );
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
			$this->log( 'error', 'The download file specified in Download form isn\'t available', array( 'file_id' => $this->getInput( 'fid' ) ) );
		}

	}

}
