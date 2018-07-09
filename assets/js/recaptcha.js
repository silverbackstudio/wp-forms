(function( $ ) {

    if( typeof grecaptcha === 'undefined' || grecaptcha === null ) {
        return;
    }
    
    if( typeof reCAPTCHA === 'undefined' || reCAPTCHA === null || ! reCAPTCHA.key ){
        return;
    }    

   $('.svbk-form').bind( 'svbkformcreate svbkformreset', function( event, data ){
       
        var form = $(event.target).svbkForm('instance');
        
        console.log( form.option('action') );

        grecaptcha.ready(function() {
			grecaptcha.execute( reCAPTCHA.key, { action: form.option('action') } ).then( function(token) {
	    		var tokenInput = $('<input>').attr(
	    		    { 
	    		        type: 'hidden', 
	    		        name: 'g-recaptcha-response', 
	    		        value: token 
	    		    }
	    		);
	    		$(event.target).append(tokenInput);
			});     
        });       
       
   } );
    
})(jQuery);