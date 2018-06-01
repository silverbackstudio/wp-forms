(function($){

    window.dataLayer = window.dataLayer || [];

    $('.svbk-form').on('submit.svbk', function(e){

        var $form = $(this);
        var formData = $form.serialize();
        var $messages = $('.messages ul', $form);
        var submitUrl = $form.attr('action');

        dataLayer.push({
            'event': 'formSubmit', 
            'formAction': $form.data('formAction'), 
            'formElement': $form,
            
            //Reset Values
            'formResult': null, 
            'formResponse': null,
            'errorField': null, 
            'errorDescription': null
        });

        //reset
        $('.field-group', $form).removeClass('error');
        $('.field-group .field-errors', $form).text('');
        $form.removeClass('response-success response-error response-request-error');
        $messages.empty();

        $form.addClass('loading');

        formData += '&ajax=1';

        $.ajax(
        {
            dataType: "json",
            url: submitUrl,
            type: "POST",
            data: formData,
            success: function(response){
                $form.addClass('response-' + response.status);

                if(response.status === 'error'){

                    for(var field in response.errors){

                        var $field_group = $('.' + response.prefix + '-' + field + '-group', $form);

                        if( $field_group.length > 0 ) {
                            $field_group.addClass('error');
                            $('.field-errors', $field_group).text(response.errors[field]);
                        } else {
                            $messages.append('<li class="error">' + response.errors[field] + '</li>');
                        }
                        
                        dataLayer.push({
                            'event': 'formError',
                            'errorField': field, 
                            'errorDescription': response.errors[field]  
                        });
                        
                    }

                } else {
                    $messages.append('<li class="success">' + response.message + '</li>');
                    $form.trigger("reset");
                }
                
                $form.removeClass('loading');

                var submitTimeout = setTimeout(
                        function(){ 
                            if( response.redirect ){
                                window.location.href = response.redirect; 
                            }
                        }
                    , 3000);

                dataLayer.push({
                    'event': 'formSubmitted', 
                    'formResult': response.status, 
                    'formResponse': response,
                    'eventCallback' : function() {
                        clearTimeout(submitTimeout);

                        if ( response.redirect ){
                            window.location.href = response.redirect;
                        }
                    }                    
                });

            },
            error: function(response){
                $form.addClass('response-request-error');
                $messages.append('<li class="error">Request Error</li>');
                dataLayer.push({'event': 'formRequestError', 'errorDescription': response});

                $form.removeClass('loading');
            }
        }
        );
        e.preventDefault();
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

    $('.svbk-form .messages').on('click', '.close', function(){
        $(this).closest('.svbk-form').removeClass('response-success response-error');
    });

})(jQuery);
