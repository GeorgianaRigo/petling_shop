// Contains logic related to elementor.
( function( $, undefined ) {
	"use strict";

	$( function() {

		var VAMTAM_ELEMENTOR = {
			init: function () {
				this.removeGrowScaleAnims.init();
			},
			// Removes grow-scale anims (select options) for all widgets except image.
			removeGrowScaleAnims: {
				init: function () {
					let selectedWidget = '';

					function removeImageAnims() {
						[ '', '_tablet', '_mobile' ].forEach( device => {
							const optGroupSelector = `#elementor-panel select[data-setting="_animation${device}"] optgroup[label="Vamtam"], #elementor-panel select[data-setting="animation${device}"] optgroup[label="Vamtam"]`,
								$animsVamtamOptGroup = $( optGroupSelector ),
								$imageGrowScaleAnims = $animsVamtamOptGroup.find( 'option[value*="imageGrowWithScale"' );

							// Remove the options.
							$.each( $imageGrowScaleAnims, function ( i, opt ) {
								$( opt ).remove();
							} );

							// If Vamtam optgroup is empty, remove it.
							if( $animsVamtamOptGroup.children(':visible').length == 0 ) {
								$animsVamtamOptGroup.remove();
							}
						} );
					}

					// Widgets.
					elementor.hooks.addAction( 'panel/open_editor/widget', function( panel, model, view ) {
						// Update selected widget.
						selectedWidget = model.elType || model.attributes.widgetType;
					} );
					// Columns.
					elementor.hooks.addAction( 'panel/open_editor/column', function( panel, model, view ) {
						// Update selected widget.
						selectedWidget = model.elType || model.attributes.elType;
					} );
					// Sections.
					elementor.hooks.addAction( 'panel/open_editor/section', function( panel, model, view ) {
						// Update selected widget.
						selectedWidget = model.elType || model.attributes.elType;
					} );

					const docClickHandler = ( e ) => {
						// We dont remove for Image widget.
						if ( selectedWidget === 'image' ) {
							return;
						}
						// Advanced Tab.
						if ( ! $( 'body' ).hasClass( 'e-route-panel-editor-advanced' ) ) {
							return;
						}
						// Isnide Motion Effects section.
						if ( e.target.closest( '.elementor-control-section_effects' ) ) {
							setTimeout( () => {
								removeImageAnims();
							}, 10 );
						}
					};

					const panel = document.getElementById( 'elementor-panel' );
					panel.addEventListener( 'click', docClickHandler, { passive: true, capture: true } ); // we need capture phase here.
				}
			},
		}

		$( window ).on( 'load', function() {
			VAMTAM_ELEMENTOR.init();
		} );
	});
})( jQuery );
