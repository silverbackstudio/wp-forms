/* global grecaptcha */
/* global reCAPTCHA */

(function($){
    
    $.widget( "silverback.svbkForm", {
     
        // Default options.
        options: {
            action: '',
            trackFallbackTimeout: 3000,
            messagesContainer: '.messages',
            
            submitTimeout: null,
            messages: null,
            response: {},
            isLoading: false
        },     
     
        _create: function() {
            
            var messagesClose = 'click ' + this.options.messagesContainer + ' .close';
            
            this._on( {
                'submit': this.submit,
                'click .form-messages__close': this.closeMessages
            });
            
            if( ! this.options.action && this.element.data('formAction') ) {
                this.options.action = this.element.data('formAction');
            }
            
            this.options.messages = $(this.options.messagesContainer + ' ul', this.element);
        },
        
        submit : function( e ){
            
            if ( this.options.isLoading ) {
                this.addError( 'Your have another request loading, please wait' );
                e.preventDefault();
                return;
            }
            
            var formData = this.element.serialize();

            this._track({
                'event': 'formSubmit', 
                'formAction': this.options.action, 
                'formElement': this.element,
                
                //Reset Values
                'formResult': null, 
                'formResponse': null,
                'errorField': null, 
                'errorDescription': null
            });
    
            this.reset(false);
    
            formData += '&ajax=1';
    
            $.ajax(
            {
                context: this,
                dataType: "json",
                url: this.element.attr('action'),
                type: "POST",
                data: formData,
                beforeSend: this.setLoading,
                success: this._formSuccess,
                error: this._formRequestError,
                complete: this.unsetLoading
            });
            
            e.preventDefault();
            
        },
        
        _formSuccess: function(response){
           
            this.options.response = response;
           
            this.element.addClass('response-' + response.status);

            if ( response.status === 'error' ){

                for(var field in response.errors){

                    this.addError( response.errors[field], response.prefix + '-' + field );
                    
                    this._track({
                        'event': 'formError',
                        'errorField': field, 
                        'errorDescription': response.errors[field]  
                    });
                    
                }

                this._trigger( "formError", null, { 
                    'response' : response 
                } );

            } else {
    
                this.addMessage( response.message, 'success' );
                this.element.trigger("reset");
                
                this.options.submitTimeout = setTimeout( 
                    this.afterSuccess, 
                    this.options.trackFallbackTimeout
                );                
                
            }

            var self = this;

            this._track({
                'event': 'formSubmitted', 
                'formResult': response.status, 
                'formResponse': response,
                'eventCallback' : function(){
                    self.afterSuccess()
                }                   
            });

        },
        
       _formRequestError: function( response ){
           
            this.options.response = response;

            this.element.addClass('response-request-error');

            this._trigger( "formRequestError", null, { 
                'response' : response 
            } );
            
            this.addMessage('Request Error', 'error' );
            
            this._track({
                'event': 'formRequestError', 
                'errorDescription': response
            });
            
        },
        
        reset : function( fields = true ){
            
            this.options.response = {};
            
            $('.field-group', this.element).removeClass('error');
            $('.field-group .field-errors', this.element).text('');
            
            this.element.removeClass('response-success response-error response-request-error');
            this.options.messages.empty();
            
            this._trigger( 'reset', null, { instance: this } );
        },
        
        addMessage : function( message , type ) {
            $('<li>')
                .addClass( type +  ' form-messages__message form-messages__message--' + type   )
                .text(message)
                .appendTo(this.options.messages);
        },
        
        closeMessages: function(){
            this.element.removeClass('response-success response-error response-request-error');
            this.options.messages.empty();
        },
        
        addError: function( error, fieldClass ) {
            var $field_group = $( '.' + fieldClass + '-group', this.element );
            
            if( $field_group.length > 0 ) {
                $field_group.addClass('error');
                $( '.field-errors', $field_group ).text(error);
            } else {
                this.addMessage(error, 'error');
            }
        },
        
        _track : function( data ){
            
            window.dataLayer = window.dataLayer || [];            
            
            dataLayer.push(data);
        },     

        setLoading: function(){
            this.options.isLoading =  true;
            this.element.addClass('loading');
        },
        
        unsetLoading: function(){
            this.options.isLoading = false;
            this.element.removeClass('loading');
        },        

        afterSuccess: function( ) {
            
            if( this.options.submitTimeout ) {
                clearTimeout(this.options.submitTimeout);
            }

            if ( this.options.response && this.options.response.redirect ){
                window.location.href = this.options.response.redirect;
            }
        },
        
    });

    $('.policy-flags-open').on('click', function(e){
        e.preventDefault();
        $( $(this).attr('href') ).slideToggle();
    });
    
    $('.policy-flags-all input').on('change', function(e){
    
        var group = $(this).closest('.policy-agreements').find('.policy-flags input');
    
        if( $(this).is(':checked') ){
            group.prop('checked', true);
        } else {
            group.prop('checked', false);
        }
    
    });

    
}( jQuery ));
