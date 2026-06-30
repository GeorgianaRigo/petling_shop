jQuery(document).ready(function($) {
    // Προσθήκη νέου ζώου
    $('#add-pet-button').on('click', function() {
        let newPetBlock = $('#pet-block-template .pet-block').clone();
        let newIndex = $('#pet-repeater-container .pet-block').length;
        
        // Αντικατάσταση του __INDEX__ με τον πραγματικό αριθμό (για τα checkboxes)
        newPetBlock.find('input[type="checkbox"]').each(function() {
            let name = $(this).attr('name').replace('__INDEX__', newIndex);
            $(this).attr('name', name);
        });
        
        $('#pet-repeater-container').append(newPetBlock);
    });

    // Αφαίρεση ζώου
    $('#pet-repeater-container').on('click', '.remove-pet-button', function() {
        if ( confirm('Είστε σίγουροι ότι θέλετε να αφαιρέσετε αυτό το κατοικίδιο;') ) {
            $(this).closest('.pet-block').remove();
        }
    });
});