function ptlrNextStep(step) {
    jQuery('.ptl-inline-error').hide();
    jQuery('#ptl-duplicate-box').hide().empty();
    jQuery('#ptl-step4-body').show();
    jQuery('#rec-step-4 .ptl-btn-prev').html('&larr; Πίσω').show();
    jQuery('.ptl-btn-submit').show();
    jQuery('#rec-step-4 .ptl-quiz-nav').css('justify-content', 'space-between');
    jQuery('#ptl-btn-to-profile').remove();

    jQuery('#ptl-recipe-app .ptl-step').removeClass('active');
    jQuery('#ptl-recipe-app #rec-step-' + step).addClass('active');

    if (step === 4 || step === 'loading' || step === 'result') {
        jQuery('.ptl-paywall-banner').hide();
    } else {
        jQuery('.ptl-paywall-banner').show();
    }
}

function ptlrValidateStep1() {
    var n = jQuery('#rw_name').val();
    var w = jQuery('#rw_weight').val();
    if (!n) { alert('Παρακαλώ συμπληρώστε το όνομα του σκύλου.'); return; }
    if (!w || w <= 0) { alert('Παρακαλώ συμπληρώστε το βάρος του σκύλου.'); return; }
    ptlrNextStep(2);
}

function ptlrSmartNextStep(step) {
    var tier = jQuery('#ptl-recipe-app input[name="health_tier"]:checked').val();
    if (tier === 'mild' && !jQuery('select[name="health_condition_mild"]').val()) { alert('Επιλέξτε την ευαισθησία.'); return; }
    if (tier === 'clinical' && !jQuery('select[name="health_condition_clinical"]').val()) { alert('Επιλέξτε την κλινική πάθηση.'); return; }
    if (step === 3 && tier === 'clinical') { step = 4; }
    ptlrNextStep(step);
}

function ptlrSmartPrevStep(step) {
    var tier = jQuery('#ptl-recipe-app input[name="health_tier"]:checked').val();
    if (step === 3 && tier === 'clinical') { step = 2; }
    ptlrNextStep(step);
}

function ptlrValidateStep3() {
    var valid = true;

    jQuery('#ptl-recipe-app #rec-step-3 select').each(function () {
        var label = jQuery(this).prev('.ptl-section-label');
        if (!jQuery(this).val()) {
            jQuery(this).css({ 'border-color': '#d63638', 'background-color': '#fff3f3' });
            label.css('color', '#d63638');
            if (jQuery(this).next('.ptl-inline-error-msg').length === 0) {
                jQuery('<div class="ptl-inline-error-msg" style="color:#d63638; font-size:13px; font-weight:bold; margin-top:-5px; margin-bottom:10px;">⚠️ Απαραίτητο πεδίο</div>').insertAfter(jQuery(this));
            }
            valid = false;
        } else {
            jQuery(this).css({ 'border-color': '#ddd', 'background-color': '#fff' });
            label.css('color', '#43282F');
            jQuery(this).next('.ptl-inline-error-msg').remove();
        }
    });

    var vegCount = jQuery('#ptl-veg-group input[type="checkbox"]:checked').length;
    if (vegCount !== 2) {
        jQuery('#veg-error').show();
        jQuery('#ptl-veg-group').prev('.ptl-section-label').css('color', '#d63638');
        valid = false;
    } else {
        jQuery('#veg-error').hide();
        jQuery('#ptl-veg-group').prev('.ptl-section-label').css('color', '#43282F');
    }

    if (!valid) {
        jQuery('#ptl-step3-error').html('⚠️ <strong>Δεν έχετε επιλέξει όλα τα υλικά!</strong><br>Ελέγξτε τα πεδία με κόκκινο χρώμα παραπάνω.').show();
        var firstError = jQuery('#ptl-recipe-app #rec-step-3 .ptl-section-label').filter(function () {
            return jQuery(this).css('color') === 'rgb(214, 54, 56)';
        }).first();
        if (firstError.length) {
            window.scrollTo({ top: firstError.offset().top - 60, behavior: 'smooth' });
        }
        return false;
    }
    jQuery('#ptl-step3-error').hide();
    ptlrNextStep(4);
}

