jQuery(document).ready(function($) {
    
    // Εμφάνιση Loader σε κάθε υποβολή φόρμας
    $('form').on('submit', function() {
        if (!$(this).hasClass('no-loader')) {
            $('#petling-global-loader').css('display', 'flex');
        }
    });
    
    // --- 1. UNSAVED CHANGES WARNING ---
    let formIsDirty = false;
    $('#petling-main-form').on('input change', 'input, select, textarea', function() {
        formIsDirty = true;
        if ($(this).attr('name') === 'petling_order_reminder_interval') {
            $('#reminder-save-warning').slideDown(200);
        }
    });
    $(window).on('beforeunload', function() {
        if (formIsDirty) { return "Έχετε μη αποθηκευμένες αλλαγές. Είστε σίγουροι ότι θέλετε να φύγετε;"; }
    });
    $('#petling-main-form').on('submit', function() { formIsDirty = false; });

    // --- 2. SMART FORM FILTERING (DOG/CAT) ---
    function filterPetData($block) {
        let type = $block.find('.pet-type-select').val(); 
        $block.find('.health-issue-item').each(function() {
            let animal = $(this).data('animal');
            if (animal === 'both' || animal === type) {
                $(this).show();
            } else {
                $(this).hide();
                $(this).find('input[type="checkbox"]').prop('checked', false);
            }
        });
    }
    $('.pet-block').each(function() { filterPetData($(this)); });
    $(document).on('change', '.pet-type-select', function() { filterPetData($(this).closest('.pet-block')); });

    // --- 3. TABS LOGIC ---
    $(document).on('click', '.pet-tab-btn', function() {
        $('.pet-tab-btn').removeClass('is-active');
        $(this).addClass('is-active');
        $('.pet-block').hide();
        $('#pet-block-' + $(this).data('tab')).show();
    });

    // --- 4. ΠΡΟΣΘΗΚΗ ΝΕΟΥ ΖΩΟΥ ---
    $('#add-pet-button').on('click', function(e) {
        e.preventDefault();
        const nextIndex = $('.pet-block').length; 
        let templateHTML = $('#pet-block-template').html();
        templateHTML = templateHTML.replace(/__INDEX__/g, nextIndex);
        const $newBlock = $('<div class="pet-block" id="pet-block-' + nextIndex + '"></div>');
        const $header = $('<div class="pet-block-header"><h4 class="pet-block-title">Νέο Ζώο 🐾</h4><button type="button" class="btn-petling btn-red remove-pet-button" formnovalidate>Αφαίρεση</button></div>');
        $newBlock.append($header).append(templateHTML);
        $('#pet-repeater-container').append($newBlock);
        filterPetData($newBlock);
        const $newTab = $('<button type="button" class="pet-tab-btn" data-tab="' + nextIndex + '">🐾 Νέο Ζώο</button>');
        $('.pet-tabs-nav').append($newTab);
        $newTab.click();
        $('#pet-block-' + nextIndex + ' .pet-name-input').on('input', function() {
            let name = $(this).val().trim();
            let type = $('#pet-block-' + nextIndex + ' .pet-type-select').val();
            let emoji = (type === 'cat') ? '🐱' : '🐶';
            if(name === '') name = 'Νέο Ζώο';
            $newTab.html(emoji + ' ' + name);
            $('#pet-block-' + nextIndex + ' .pet-block-title').text('Κατοικίδιο: ' + name);
        });
    });

    // --- 5. ΑΛΛΑΓΗ TAB EMOJI ---
    $(document).on('change', '.pet-type-select', function() {
        const $block = $(this).closest('.pet-block');
        const id = $block.attr('id');
        if (id) {
            const index = id.replace('pet-block-', '');
            const nameInput = $block.find('.pet-name-input').val().trim();
            const name = nameInput === '' ? 'Νέο Ζώο' : nameInput;
            const emoji = ($(this).val() === 'cat') ? '🐱' : '🐶';
            $('.pet-tab-btn[data-tab="' + index + '"]').html(emoji + ' ' + name);
        }
    });

    // --- 6. ΑΦΑΙΡΕΣΗ ΖΩΟΥ ---
    $(document).on('click', '.remove-pet-button', function() {
        if (confirm('Είστε σίγουροι ότι θέλετε να αφαιρέσετε αυτό το κατοικίδιο;')) {
            const $block = $(this).closest('.pet-block');
            const id = $block.attr('id');
            if (id) {
                const index = id.replace('pet-block-', '');
                $('.pet-tab-btn[data-tab="' + index + '"]').remove();
            }
            $block.remove();
            $('.pet-tab-btn').first().click();
            formIsDirty = true; 
        }
    });

    // --- 7. ACCORDIONS AUTO-CLOSE ---
    const accordions = document.querySelectorAll('.petling-accordion');
    accordions.forEach(acc => {
        acc.addEventListener('click', function(e) {
            if (!this.hasAttribute('open')) {
                accordions.forEach(otherAcc => {
                    if (otherAcc !== this) {
                        otherAcc.removeAttribute('open');
                    }
                });
            }
        });
    });

    // --- 8. SHOW OLDER WEIGHTS BUTTON ---
    $(document).on('click', '.btn-show-older-weights', function() {
        var wrapper = $(this).closest('.weight-history-wrapper');
        wrapper.find('.weight-year-group').slideDown(300);
        wrapper.find('.weight-item-row').slideDown(300);
        $(this).parent().slideUp(300);
    });

});