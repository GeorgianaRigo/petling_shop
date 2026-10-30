jQuery(document).ready(function($) {
    const $log = $('#yg-log');
    const $statusBar = $('#yg-status-bar');
    const $progressFill = $('#yg-progress-fill');
    const $progressText = $('#yg-progress-text');

    let totalProducts = 0;
    let currentOffset = 0;
    let currentMode = ''; 
    const batchLimit = 5;

    // =========================================================
    // 1. ΥΠΑΡΧΩΝ ΜΗΧΑΝΙΣΜΟΣ (XML IMPORT - ΑΘΙΚΤΟΣ)
    // =========================================================
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
                let percent = Math.min(100, Math.round((currentOffset / totalProducts) * 100));
                $progressFill.css('width', percent + '%');
                $progressText.text(`${percent}% - ${Math.min(currentOffset, totalProducts)}/${totalProducts}`);

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

    // =========================================================
    // 2. ΝΕΟ - ΜΗΧΑΝΙΣΜΟΣ COPY-PASTE CSV (ΜΟΝΟ YOGGIES)
    // =========================================================
    $('#yg-btn-pasted-csv').on('click', function() {
        const csvText = $('#yg-csv-data').val().trim();
        if(!csvText) {
            alert('Παρακαλώ επικολλήστε τα δεδομένα CSV στο πεδίο.');
            return;
        }

        $('.button').prop('disabled', true);
        $('#yg-log').html(`🚀 Ανάγνωση δεδομένων από το πεδίο (Έξυπνο Fallback)...<br>`);
        $('#yg-status-bar').hide();
        
        $.post(ygVars.ajax_url, { action: 'yg_process_pasted_csv', nonce: ygVars.nonce, csv_text: csvText }, function(res) {
            if (res.success) {
                $('#yg-log').append(`✅ Ελέγχθηκαν <b>${res.data.total_checked}</b> προϊόντα Yoggies στο E-shop.<br>`);
                $('#yg-log').append(`🎉 <b style="color:green;">Ενημερώθηκαν/Αντιγράφηκαν επιτυχώς: ${res.data.updated} συσκευασίες!</b>`);
                $('#yg-csv-data').val(''); // Καθαρίζει το κουτί αν όλα πήγαν καλά
            } else {
                $('#yg-log').append(`❌ Σφάλμα: ${res.data.message}<br>`);
            }
            resetUi();
        }).fail(function() {
            $('#yg-log').append('<span style="color:red;">❌ Αποτυχία σύνδεσης. Δοκιμάστε με μικρότερο κομμάτι κειμένου.</span><br>');
            resetUi();
        });
    });

    function resetUi() { 
        $('.button').prop('disabled', false); 
    }
});