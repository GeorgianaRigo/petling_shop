jQuery(document).ready(function ($) {
    console.log('Shopify Import JS loaded');
    let currentPage = 1;
    let totalProducts = 0;
    let importedCount = 0;
    let maxRetries = 3;

    function importBatch(page, retryCount = 0) {
        $('#progress').append('<p>📦 Φόρτωση batch ' + page + '...</p>');

        const url = `${shopifyImportAjax.rest_url}?page=${page}&per_page=150&force_refresh=1`;

        $.get(url, function (response) {
            console.log('REST API response:', response);

            const products = response.products || [];

            if (page === 1) {
                totalProducts = response.total || products.length;
                $('#progress').append('<p>🔢 Σύνολο προϊόντων για εισαγωγή: <strong>' + totalProducts + '</strong></p>');
            }

            if (products.length === 0) {
                $('#progress').append('<p><strong>✔️ Η εισαγωγή ολοκληρώθηκε!</strong> (' + importedCount + ' από ' + totalProducts + ')</p>');
                $('#start-import').prop('disabled', false);
                return;
            }

            $.post(shopifyImportAjax.ajax_url, {
                action: 'shopify_import_batch',
                nonce: shopifyImportAjax.nonce,
                products: products,
            }, function (resp) {
                if (resp.success) {
                    importedCount += resp.data.imported;

                    $('#progress').append('<p>✅ Εισήχθησαν <strong>' + resp.data.imported + '</strong> προϊόντα. (Σύνολο: ' + importedCount + ' από ' + totalProducts + ')</p>');

                    if (resp.data.errors.length > 0) {
                        resp.data.errors.forEach(err => {
                            $('#progress').append('<p style="color:red;">' + err + '</p>');
                        });
                    }

                    // Προσθήκη flag has_more που δεν υπήρχε - διορθώθηκε
                    if (response.has_more) {
                        setTimeout(() => importBatch(page + 1), 500);
                    } else {
                        $('#progress').append('<p><strong>🎉 Ολοκληρώθηκε: ' + importedCount + ' από ' + totalProducts + ' προϊόντα.</strong></p>');
                        $('#start-import').prop('disabled', false);
                    }
                } else {
                    $('#progress').append('<p style="color:red;">Σφάλμα: ' + resp.data.message + '</p>');
                    $('#start-import').prop('disabled', false);
                }
            }).fail(function (xhr) {
                if (xhr.status === 429 && retryCount < maxRetries) {
                    let wait = Math.pow(2, retryCount) * 1000;
                    $('#progress').append('<p style="color:orange;">⚠️ Rate limit hit, retry σε ' + (wait / 1000) + ' δευτερόλεπτα...</p>');
                    setTimeout(() => importBatch(page, retryCount + 1), wait);
                } else if (retryCount < maxRetries) {
                    let wait = Math.pow(2, retryCount) * 500;
                    $('#progress').append('<p style="color:orange;">⚠️ Σφάλμα δικτύου, retry σε ' + (wait / 1000) + ' δευτερόλεπτα...</p>');
                    setTimeout(() => importBatch(page, retryCount + 1), wait);
                } else {
                    $('#progress').append('<p style="color:red;">❌ Απέτυχε η εισαγωγή batch ' + page + ' μετά από ' + maxRetries + ' προσπάθειες.</p>');
                    $('#start-import').prop('disabled', false);
                }
            });

        }).fail(function (xhr, status, error) {
            console.error('REST API request failed:', status, error, xhr.responseText);
            $('#progress').append('<p style="color:red;">REST API error: ' + error + '</p>');

            if (xhr.status === 429 && retryCount < maxRetries) {
                let wait = Math.pow(2, retryCount) * 1000;
                $('#progress').append('<p style="color:orange;">⚠️ Rate limit hit στο REST API, retry σε ' + (wait / 1000) + ' δευτερόλεπτα...</p>');
                setTimeout(() => importBatch(page, retryCount + 1), wait);
            } else if (retryCount < maxRetries) {
                let wait = Math.pow(2, retryCount) * 500;
                $('#progress').append('<p style="color:orange;">⚠️ Σφάλμα REST API, retry σε ' + (wait / 1000) + ' δευτερόλεπτα...</p>');
                setTimeout(() => importBatch(page, retryCount + 1), wait);
            } else {
                $('#progress').append('<p style="color:red;">❌ Απέτυχε το REST API batch ' + page + ' μετά από ' + maxRetries + ' προσπάθειες.</p>');
                $('#start-import').prop('disabled', false);
            }
        });
    }

    $('#start-import').on('click', function () {
        $(this).prop('disabled', true);
        $('#progress').empty().append('<p>🚀 Ξεκινάμε εισαγωγή προϊόντων...</p>');
        currentPage = 1;
        importedCount = 0;
        importBatch(currentPage);
    });
});
