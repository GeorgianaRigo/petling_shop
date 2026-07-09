/* Petling Partners Promo - frontend JS
 * Χειρίζεται και τα δύο forms: claim (.ptl-promo-form) και redeem (.ptl-redeem-form).
 * Τα δεδομένα ajaxUrl / nonces έρχονται από το petlingPromo (wp_localize_script).
 */
jQuery(document).ready(function ($) {

    // ---- Claim form: ο πελάτης παίρνει κωδικό ----
    $('.ptl-promo-form').on('submit', function (e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('.ptl-btn-submit');
        var loader = form.find('.ptl-loader');
        var msgArea = form.find('.ptl-message-area');

        btn.hide();
        loader.show();
        msgArea.html('');

        var data = form.serialize() + '&action=petling_process_promo';

        $.post(petlingPromo.ajaxUrl, data, function (response) {
            loader.hide();
            if (response.success) {
                form.closest('.ptl-promo-inner').html(response.data);
            } else {
                btn.show();
                msgArea.html(response.data);
            }
        }).fail(function () {
            loader.hide();
            btn.show();
            msgArea.html('<div class="ptl-alert ptl-error">Σφάλμα διακομιστή. Δοκιμάστε ξανά.</div>');
        });
    });

    // ---- Redeem form: ο συνεργάτης επιβεβαιώνει τον κωδικό στο ραντεβού ----
    $('.ptl-redeem-form').on('submit', function (e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('.ptl-btn-submit');
        var loader = form.find('.ptl-loader');
        var msgArea = form.find('.ptl-message-area');

        btn.hide();
        loader.show();
        msgArea.html('');

        var data = form.serialize() + '&action=petling_process_redeem';

        $.post(petlingPromo.ajaxUrl, data, function (response) {
            loader.hide();
            btn.show();
            msgArea.html(response.data);
            // Καθαρίζουμε μόνο το πεδίο αναζήτησης ώστε να ελέγξει τον επόμενο κωδικό εύκολα,
            // κρατάμε το password ώστε να μη χρειάζεται να το ξαναγράφει συνέχεια.
            if (response.success) {
                form.find('input[name="redeem_lookup"]').val('').focus();
            }
        }).fail(function () {
            loader.hide();
            btn.show();
            msgArea.html('<div class="ptl-alert ptl-error">Σφάλμα διακομιστή. Δοκιμάστε ξανά.</div>');
        });
    });

});
