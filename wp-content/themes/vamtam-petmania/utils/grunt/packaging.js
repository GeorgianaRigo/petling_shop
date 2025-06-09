/* jshint node:true */
module.exports = function(grunt) {
	'use strict';

	var path = require('path');

	var basedir = path.dirname(grunt.file.findup('Gruntfile.js'));
	var theme_name = grunt.file.readJSON(path.join(basedir, 'package.json')).name;
	var builddir = path.join(basedir, 'build', theme_name);

	var secrets_path = grunt.file.findup('secrets.json');
	var secrets = secrets_path ? require( secrets_path ) : {
		livepath: '',
		ssh_host: '',
		export_api_url: '',
		export_api_key: ''
	};

	function exportApiCall(action, callback) {
		var http = require('https');
		var url = secrets.export_api_url + secrets.export_api_key + '/' + action;

		grunt.log.writeln( url );

		http.get(url, function(res) {
			var body = '';

			res.on('data', function(chunk) {
				body += chunk;
			});

			res.on('end', function() {
				var response = body;

				if ( res.headers['content-type'].match( /json/ ) ) {

					// Actions that shouldn't JSON.parse() the response.
					const noParseActions = [
						'elementor-settings',
					];

					if ( noParseActions.includes( action ) ) {
						let validJSON;
						try {
							/*
								Check that we have valid json response but not assign
								to response as JSON.parse() re-orders numeric keys
								and some actions need to ensure the key order mirrors the server's.
							*/
							validJSON = JSON.parse(body.trim());
						} catch(e) {}

						if ( 'error' in validJSON ) {
							return callback( validJSON.error );
						}
					} else {
						try {
							response = JSON.parse(body.trim());
						} catch(e) {}

						if ( 'error' in response ) {
							return callback( response.error );
						}
					}
				}

				callback(null, response);
			});
		}).on('error', function(err) {
			callback(err);
		});
	}

	grunt.registerTask('download-revslider', function() {
		var done = this.async();

		exportApiCall('revslider', function(err, res) {
			if(err) return done(grunt.util.error("API error:"+err));

			if ( res.length === 0 ) {
				done( grunt.util.error( 'No sliders found, possibly something went wrong.' ) );

				console.error( res );

				return;
			}

			var exec = require('child_process').exec;

			var localdir = path.join(builddir, 'samples/revslider/');
			grunt.file.mkdir(localdir);

			var ri = -1;

			var next = function() {
				if(++ri >= res.length)
					return done();

				grunt.log.writeln('Downloading '+res[ri]);

				var url = secrets.export_api_url + secrets.export_api_key + '/revslider-single/' + res[ri];

				var curl = "curl -o '" + path.join(localdir, res[ri] + '.zip') + "' '" + url + "'";

				exec(curl, function(error) {
					if(error) return done(grunt.util.error(error));

					next();
				});
			};

			next();
		});
	});

	grunt.registerTask('download-elementor-styles', function() {
		var done = this.async();

		exportApiCall( 'elementor-styles-fallback', function(err, res) {
			if ( err ) {
				done( grunt.util.error("API error: " + err ) );
				return;
			}

			var css_path = path.join(builddir, 'samples/elementor-styles-fallback.css');

			res.css = res.css.replace( /(.*-line-height.*)em;/g, '$1;' );

			grunt.file.write( css_path, res.css );

			var gfonts_path = path.join(builddir, 'samples/elementor-styles-fallback-fonts.php');

			grunt.file.write( gfonts_path, res.fonts );

			done();
		});
	});

	grunt.registerTask('download-elementor-global-defaults', function() {
		var done = this.async();

		exportApiCall( 'elementor-global-defaults', function(err, res) {
			if ( err ) {
				done( grunt.util.error("API error: " + err ) );
				return;
			}

			var export_path = path.join(builddir, 'samples/elementor-global-defaults.php');

			grunt.file.write( export_path, res.exported );

			done();
		});
	});

	grunt.registerTask('download-json', function( name ) {
		var done = this.async();

		exportApiCall( name, function(err, res) {
			if ( err ) {
				done( grunt.util.error("API error: " + err ) );
				return;
			}

			var exec = require('child_process').exec;

			var localpath = path.join(builddir, 'samples/', name + '.json');

			if ( typeof res !== 'string' ) {
				res = JSON.stringify( res );
			}

			grunt.file.write( localpath, res );

			done();
		});
	});

	grunt.registerTask('download-json-serialized', function( name ) {
		var done = this.async();

		exportApiCall( name, function(err, res) {
			if ( err ) {
				done( grunt.util.error("API error: " + err ) );
				return;
			}

			var exec = require('child_process').exec;

			var localpath = path.join(builddir, 'samples/', name + '.ser.json');

			if ( typeof res !== 'string' ) {
				res = JSON.stringify( res );
			}

			grunt.file.write( localpath, res );

			done();
		});
	});

	grunt.registerTask('check-api', function() {
		var done = this.async();

		exportApiCall('api-version', function(err, res) {
			if(err) return done(false);
			if(!('version' in res) || res.version < grunt.config('pkg').vamtamApi)
				return done(grunt.util.error("Old Export API. Please update the plugin to version " + grunt.config('pkg').vamtamApi));

			done();
		});
	});

	grunt.registerTask('download-sidebars-options', function() {
		var done = this.async();

		var parts = [
			['default-options-beaver', 'default-options.php', 'options'],
		], pi = -1;

		var next = function() {
			if(++pi >= parts.length)
				return done();

			grunt.log.writeln('Downloading '+parts[pi][1]);

			exportApiCall(parts[pi][0], function(err, res) {
				if(err) return done(false);

				if ( typeof res === 'string' ) {
					res = JSON.parse( res );
				}

				grunt.file.write(path.join(builddir, "samples", parts[pi][1]), res[parts[pi][2]].replace(/(\r\n|\r|\n)/g, "\n"));
				next();
			});
		};

		next();
	});

	grunt.registerTask('download-content-xml', function() {
		var done = this.async();

		exportApiCall('content.xml-beaver', function(err, res) {
			if(err) return done(grunt.util.error("API error:"+err));

			console.log(res);

			var exec = require('child_process').exec;
			var curl = "curl --fail-with-body -o "+path.join(builddir, 'samples', 'content.xml')+" "+res.download_url;

			exec(curl, function(err) {
				if(err) return done(grunt.util.error(err));

				grunt.log.writeln("saved content.xml");
				done();
			});
		});
	});

	grunt.registerTask('download-elementor-icons', function() {
		var done = this.async();

		exportApiCall('elementor-icons', function(err, res) {
			if(err) return done(grunt.util.error("API error:"+err));

			console.log(res);

			var exec = require('child_process').exec;
			var curl = "curl --fail-with-body -o "+path.join(builddir, 'samples', 'theme-icons.zip')+" "+res.download_url;

			exec(curl, function(err) {
				if(err) return done(grunt.util.error(err));

				grunt.log.writeln("saved theme-icons.zip");
				done();
			});
		});
	});

	grunt.registerMultiTask('add-textdomain', function() {
		var files = grunt.file.expand(this.data);

		for(var fi in files) {
			grunt.file.write(files[fi],
				grunt.file.read(files[fi])
					.replace( /,\s*(['"])(vamtam|wpv)\1/g, ", '"+theme_name+"'")
					.replace( /(load_theme_textdomain|is_textdomain_loaded)\(\s*'(vamtam|wpv)'/g, "$1( '"+theme_name+"'")
			);
		}
	});

	grunt.registerTask('build-plugins', function() {
		grunt.task.run( 'makepot:elements' );

		var done = this.async();
		var prefix = 'vamtam/plugins/';

		var plugins = grunt.file.expand([
			prefix + '*',
			'!'+ prefix + '*.*',
		]);

		plugins.forEach(function(dirname) {
			var plugin_name = dirname.replace(prefix, '');

			grunt.config.set('compress.plugin-'+plugin_name, {
				options: {
					archive: prefix + plugin_name + '.zip',
					mode: 'zip',
					pretty: true,
					level: 9,
				},
				files: [{
					expand: true,
					src: [
						plugin_name + '/**/*',
						'!' + plugin_name + '/node_modules/**',
					],
					cwd: 'vamtam/plugins/'
				}]
			});

			grunt.task.run('compress:plugin-'+plugin_name);
		});

		done();
	});
};
