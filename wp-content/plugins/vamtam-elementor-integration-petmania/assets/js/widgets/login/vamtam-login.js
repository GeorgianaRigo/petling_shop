class VamtamLoginHandler extends elementorModules.frontend.handlers.Base {
	getDefaultSettings() {
        return {
            selectors: {
                lostPass: '.elementor-lost-password',
                separator: '.elementor-login-separator',
                register: '.elementor-register',
				passInput: 'input[id="password"]',
				dialog: '.dialog-widget',
				dialogCloseIcon: '.dialog-close-button i.eicon-close',
            },
        };
    }

    getDefaultElements() {
        const selectors = this.getSettings( 'selectors' );
        return {
            $lostPass: this.$element.find( selectors.lostPass ),
            $separator: this.$element.find( selectors.separator ),
            $register: this.$element.find( selectors.register ),
            $passInput: this.$element.find( selectors.passInput ),
            $dialogCloseIcon: this.$element.closest( selectors.dialog ).find( selectors.dialogCloseIcon ),
        };
    }

	onInit() {
		elementorModules.frontend.handlers.Base.prototype.onInit.apply( this, arguments );

		// Only for popup login form.
		if ( this.$element.hasClass( 'vamtam-popup-login-form' ) ) {
			this.popupLoginForm();
		}
	}

	popupLoginForm() {
		const $lostPass      = this.elements.$lostPass,
			$separator       = this.elements.$separator,
			$register        = this.elements.$register,
			$passInput       = this.elements.$passInput,
			$dialogCloseIcon = this.elements.$dialogCloseIcon;

		// Earlier, so it can apply to logged-in state as well.
		if ( $dialogCloseIcon.length ) {
			// Change close btn icon.
			$dialogCloseIcon.addClass( 'vamtamtheme- vamtam-theme-close' );
		}

		if ( ! $lostPass.length || ! $register.length || ! $passInput.length ) {
			return;
		}

		// Hide separator.
		$separator.hide();

		// Move lost-pass el.
		$lostPass.insertAfter( $passInput );

		// Change register text.
		$register.before( `<span class="vamtam-account-text">${VamtamLoginStrings.account}</span>` );
		$register.text( VamtamLoginStrings.register );
	}
}

jQuery( window ).on( 'elementor/frontend/init', () => {
	if ( ! elementorFrontend.elementsHandler || ! elementorFrontend.elementsHandler.attachHandler ) {
		const addHandler = ( $element ) => {
			elementorFrontend.elementsHandler.addHandler( VamtamLoginHandler, {
				$element,
			} );
		};

		elementorFrontend.hooks.addAction( 'frontend/element_ready/login.default', addHandler, 100 );
	} else {
		elementorFrontend.elementsHandler.attachHandler( 'login', VamtamLoginHandler );
	}
} );
