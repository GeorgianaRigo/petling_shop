jQuery(document).ready(function($) {
    const $log =$('#bg-log');
    const $statusBar =$('#bg-status-bar');
    const $progressFill =$('#bg-progress-fill');
    const $progressText =$('#bg-progress-text');

    let totalProducts = 0;
    let currentOffset = 0;
    let currentMode = ''; 
    const batchLimit = 5;

    $('#bg-btn-import, #bg-btn-update').on('click', function() {
        currentMode = $(this).attr('id') === 'bg-btn-import' ? 'import' : 'update';
        $('.button').prop('disabled', true);$log.html(`🚀 Έναρξη ${currentMode === 'import' ? 'ΕΙΣΑΓΩΓΗΣ' : 'ΕΝΗΜΕΡΩΣΗΣ'}...<br>`);
        $statusBar.show();
        currentOffset = 0;
        
        $.post(bgVars.ajax_url, { action: 'bg_init_xml', nonce: bgVars.nonce }, function(res) {
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
            resetUi(); 
            return;
        }

        $.post(bgVars.ajax_url, {
            action: 'bg_process_batch',
            nonce: bgVars.nonce,
            offset: currentOffset,
            mode: currentMode
        }, function(res) {
            if (res.success) {
                currentOffset += batchLimit;
                let percent = Math.min(100, Math.round((currentOffset / totalProducts) * 100));
                $progressFill.css('width', percent + '\%');$progressText.text(`${percent}% - ${Math.min(currentOffset, totalProducts)}/${totalProducts}`);

                if (currentMode === 'import') {
                    $log.append(`📦 Νέα: +${res.data.created} (Προσπεράστηκαν ${res.data.skipped} υπάρχοντα)<br>`);
                } else {
                    $log.append(`🔄 Updates: +${res.data.updated} (Προσπεράστηκαν ${res.data.skipped} νέα)<br>`);
                }
                
                if (res.data.errors && res.data.errors.length > 0) {
                    res.data.errors.forEach(err => $log.append(`<span style="color:red;">⚠ ${err}</span><br>`));
                }
                
                doBatch();
            }
        }).fail(function() {
            // Αν πέσει η σύνδεση, δοκιμάζει ξανά μετά από 3 δευτερόλεπτα
            setTimeout(doBatch, 3000);
        });
    }

    function resetUi() { 
        $('.button').prop('disabled', false); 
    }
});