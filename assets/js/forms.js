(function($){

    window.dataLayer = window.dataLayer || [];

    $('.svbk-form').on('submit.svbk', function(e){

        var $form = $(this);
        var data = $form.serialize();
        var $messages = $('.messages ul', $form);
        var formTitle = $form.siblings('.form-title').text();
        var formAction = $form.attr('action');
        var formArray = $form.serializeArray();
        
        var dataObject = {};
        
        for (var i = 0; i < formArray.length; i++){
            dataObject[formArray[i]['name']] = formArray[i]['value'];
        }

        dataLayer.push({'event': 'formSubmit', 'formAction': formAction, 'formData' : dataObject });

        //reset
        $('.field-group', $form).removeClass('error');
        $('.field-group .field-errors', $form).text('');
        $form.removeClass('response-success response-error response-request-error');
        $messages.empty();

        $form.addClass('loading');

        data += '&ajax=1';

        $.ajax(
        {
            dataType: "json",
            url: formAction,
            type: "POST",
            data: data,
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
                        
                        dataLayer.push({'event': 'formError', 'errorField': field, 'errorDescription': response.errors[field], 'formAction': formAction, 'formData' : dataObject });
                        
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
                    'formAction': formAction, 
                    'formData' : dataObject,
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
                dataLayer.push({'event': 'formRequestError', 'errorDescription': response, 'formAction': formAction, 'formData' : dataObject});

                $form.removeClass('loading');
            }
        }
        );
        e.preventDefault();
    });

    $('.policy-flags-open').on('click', function(e){
        dataLayer.push({'event': 'formEvent',  'formEvent': 'policyOpen'});
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
