document.addEventListener('DOMContentLoaded', function() {
    // Μηχανισμός Auto-Close για τα Accordions του Ιατρού
    const accordions = document.querySelectorAll('.vet-accordion');
    
    accordions.forEach(acc => {
        acc.addEventListener('click', function(e) {
            // Αν ανοίγει τώρα αυτό το accordion...
            if (!this.hasAttribute('open')) {
                // Κλείσε όλα τα υπόλοιπα
                accordions.forEach(otherAcc => {
                    if (otherAcc !== this) {
                        otherAcc.removeAttribute('open');
                    }
                });
            }
        });
    });
});