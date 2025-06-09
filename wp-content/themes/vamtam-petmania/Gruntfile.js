/* jshint node:true */
module.exports = function(grunt) {
	'use strict';

	grunt.util.linefeed = '\n';

	grunt.util.linefeed = '\n';

	grunt.initConfig(require('./utils/grunt/init')(grunt));
	require('./utils/grunt/packaging')(grunt);

	require('matchdep').filterDev('grunt-*').forEach( grunt.loadNpmTasks );

	grunt.registerTask('buildjs', ['concat', 'uglify']);
	grunt.registerTask('dev', [ 'buildjs', 'less', 'parallel:dev']);
	grunt.registerTask('dev-live', [ 'buildjs', 'less', 'parallel:dev-live']);

	grunt.registerTask('post-sync', function() {
		var done = this.async();

		done();
	});

	// build process - related tasks go on the same row
	grunt.registerTask('package', [
		'jshint', 'buildjs',
		'check-api',
		'build-plugins',
		'parallel:composer',
		'clean:build', 'clean:dist',
		'makepot', 'add-textdomain',
		'copy:theme',

		// samples
		// 'scp-download-samples', // removed as it only downloads all-default.css
		'download-content-xml',
		'download-sidebars-options',
		// 'download-revslider',

		'download-json:the-events-calendar',
		'download-json:theme-mods',
		'download-json:elementor-settings',
		'download-json:woocommerce-settings',
		'download-json:wpc-smart-wishlist',
		'download-json:ajax-search-for-woocommerce',
		'download-json:woocommerce-ajax-filters',
		'download-json-serialized:woo-extra-product-options',
		// 'download-json:woocommerce-booking',
		'download-json:woocommerce-attributes',
		'download-json:instagram-feed',
		'download-elementor-styles',
		'download-elementor-global-defaults',
		'download-elementor-icons',

		// clean and compress
		'clean:post-copy',
		'replace:style-switcher',
		'compress:theme',
		'clean:build'
	]);
};
