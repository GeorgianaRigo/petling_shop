jQuery(document).ready(function ($) {
    let nextPageInfo = null;

    function importBatch(pageInfo) {
        $('#progress').append('<p>Φόρτωση batch...</p>');

        $.post(shopifyImportAjax.ajax_url, {
            action: 'shopify_import_batch',
            nonce: shopifyImportAjax.nonce,
            page_info: pageInfo || '',
        }, function (response) {
            if (response.success) {
                const data = response.data;

                $('#progress').append('<p>Εισήχθησαν σε αυτό το batch: <strong>' + data.imported + '</strong> προϊόντα.</p>');

                if (data.errors && data.errors.length > 0) {
                    data.errors.forEach(err => {
                        $('#progress').append('<p style="color:red;">' + err + '</p>');
                    });
                }

                if (!data.done && data.next_page_info) {
                    nextPageInfo = data.next_page_info;
                    importBatch(nextPageInfo);
                } else {
                    $('#progress').append('<p><strong>✔️ Η εισαγωγή ολοκληρώθηκε!</strong></p>');
                    $('#start-import').prop('disabled', false);
                }
            } else {
                $('#progress').append('<p style="color:red;">Σφάλμα: ' + response.data.message + '</p>');
                $('#start-import').prop('disabled', false);
            }
        }).fail(function () {
            $('#progress').append('<p style="color:red;">Σφάλμα δικτύου.</p>');
            $('#start-import').prop('disabled', false);
        });
    }

    $('#start-import').on('click', function () {
        $(this).prop('disabled', true);
        $('#progress').empty().append('<p>Ξεκινάμε εισαγωγή προϊόντων...</p>');

        $.post(shopifyImportAjax.ajax_url, {
            action: 'shopify_import_start',
            nonce: shopifyImportAjax.nonce,
        }, function (response) {
            if (response.success) {
                const products = response.data.products || [];
                $('#progress').append('<p>Εισήχθησαν σε αυτό το batch: <strong>' + products.length + '</strong> προϊόντα.</p>');

                if (response.data.next_page_info) {
                    nextPageInfo = response.data.next_page_info;
                    importBatch(nextPageInfo);
                } else {
                    $('#progress').append('<p><strong>✔️ Η εισαγωγή ολοκληρώθηκε!</strong></p>');
                    $('#start-import').prop('disabled', false);
                }
            } else {
                $('#progress').append('<p style="color:red;">Σφάλμα εκκίνησης: ' + response.data.message + '</p>');
                $('#start-import').prop('disabled', false);
            }
        }).fail(function () {
            $('#progress').append('<p style="color:red;">Σφάλμα δικτύου κατά την έναρξη εισαγωγής.</p>');
            $('#start-import').prop('disabled', false);
        });
    });
});
