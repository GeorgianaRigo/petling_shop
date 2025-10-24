jQuery(document).ready(function($) {

    // Handles the "Add Pet" button and "Remove" button logic
    $('#add-pet-button').on('click', function() {
        let newPetBlock = $('#pet-block-template .pet-block').clone();
        let newIndex = $('#pet-repeater-container .pet-block').length;
        newPetBlock.find('input[type="checkbox"]').each(function() {
            let name = $(this).attr('name').replace('__INDEX__', newIndex);
            $(this).attr('name', name);
        });
        $('#pet-repeater-container').append(newPetBlock);
    });

    $('#pet-repeater-container').on('click', '.remove-pet-button', function() {
        if ( confirm('Είστε σίγουροι ότι θέλετε να αφαιρέσετε αυτό το κατοικίδιο;') ) {
            $(this).closest('.pet-block').remove();
        }
    });

    // Inject CSS for better styling and layout corrections.
    const customCSS = `
        /* General styling rules... */
        .pet-details-fieldset { margin-bottom: 20px; width: 100%; }
        .pet-fieldset-description { font-size: 0.9em; color: #666; margin-bottom: 25px; }
        .pet-block { border: 1px solid #e0e0e0; padding: 20px; margin-bottom: 20px; border-radius: 8px; background-color: #f9f9f9; margin-top: 20px; }
        .pet-block h4 { margin-top: 0; display: flex; justify-content: space-between; align-items: center; }
        .pet-block .remove-pet-button { font-size: 12px; padding: 4px 8px; background: #e62121; color: white; border: none; cursor: pointer; border-radius: 4px; line-height: 1.5; }
        #add-pet-button { margin-top: 10px; }
        
        /* --- THE ULTIMATE FIX FOR FONT-SIZE --- */
        /* This rule is extremely specific to override theme styles */
        body.woocommerce-account .woocommerce-MyAccount-content .pet-details-fieldset input.input-text,
        body.woocommerce-account .woocommerce-MyAccount-content .pet-details-fieldset select,
        body.woocommerce-account .woocommerce-MyAccount-content .pet-details-fieldset textarea {
            font-size: 16px !important; /* Adjust if needed */
            height: auto !important;
            padding: 10px 15px !important;
            line-height: 1.5 !important;
            box-sizing: border-box !important;
            width: 100% !important;
            border-color: #C7B297;
        }

        /* Scrollbar for health issues */
        .checkbox-wrapper { max-height: 160px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background-color: #fff; border-radius: 4px; }
        .checkbox-wrapper .checkbox-label { display: block; margin-bottom: 8px; font-weight: normal; }
        .checkbox-wrapper .checkbox-label input { margin-right: 8px; }

        /* Media Query for mobile devices */
        @media (max-width: 767px) {
            .pet-details-fieldset .form-row-first,
            .pet-details-fieldset .form-row-last {
                width: 100% !important;
                float: none !important;
                margin-right: 0 !important;
            }
        }
    `;
    $('head').append('<style>' + customCSS + '</style>');
});