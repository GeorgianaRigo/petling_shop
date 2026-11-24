function ptlnNextStep(step) {
    jQuery('#ptl-nutrition-app .ptl-step').removeClass('active');
    jQuery('#ptl-nutrition-app #ptl-step-' + step).addClass('active');

    var appEl = jQuery('#ptl-nutrition-app');
    if (appEl.length) {
        setTimeout(function () {
            var appEl = jQuery('#ptl-recipe-app');
            if (appEl.length) {
                jQuery('html, body').stop().animate({
                    scrollTop: Math.max(0, appEl.offset().top - 80)
                }, 300);
            }
        }, 50);
    }
}

function ptlnSmartNextStep(step) {
    var health = jQuery('#ptl-nutrition-app input[name="health_issue"]:checked').val();
    if (step === 2 && health === 'yes') { step = 3; }
    ptlnNextStep(step);
}

function ptlnSmartPrevStep(step) {
    var health = jQuery('#ptl-nutrition-app input[name="health_issue"]:checked').val();
    if (step === 2 && health === 'yes') { step = 1; }
    ptlnNextStep(step);
}

function ptlnCheckDataBeforeStep3() {
    var weight = jQuery('#ptl-nutrition-app input[name="current_weight"]').val();
    if (!weight || weight <= 0) { alert('Παρακαλώ εισάγετε σωστό βάρος.'); return; }
    ptlnNextStep(3);
}

jQuery(document).ready(function ($) {
    $('#ptl-nutrition-form').on('submit', function (e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $('#ptl-nutrition-app .ptl-step').removeClass('active');
        $('#ptl-quiz-loader').show();

        $.post(ptlNutritionQuiz.ajaxUrl, formData + '&action=ptl_process_nutrition', function (response) {
            $('#ptl-quiz-loader').hide();
            $('#ptl-nutrition-app #ptl-step-result').addClass('active');
            if (response.success) {
                $('#ptl-result-content').html(response.data);
            } else {
                $('#ptl-result-content').html('<p style="color:red;">' + response.data + '</p>');
            }
        }).fail(function () {
            $('#ptl-quiz-loader').hide();
            $('#ptl-nutrition-app #ptl-step-result').addClass('active');
            $('#ptl-result-content').html('<p style="color:red;">Σφάλμα σύνδεσης.</p>');
        });
    });
});