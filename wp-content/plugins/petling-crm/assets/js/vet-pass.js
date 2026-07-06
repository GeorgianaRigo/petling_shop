document.addEventListener('DOMContentLoaded', function() {
    
    // --- ΚΑΘΑΡΙΣΜΟΣ URL (Αφαίρεση του ?saved=1 για να μην ξαναβγεί στο refresh) ---
    if (window.history.replaceState) {
        const url = new URL(window.location.href);
        if (url.searchParams.has('saved')) {
            const toast = document.getElementById('petling-toast');
            if (toast) toast.style.display = 'block';
            url.searchParams.delete('saved');
            window.history.replaceState({path: url.href}, '', url.href);
        }
    }

    // --- Εμφάνιση Loader σε κάθε υποβολή φόρμας ---
    const forms = document.querySelectorAll('form:not(.no-loader)');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            if (this.checkValidity && !this.checkValidity()) return;
            const loader = document.getElementById('petling-global-loader');
            if(loader) loader.style.display = 'flex';
        });
    });

    // --- Μηχανισμός Auto-Close για τα Accordions του Ιατρού ---
    const accordions = document.querySelectorAll('.vet-accordion');
    accordions.forEach(acc => {
        acc.addEventListener('click', function(e) {
            if (!this.hasAttribute('open')) {
                accordions.forEach(otherAcc => {
                    if (otherAcc !== this) {
                        otherAcc.removeAttribute('open');
                    }
                });
            }
        });
    });
    
    // --- Εμφάνιση παλαιότερων βαρών (Vanilla JS) ---
    const showWeightsBtns = document.querySelectorAll('.btn-show-older-weights');
    showWeightsBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const wrapper = this.closest('.weight-history-wrapper');
            const hiddenGroups = wrapper.querySelectorAll('.weight-year-group[style*="display:none"]');
            const hiddenItems = wrapper.querySelectorAll('.weight-item-row[style*="display:none"]');
            
            hiddenGroups.forEach(el => el.style.display = 'block');
            hiddenItems.forEach(el => el.style.display = 'flex');
            
            this.parentElement.style.display = 'none';
        });
    });
    
});