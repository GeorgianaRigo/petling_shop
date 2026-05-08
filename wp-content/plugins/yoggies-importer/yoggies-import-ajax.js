jQuery(document).ready(function($) {
    const $log = $('#yg-log');
    const $statusBar = $('#yg-status-bar');
    const $progressFill = $('#yg-progress-fill');
    const $progressText = $('#yg-progress-text');

    let totalProducts = 0;
    let currentOffset = 0;
    let currentMode = ''; 
    const batchLimit = 5;

    $('#yg-btn-import, #yg-btn-update').on('click', function() {
        currentMode = $(this).attr('id') === 'yg-btn-import' ? 'import' : 'update';
        $('.button').prop('disabled', true);
        $log.html(`🚀 Έναρξη ${currentMode === 'import' ? 'ΕΙΣΑΓΩΓΗΣ' : 'ΕΝΗΜΕΡΩΣΗΣ'}...<br>`);
        $statusBar.show();
        currentOffset = 0;
        
        $.post(ygVars.ajax_url, { action: 'yg_init_xml', nonce: ygVars.nonce }, function(res) {
            if (res.success) {
                totalProducts = res.data.total;
                $log.append(`✅ XML έτοιμο. Σύνολο προϊόντων: ${totalProducts}.<br>`);
                doBatch();
            } else {
                $log.append(`❌ Σφάλμα: ${res.data.message}<br>`);
                resetUi();
            }
        });
    });

    function doBatch() {
        if (currentOffset >= totalProducts) {
            $log.append('<br>🎉 <b>Ολοκληρώθηκε!</b>');
            resetUi(); return;
        }

        $.post(ygVars.ajax_url, {
            action: 'yg_process_batch',
            nonce: ygVars.nonce,
            offset: currentOffset,
            mode: currentMode
        }, function(res) {
            if (res.success) {
                currentOffset += batchLimit;
                let percent = Math.round((currentOffset / totalProducts) * 100);
                $progressFill.css('width', percent + '%');
                $progressText.text(`${percent}% - ${currentOffset}/${totalProducts}`);

                if (currentMode === 'import') {
                    $log.append(`📦 Νέα: +${res.data.created} (Προσπεράστηκαν ${res.data.skipped} υπάρχοντα)<br>`);
                } else {
                    $log.append(`🔄 Updates: +${res.data.updated} (Προσπεράστηκαν ${res.data.skipped} νέα)<br>`);
                }
                doBatch();
            }
        }).fail(function() {
            setTimeout(doBatch, 3000);
        });
    }

    function resetUi() { $('.button').prop('disabled', false); }
});