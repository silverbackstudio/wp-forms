/* global grecaptcha */
/* global reCAPTCHA */

(function( $ ) {

    if( typeof grecaptcha === 'undefined' || grecaptcha === null ) {
        return;
    }
    
    if( typeof reCAPTCHA === 'undefined' || reCAPTCHA === null || ! reCAPTCHA.key ){
        return;
    }    

   $('.svbk-form').bind( 'svbkformcreate svbkformreset', function( event, data ){
       
        var $form = $(event.target);
        var svbkForm = $form.svbkForm('instance');
        
        grecaptcha.ready(function() {
            
			grecaptcha.execute( reCAPTCHA.key, { action: svbkForm.option('action') } ).then( function(token) {
			    
			    var $grecaptchaField = $form.find('input[name="g-recaptcha-response"]');
			    
			    if( ! $grecaptchaField.length ) {
			        
    	    		var $grecaptchaField = $('<input>').attr(
    	    		    { 
    	    		        type: 'hidden', 
    	    		        name: 'g-recaptcha-response', 
    	    		    }
    	    		);
    	    		
    	    		$form.append($grecaptchaField);
			    } 
			    
                $grecaptchaField.val(token);
			    
			});     
        });       
       
   } );
    
})(jQuery);