jQuery(document).ready(function($) {

    // Logic για Προσθήκη/Αφαίρεση
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

    // --- CSS ΓΙΑ 2 ΣΤΗΛΕΣ (100% INPUT) ---
    const customCSS = `
        .pet-block { 
            border: 1px solid #ddd; 
            padding: 25px; 
            margin-bottom: 30px; 
            background-color: #fff; 
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .pet-block h4 { 
            margin-top: 0; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .pet-block .remove-pet-button { 
            font-size: 12px; 
            background: #e62121; 
            color: white; 
            border: none; 
            padding: 5px 10px;
            border-radius: 4px;
        }

        /* --- ΣΤΗΛΕΣ & INPUTS --- */
        /* Ορίζουμε ρητά το πλάτος των στηλών για να είμαστε σίγουροι */
        .pet-block .form-row-first {
            width: 48% !important;
            float: left !important;
            margin-right: 4% !important;
            clear: both; /* Καθαρίζει την προηγούμενη γραμμή */
        }
        .pet-block .form-row-last {
            width: 48% !important;
            float: right !important;
            margin-right: 0 !important;
        }
        .pet-block .form-row-wide {
            width: 100% !important;
            clear: both;
            float: none;
        }
        
        /* ΤΟ ΣΗΜΑΝΤΙΚΟ: Τα κουτιά (inputs) πιάνουν το 100% της στήλης τους */
        .pet-block input[type="text"],
        .pet-block input[type="date"],
        .pet-block input[type="number"],
        .pet-block select,
        .pet-block textarea {
            width: 100% !important; /* Γεμίζει το 48% του γονέα του */
            border: 1px solid #C7B297 !important;
            border-radius: 5px !important;
            padding: 10px 15px !important;
            min-height: 45px;
            background-color: #fff;
            box-sizing: border-box !important; /* Για να μην βγαίνει έξω το padding */
        }

        /* Scroll Box Υγείας */
        .checkbox-wrapper { 
            max-height: 180px; 
            overflow-y: auto; 
            border: 1px solid #C7B297; 
            padding: 15px; 
            background-color: #fcfcfc; 
            border-radius: 5px; 
            width: 100%;
            box-sizing: border-box;
        }
        .checkbox-wrapper .checkbox-label { 
            display: flex; 
            align-items: center;
            margin-bottom: 8px; 
            width: 100%;
        }
        .checkbox-wrapper input[type="checkbox"] {
            margin-right: 10px;
            width: auto !important;
            min-height: auto;
        }

        #add-pet-button {
            width: 100%;
            padding: 15px;
            background-color: #C7B297;
            color: white;
            font-weight: bold;
        }

        /* Κινητό: Μία στήλη */
        @media (max-width: 767px) {
            .pet-block .form-row-first,
            .pet-block .form-row-last {
                width: 100% !important;
                float: none !important;
                margin-right: 0 !important;
            }
        }
    `;
    $('head').append('<style>' + customCSS + '</style>');
});