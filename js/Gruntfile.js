/*
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *  
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *  
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *  
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *  
 */


module.exports = function(grunt) {
	'use strict';

	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-wrap');
	grunt.loadNpmTasks('grunt-karma');
	grunt.loadNpmTasks('grunt-phpunit');

	grunt.initConfig({

		meta: {
			pkg: grunt.file.readJSON('package.json'),
			version: '<%= meta.pkg.version %>',
			configJS: 'config/',
			buildJS: [
				'app/**/*.js',
				'controller/**/*.js',
				'filters/**/*.js',
				'directive/**/*.js',
				'service/**/*.js'
			],
			productionJS: 'public/',
			testsJS: '../tests/js/'
		},

		concat: {
			options: {
				stripBanners: true
			},
			dist: {
				src: ['<%= meta.buildJS %>'],
				dest: '<%= meta.productionJS %>app.js'
			}
		},

		wrap: {
			app: {
				src: ['<%= meta.productionJS %>app.js'],
				dest: '<%= meta.productionJS %>app.js',
				option: {
					wrapper: [
						'(function(angular, $, oc_requesttoken, undefined){\n\n\'use strict\';\n\n',
						'\n})(angular, jQuery, oc_requesttoken);'
					]
				}
			}
		},

		jshint: {
			files: [
				'Gruntfile.js',
				'<%= meta.buildJS %>**/*.js',
				'<%= meta.testsJS %>**/*.js'
			],
			options: {
				jshintrc: '.jshintrc',
				reporter: require('jshint-stylish')
			}
		},

		watch: {
			concat: {
				files: ['<%=meta.buildJS%>'],
				options: {
					livereload: true
				},
				tasks: ['build']
			}
		},

		phpunit: {
			classes: {
				dir: '../tests/unit'
			},
			options: {
				bootstrap: '../tests/bootstrap.php',
				colors: true
			}
		},

		karma: {
			unit: {
				configFile: '<%= meta.testsJS %>config/karma.js'
			},
			continuous: {
				configFile: '<%= meta.testsJS %>config/karma.js',
				browsers: ['Firefox'],
				singleRun: true,
				reporters: ['progress']
			}
		},
	});

	// make tasks available under simpler commands
	grunt.registerTask('build', ['jshint', 'concat', 'wrap']);
	grunt.registerTask('js-unit', ['karma:continuous']);

};
