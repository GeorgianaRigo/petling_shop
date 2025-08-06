jQuery(document).ready(function ($) {
    let currentPage = 1;
    let totalProducts = 0;
    let importedCount = 0;

    function importBatch(page) {
        $('#progress').append('<p>📦 Φόρτωση batch ' + page + '...</p>');

        const url = `${shopifyImportAjax.rest_url}?page=${page}&per_page=50`;

        $.get(url, function (response) {
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

                    if (response.has_more) {
                        importBatch(page + 1);
                    } else {
                        $('#progress').append('<p><strong>🎉 Ολοκληρώθηκε: ' + importedCount + ' από ' + totalProducts + ' προϊόντα.</strong></p>');
                        $('#start-import').prop('disabled', false);
                    }
                } else {
                    $('#progress').append('<p style="color:red;">Σφάλμα: ' + resp.data.message + '</p>');
                    $('#start-import').prop('disabled', false);
                }
            }).fail(function () {
                $('#progress').append('<p style="color:red;">❌ Σφάλμα δικτύου.</p>');
                $('#start-import').prop('disabled', false);
            });

        }).fail(function () {
            $('#progress').append('<p style="color:red;">❌ Σφάλμα REST API (404 ή άλλη αποτυχία).</p>');
            $('#start-import').prop('disabled', false);
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
