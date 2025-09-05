jQuery(document).ready(function ($) {
    console.log('Shopify Import JS loaded');

    let currentPage = 1;
    let totalProducts = 0;
    let processedCount = 0;
    const maxRetries = 3;

    function importBatch(page, retryCount = 0) {
        $('#progress').append('<p>📦 Φόρτωση batch ' + page + '...</p>');

        const forceRefresh = $('#force-refresh').is(':checked') ? 1 : 0;
        const url = `${shopifyImportAjax.rest_url}?page=${page}&per_page=50&force_refresh=${forceRefresh}`;

        $.get(url, function (response) {
            console.log('REST API response:', response);

            const products = response.products || [];

            if (page === 1) {
                totalProducts = response.total || products.length;
                $('#progress').append('<p>🔢 Σύνολο προϊόντων για επεξεργασία: <strong>' + totalProducts + '</strong></p>');
            }

            if (products.length === 0) {
                $('#progress').append('<p><strong>✔️ Η διαδικασία ολοκληρώθηκε!</strong> (' + processedCount + ' από ' + totalProducts + ')</p>');
                $('#start-import, #start-update').prop('disabled', false);
                return;
            }

            $.post(shopifyImportAjax.ajax_url, {
                action: 'shopify_import_batch',
                nonce: shopifyImportAjax.nonce,
                products: products,
            }, function (resp) {
                if (resp.success) {
                    processedCount += resp.data.imported;
                    $('#progress').append('<p>✅ Εισήχθησαν <strong>' + resp.data.imported + '</strong> προϊόντα. (Σύνολο: ' + processedCount + ' από ' + totalProducts + ')</p>');
                    if (resp.data.errors.length > 0) {
                        resp.data.errors.forEach(err => {
                            $('#progress').append('<p style="color:red;">' + err + '</p>');
                        });
                    }
                    if (response.has_more) {
                        setTimeout(() => importBatch(page + 1), 500);
                    } else {
                        $('#progress').append('<p><strong>🎉 Ολοκληρώθηκε: ' + processedCount + ' από ' + totalProducts + ' προϊόντα.</strong></p>');
                        $('#start-import, #start-update').prop('disabled', false);
                    }
                } else {
                    $('#progress').append('<p style="color:red;">Σφάλμα: ' + resp.data.message + '</p>');
                    $('#start-import, #start-update').prop('disabled', false);
                }
            });
        });
    }

    function updateBatch(page, retryCount = 0) {
        $('#progress').append('<p>📦 Φόρτωση batch (ενημέρωση) ' + page + '...</p>');

        const forceRefresh = $('#force-refresh').is(':checked') ? 1 : 0;
        
        // ---- ΚΡΙΣΙΜΗ ΑΛΛΑΓΗ ΓΙΑ ΤΟ UPDATE ----
        // Προσθέτουμε την παράμετρο context=update στο URL
        const url = `${shopifyImportAjax.rest_url}?page=${page}&per_page=50&force_refresh=${forceRefresh}&context=update`;

        $.get(url, function (response) {
            console.log('REST API response for update:', response);

            const products = response.products || [];

            if (page === 1) {
                totalProducts = response.total || products.length;
                $('#progress').append('<p>🔢 Σύνολο προϊόντων για ενημέρωση: <strong>' + totalProducts + '</strong></p>');
            }

            if (products.length === 0) {
                $('#progress').append('<p><strong>✔️ Η διαδικασία ενημέρωσης ολοκληρώθηκε!</strong> (' + processedCount + ' από ' + totalProducts + ')</p>');
                $('#start-import, #start-update').prop('disabled', false);
                return;
            }

            $.post(shopifyImportAjax.ajax_url, {
                action: 'shopify_update_batch',
                nonce: shopifyImportAjax.nonce,
                products: products,
            }, function (resp) {
                if (resp.success) {
                    processedCount += resp.data.updated;
                    $('#progress').append('<p>✅ Ενημερώθηκαν <strong>' + resp.data.updated + '</strong> προϊόντα. (Σύνολο: ' + processedCount + ' από ' + totalProducts + ')</p>');
                    if (resp.data.errors.length > 0) {
                        resp.data.errors.forEach(err => {
                            $('#progress').append('<p style="color:red;">' + err + '</p>');
                        });
                    }
                    if (response.has_more) {
                        setTimeout(() => updateBatch(page + 1), 500);
                    } else {
                        $('#progress').append('<p><strong>🎉 Ολοκληρώθηκε η ενημέρωση: ' + processedCount + ' από ' + totalProducts + ' προϊόντα.</strong></p>');
                        $('#start-import, #start-update').prop('disabled', false);
                    }
                } else {
                    $('#progress').append('<p style="color:red;">Σφάλμα: ' + resp.data.message + '</p>');
                    $('#start-import, #start-update').prop('disabled', false);
                }
            });
        });
    }

    $('#start-import').on('click', function () {
        $(this).prop('disabled', true);
        $('#start-update').prop('disabled', true);
        $('#progress').empty().append('<p>🚀 Ξεκινάμε εισαγωγή προϊόντων...</p>');
        currentPage = 1;
        processedCount = 0;
        importBatch(currentPage);
    });

    $('#start-update').on('click', function () {
        $(this).prop('disabled', true);
        $('#start-import').prop('disabled', true);
        $('#progress').empty().append('<p>🚀 Ξεκινάμε ενημέρωση προϊόντων...</p>');
        currentPage = 1;
        processedCount = 0;
        updateBatch(currentPage);
    });
});