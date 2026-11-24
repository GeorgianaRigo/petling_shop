var ptlCurrentPage = 1;
var ptlItemsPerPage = 3;

function ptlSwitchTab(tabName, btnEl) {
    document.querySelectorAll('.ptl-tab-pane').forEach(function (pane) { pane.classList.remove('active'); });
    document.querySelectorAll('.ptl-tab-btn').forEach(function (btn) { btn.classList.remove('active'); });
    document.getElementById('tab-' + tabName).classList.add('active');
    if (btnEl) btnEl.classList.add('active');
}

function ptlToggleCard(headerEl) {
    var card = headerEl.closest('.ptl-recipe-card');
    card.classList.toggle('open');
}

function ptlFilterRecipes() {
    ptlCurrentPage = 1;
    ptlRenderPagination();
}

function ptlRenderPagination() {
    var filterEl = document.getElementById('ptl-protein-filter');
    if (!filterEl) return;

    var selectedProtein = filterEl.value;
    var cards = Array.from(document.querySelectorAll('#ptl-recipes-list .ptl-recipe-card'));
    var filteredCards = cards.filter(function (card) {
        var prot = card.getAttribute('data-protein');
        if (selectedProtein === 'all' || prot === selectedProtein) { return true; }
        card.style.display = 'none';
        return false;
    });

    var totalPages = Math.ceil(filteredCards.length / ptlItemsPerPage) || 1;
    if (ptlCurrentPage > totalPages) ptlCurrentPage = totalPages;
    var start = (ptlCurrentPage - 1) * ptlItemsPerPage;
    var end = start + ptlItemsPerPage;

    filteredCards.forEach(function (card, idx) {
        card.style.display = (idx >= start && idx < end) ? 'block' : 'none';
    });

    var pagerContainer = document.getElementById('ptl-pager-controls');
    pagerContainer.innerHTML = '';

    if (totalPages > 1) {
        var prevBtn = document.createElement('button');
        prevBtn.innerHTML = '&larr;';
        prevBtn.disabled = (ptlCurrentPage === 1);
        prevBtn.onclick = function () { ptlCurrentPage--; ptlRenderPagination(); };
        pagerContainer.appendChild(prevBtn);

        for (var i = 1; i <= totalPages; i++) {
            (function (pageNum) {
                var btn = document.createElement('button');
                btn.innerText = pageNum;
                if (pageNum === ptlCurrentPage) btn.classList.add('active');
                btn.onclick = function () { ptlCurrentPage = pageNum; ptlRenderPagination(); };
                pagerContainer.appendChild(btn);
            })(i);
        }

        var nextBtn = document.createElement('button');
        nextBtn.innerHTML = '&rarr;';
        nextBtn.disabled = (ptlCurrentPage === totalPages);
        nextBtn.onclick = function () { ptlCurrentPage++; ptlRenderPagination(); };
        pagerContainer.appendChild(nextBtn);
    }
}

function ptlDownloadPDF(elementId) {
    var sourceElement = document.getElementById(elementId);
    if (!sourceElement) return;

    var iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    var doc = iframe.contentWindow.document;
    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Petling_Syntagi</title><style>@page { size: A4 portrait; margin: 15mm; } body { font-family: sans-serif; color: #333; margin: 0; padding: 0; } h2 { color: #43282F; margin: 0 0 5px 0; font-size: 20px; } h3 { color: #43282F; margin: 20px 0 10px 0; font-size: 16px; } ul { list-style: none; padding: 0; margin: 0 0 20px 0; } li { padding: 6px 0; border-bottom: 1px solid #eee; font-size: 14px; } img { max-width: 220px; height: auto; }</style></head><body>');
    doc.write(sourceElement.innerHTML);
    doc.write('</body></html>');
    doc.close();

    setTimeout(function () {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(function () { document.body.removeChild(iframe); }, 1000);
    }, 300);
}

document.addEventListener('DOMContentLoaded', function () {
    ptlRenderPagination();
});