jQuery(document).ready(function ($) {
    function ptlCheckPremium() {
        var hasPremium = false;
        $('#ptl-recipe-app #rec-step-3 select').each(function () {
            var val = $(this).val();
            if (val && val.indexOf('🔒') !== -1) hasPremium = true;
        });
        $('#ptl-veg-group input[type="checkbox"]:checked').each(function () {
            var val = $(this).val();
            if (val && val.indexOf('🔒') !== -1) hasPremium = true;
        });

        if (hasPremium) { $('#ptl-premium-warning').slideDown(); }
        else { $('#ptl-premium-warning').slideUp(); }

        var btnText = (ptlRecipeMaker.limitReached || hasPremium) ? 'Ολοκλήρωση & Πληρωμή' : 'Αποθήκευση (Δωρεάν)';
        $('.ptl-btn-submit').text(btnText);
    }

    $('#ptl-saved-pet-selector').on('change', function () {
        var val = $(this).val();
        if (val) {
            var data = JSON.parse(val);
            if (data.id) $('#rw_id').val(data.id);
            if (data.nm) $('#rw_name').val(data.nm);
            if (data.w) $('#rw_weight').val(data.w);
            if (data.st) $('#rw_stage').val(data.st);
            if (data.nt) $('#rw_neutered').val(data.nt);
            if (data.act) $('#rw_activity').val(data.act);
        } else {
            $('#rw_id, #rw_name, #rw_weight').val('');
        }
    });

    $('#ptl-recipe-form').on('keypress', function (e) {
        if (e.keyCode === 13) { e.preventDefault(); return false; }
    });

    $('#ptl-recipe-app input[name="health_tier"]').on('change', function () {
        var val = $(this).val();
        if (val === 'mild') {
            $('#ptl-health-mild-box').slideDown(); $('#ptl-health-clinical-box').slideUp(); $('#legacy_health_issue').val('no');
        } else if (val === 'clinical') {
            $('#ptl-health-mild-box').slideUp(); $('#ptl-health-clinical-box').slideDown(); $('#legacy_health_issue').val('yes');
        } else {
            $('#ptl-health-mild-box, #ptl-health-clinical-box').slideUp(); $('#legacy_health_issue').val('no');
        }
    });

    $('select[name="health_condition_mild"], select[name="health_condition_clinical"]').on('change', function () {
        $('#legacy_health_condition').val($(this).val());
    });

    $('#ptl-recipe-app #rec-step-3 select').on('change', function () {
        if ($(this).val()) {
            $(this).css({ 'border-color': '#ddd', 'background-color': '#fff' });
            $(this).prev('.ptl-section-label').css('color', '#43282F');
            $(this).next('.ptl-inline-error-msg').remove();
        }
        ptlCheckPremium();
    });

    $('#ptl-veg-group input[type="checkbox"]').on('change', function () {
        var checkedCount = $('#ptl-veg-group input[type="checkbox"]:checked').length;
        if (checkedCount >= 2) {
            $('#ptl-veg-group input[type="checkbox"]:not(:checked)').prop('disabled', true).next('label').addClass('disabled');
            $('#ptl-veg-hint').slideDown();
        } else {
            $('#ptl-veg-group input[type="checkbox"]').prop('disabled', false).next('label').removeClass('disabled');
            $('#ptl-veg-hint').slideUp();
        }
        if (checkedCount === 2) {
            $('#veg-error').hide();
            $('#ptl-veg-group').prev('.ptl-section-label').css('color', '#43282F');
        }
        ptlCheckPremium();
    });

    $('#ptl-recipe-form').on('submit', function (e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $('#ptl-recipe-app .ptl-step').removeClass('active');
        $('#rec-step-loading').addClass('active');

        var ajaxCall = $.post(ptlRecipeMaker.ajaxUrl, formData + '&action=ptl_process_recipe');
        var progress = 0;
        var progressInterval = setInterval(function () {
            progress += 20;
            if (progress <= 100) { $('#ptl-loading-progress').css('width', progress + '%'); }
        }, 800);

        setTimeout(function () {
            clearInterval(progressInterval);
            $('#ptl-loading-progress').css('width', '100%');

            ajaxCall.done(function (response) {
                if (response.success) {
                    if (response.data && response.data.redirect) {
                        $('#loading-title').html('Μεταφορά στο Ταμείο... 🛒');
                        $('#loading-desc').html('Η συνταγή σας είναι έτοιμη! Μεταφορά για πληρωμή...');
                        window.location.href = response.data.redirect;
                    } else {
                        $('#rec-step-loading').removeClass('active');
                        $('#rec-step-result').addClass('active');
                        $('#ptl-recipe-content').html(response.data);
                    }
                } else {
                    if (typeof response.data === 'object' && response.data.is_duplicate) {
                        $('#rec-step-loading').removeClass('active');
                        $('#rec-step-4').addClass('active');
                        $('#ptl-general-error').hide();
                        $('#ptl-step4-body').hide();

                        var dupCard = '<div style="text-align:center; padding:10px 5px 5px;">'
                            + '<div style="font-size:42px; margin-bottom:10px;">📖</div>'
                            + '<h3 style="margin:0 0 10px 0; color:#43282F; font-size:21px;">Αυτή η συνταγή υπάρχει ήδη!</h3>'
                            + '<p style="margin:0; color:#555; font-size:15px; line-height:1.5;">' + response.data.message + '</p>'
                            + '</div>';
                        $('#ptl-duplicate-box').html(dupCard).show();

                        $('#rec-step-4 .ptl-btn-submit').hide();
                        $('#rec-step-4 .ptl-btn-prev').html('&larr; Αλλαγή Υλικών');
                        if ($('#ptl-btn-to-profile').length === 0) {
                            $('#rec-step-4 .ptl-quiz-nav').append('<a href="' + response.data.dashboard_url + '" id="ptl-btn-to-profile" class="ptl-btn-submit" style="text-decoration:none; text-align:center; background:#43282F; padding:12px 24px; font-size:15px;">Δες τη Συνταγή σου &rarr;</a>');
                        }
                    } else {
                        $('#rec-step-loading').removeClass('active');
                        $('#rec-step-3').addClass('active');
                        var errMsg = typeof response.data === 'string' ? response.data : (response.data.message || 'Σφάλμα');
                        $('#ptl-step3-error').html('⚠️ ' + errMsg).show();
                    }
                    window.scrollTo({ top: $('#ptl-recipe-app').offset().top - 40, behavior: 'smooth' });
                }
            }).fail(function (jqXHR) {
                $('#rec-step-loading').removeClass('active');
                $('#rec-step-4').addClass('active');
                var errMsg = (jqXHR.status === 403) ? 'Η συνεδρία σας έληξε. Παρακαλώ ανανεώστε τη σελίδα.' : 'Υπήρξε σφάλμα σύνδεσης. Δοκιμάστε να ανανεώσετε τη σελίδα (Refresh).';
                $('#ptl-general-error').html(errMsg).show();
                window.scrollTo({ top: $('#ptl-recipe-app').offset().top - 40, behavior: 'smooth' });
            });
        }, 4000);
    });
});