jQuery(document).ready(function($) {
    if ($('body').hasClass('upload-php')) {
        $('.remove-sizes').on('click', function(event) {
            event.preventDefault();
            var imageId = $(this).data('id');
            var selectedSizes = [];
            var unselectedSizes = [];

            $(this).siblings('.imc-checkboxes').find('.size-checkbox:checked').each(function() {
                selectedSizes.push($(this).data('size'));
            });

            $(this).siblings('.imc-checkboxes').find('.size-checkbox:not(:checked)').each(function() {
                unselectedSizes.push($(this).data('size'));
            });

            var messageContainer = $(this).closest('.custom-actions').find('.imc-message');
            var actionsContainer = $(this).closest('.imc-actions');
            
            if (selectedSizes.length === 0) {
                messageContainer.text(String("No sizes selected."));
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'image_sizes_cleaner',
                    image_id: imageId,
                    sizes: selectedSizes,
                    nonce: imageSizesCleaner.nonce
                },
                success: function(response) {
                    var message = '';

                    if (response.success) {
                        var removedSizes = response.removedSizes;
                        if (unselectedSizes.length === 0) {
                            actionsContainer.remove();
                        } else {
                            removedSizes.forEach(function(size) {
                                actionsContainer.find('.size-checkbox[data-size="' + size + '"]').closest('div').remove();
                            });
                        }
                        message += '<span style="color: green;">' + String(response.success) + '</span><br>';
                    }
                    
                    console.log(response);

                    if (response.errors.length > 0) {
                        var errorMessage = response.errors.join('<br>');
                        message += '<span style="color: red;">' + String(errorMessage) + '</span>';
                    }

                    if (message === '') {
                        message = 'Unknown response format';
                    }

                    messageContainer.html(message);
                },
                error: function(_xhr, textStatus, errorThrown) {
                    console.log("wtf")
                    var errorMessage = 'AJAX request failed: ' + textStatus + ' (' + errorThrown + ')';
                    var errorElement = $('<div/>', { text: errorMessage });
                    messageContainer.html(errorElement);
                }
            });
        });
    }
});
