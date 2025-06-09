module.exports = function(grunt) {
	// Project configuration.
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		makepot: {
			target: {
				options: {
					domainPath: '/languages/',
					mainFile: 'vamtam-importers.php',
					potFilename: 'vamtam-importers.pot',
					type: 'wp-plugin',
				}
			}
		},
	});

	require('matchdep').filterDev('grunt-*').forEach( grunt.loadNpmTasks );
};