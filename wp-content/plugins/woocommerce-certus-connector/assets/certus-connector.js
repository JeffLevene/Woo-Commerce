;jQuery(function( $ ) {

	$("#certus_connector_settings_form").on('submit', function( event ) {

		var form = $(this), inputs = form.find("input, select, button, textarea"), 
                data = form.serialize();
                
                var email_address = $('#certus_email_address', form).val();
                var token = $('#certus_api_token', form).val();
                
                event.preventDefault();
                
                if( email_address == '' || token == '' ) {
                    var msg = 'Email Address or API Token cannot be empty.';
                    show_message( '', msg );
                } else {

                    $('#certus_connector_progress_label').removeClass('certus_connector_progressbar_label');
                    $('#certus_connector_progressbar').addClass('certus_connector_progressbar');
                    $('#certus_connector_progress_label').text('Saving Settings...');

                    $("#certus_connector_progressbar").show();
                    inputs.prop("disabled", true);
                    
                    request = $.ajax({
                        url: ajaxurl + '?action=certus_connector_save',
                        type: "post",
                        data: data
                    });
                    
                    request.done(function ( response ){

                        response = JSON.parse(response);

                        if( response.status == "OK" ){
                            
                            var total_order_count = remaining_order_count = response.order_count;
                            var params = Array();
                            var per_orders_sent = 0;
                            var all_done = false; // Flag for handling all done message

                            // to hide div showing messages.
                            $("#certus_configure_message").hide();
                            $("#certus_message").hide();

                            var remaining_order_count = response.order_count;

                            if ( remaining_order_count > 0 ) {
                                $('#certus_connector_progress_label').text('Loading Past Orders...');
                                setTimeout(function() { send_data(remaining_order_count, params); }, 2000);
                            } else {
                                show_data_loaded_msg( 'Settings Saved! No Past orders to send to Certus.' );
                            }

                            function send_data( remaining_order_count, params ) {
                                
                                if ( remaining_order_count == response.order_count ) {
                                    $('#certus_connector_progress_label').empty();
                                }
                                
                                $('#certus_connector_progress_label').addClass('certus_connector_progressbar_label');
                                
                                    send_batch_request = $.ajax({
                                        url: ajaxurl + '?action=certus_connector_send_batch',
                                        type: "post",
                                        async: 'false',
                                        data: {
                                            'params': params                           
                                        }
                                    });

                                send_batch_request.done(function ( send_batch_response, textStatus, jqXHR ){

                                    send_batch_response = JSON.parse( send_batch_response );
                                    
                                    if( send_batch_response.status == 'OK' ){

                                        params = send_batch_response.results;
                                        remaining_order_count = remaining_order_count - send_batch_response.sent_count;

                                        if ( total_order_count != remaining_order_count ) {
                                            per_orders_sent = Math.round(( total_order_count - remaining_order_count ) / total_order_count * 100);
                                            progress( per_orders_sent, $('#certus_connector_progressbar') );
                                        }

                                        if (remaining_order_count > 0) {
                                            send_data(remaining_order_count,JSON.stringify(params));
                                        } else {
                                            all_done = true;
                                        }

                                    } else if ( send_batch_response.status == 'ALL_DONE' ) {
                                        all_done = true;
                                    } else {

                                        var status = send_batch_response.results.woocommerce.status;
                                        var msg = send_batch_response.results.woocommerce.message;
                                        setTimeout( function() { show_message( status, msg ); }, 1500 );
                                    }

                                    if ( all_done === true ) {

                                        per_orders_sent = 100;
                                        progress( per_orders_sent, $('#certus_connector_progressbar') );
                                        show_data_loaded_msg('Past orders were sent to Certus successfully.');    
                                        
                                    }

                                });
                            }


                        } else {

                            // Show error message if credential were not vaidated.
                            $("#certus_connector_progressbar").fadeOut(100);
                            var status = response.status;
                            var msg = response.message;
                            show_message( status, msg );
                        }


                    });
                
                    request.fail(function (jqXHR, textStatus, errorThrown){
                        console.error(
                            "The following error occured: "+
                            textStatus, errorThrown
                        );
                    });

                    request.always(function () {
                        inputs.prop("disabled", false);
                    });
                    
                }
		
                

                function show_message( status, msg ){
                    $("#certus_message").show();
                    if( status ){
                        $("#certus_message p").text( status + ':' + msg );
                    } else {
                        $("#certus_message p").text( msg );
                    }

                }
                
                function progress(percent, $element) {
                    
                    var progressBarWidth = percent * $element.width() / 100;
                    $element.find('div').css({ width: percent + '%' }).html(percent + "%&nbsp;");

                }
                
                function show_data_loaded_msg( msg ){
                    
                    var img_url = certus_params.image_url + 'green-tick.png';
                    
                    setTimeout( function() {
                        
                            $('#certus_connector_progress_label').removeClass('certus_connector_progressbar_label');
                            $('#certus_connector_progressbar').removeClass('certus_connector_progressbar');
                            
                            
                            $('#certus_connector_progress_label').html('<img src="' + img_url + '"alt="Complete" height="16" width="16" class="alignleft"><h3>'+ msg +'</h3><p>New orders will sync automatically.</p>');
                        }, 300 );
                }
                
	});
} );