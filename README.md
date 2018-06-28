# Silverback Wordpress Forms

Requires [silverbackstudio/wp-email](https://github.com/silverbackstudio/wp-email) to send emails.

## Example

```php
use Svbk\WP;
use Svbk\WP\Email;

/**
* Register forms after theme setup.
*    
* @return void
*/
function register_custom_forms() {

    /**
    * Set the default email marketing platform and transactional email service
    */  
    Forms\Form::setDefaults( 
        array(
            // Sendinblue   		
            'transactional' => new Email\Transactional\SendInBlue,
            'marketing' => new Email\Marketing\SendInBlue,
            
            // Mailchimp:
            //'transactional' => new Email\Transactional\Mandrill( 'mandrill-apikey' ),
            //'marketing' => new Email\Marketing\MailChimp( 'mailchimp-apikey' ),
        )
    );
   
   
    /**
    * Creates a `Subscribe` form named 'trial' 
    */    	
    Forms\Manager::create( 'trial', Forms\Subscribe::class, 
        [
          	'marketing_lists' => array( 8 ),
        ] 
    );   	
      	
                                     
}

add_action( 'after_setup_theme', 'register_custom_forms', 11 );
```  	
	
Creates a `Contact` form named 'contact' and return the form instance

```php

$trial_form = Forms\Manager::create( 'contact', Forms\Contact::class, [
    'admin_template' => 'template-id-or-name',
    'user_template' => 'template-id-or-name',
    'marketing_lists' => array( 1 ),
    'recipient' => new Email\Contact( [	'email' => env('RECIPIENT_EMAIL') ]	),   
   
    // to customize policy flag texts
    //'policyScope' => __('<b>[privacy-controller-name]</b> will process your information to respond to your request.', '[textdomain]');
    //'policyUnsubscribe' => __('You can unsubscribe at any time by clicking on the link at the bottom of each email.', '[textdomain]');
    //'policyAllText' => __( 'I have read and accept the [privacy-policy-link] and agree to receive personalized promotional informations.', '[textdomain]' );
    //'policyAllToggleText' ==> __( 'To select partial consents %s', '[textdomain]' );
    //'policyService' => __( 'I have read and agree to the [privacy-policy-link]', '[textdomain]' );
    //'policyNewsletter' => __( 'I have read the [privacy-policy-link] and agree to the processing of my data to receive informative material', '[textdomain]' );
    //'policyMarketing' => __( 'I have read the [privacy-policy-link] and agree to the processing of my data to receive personalized promotional materials based on my browsing data.', '[textdomain]' );
] );

```

See [silverbackstudio/wp-shortcakes](https://github.com/silverbackstudio/wp-shortcakes) or [silverbackstudio/wp-widgets](https://github.com/silverbackstudio/wp-widgets) see how to render forms in a shortcode or a widget.
