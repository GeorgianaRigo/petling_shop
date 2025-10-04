jQuery(document).ready(function($) {
    const $menuItems = $('li.vamtam-menu-click-on-hover'); 
    const $targetSection = $('.vamtam-header-mega-menuvamtam-header-mega-menu'); 
    
    let menuTimer;

    if (!$targetSection.length) {
        console.log('Mega menu target not found.');
        return;
    }

    $menuItems.on('mouseenter', function() {
        clearTimeout(menuTimer);
        $targetSection.addClass('hover-active');
    });

    $menuItems.on('mouseleave', function() {
        menuTimer = setTimeout(function() {
            $targetSection.removeClass('hover-active');
        }, 200);
    });
    
    $targetSection.on('mouseenter', function() {
        clearTimeout(menuTimer);
    });

    $targetSection.on('mouseleave', function() {
        menuTimer = setTimeout(function() {
            $targetSection.removeClass('hover-active');
        }, 200);
    });
});