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

/*jslint node: true */
'use strict';

const gulp = require('gulp'),
    ngAnnotate = require('gulp-ng-annotate'),
    uglify = require('gulp-uglify'),
    jshint = require('gulp-jshint'),
    KarmaServer = require('karma').Server,
    phpunit = require('gulp-phpunit'),
    concat = require('gulp-concat'),
    sourcemaps = require('gulp-sourcemaps');

// Configuration
const buildTarget = 'app.min.js';
const phpunitConfig = __dirname + '/../phpunit.xml';
const karmaConfig = __dirname + '/karma.conf.js';
const destinationFolder = __dirname + '/build/';
const sources = [
    'app/App.js', 'app/Config.js', 'app/Run.js',
    'controller/**/*.js',
    'filter/**/*.js',
    'service/**/*.js',
    'gui/**/*.js',
    'plugin/**/*.js',
    'utility/**/*.js',
    'directive/**/*.js'
];
const testSources = ['tests/**/*.js'];
const phpSources = ['../**/*.php', '!../js/**', '!../vendor/**'];
const watchSources = sources.concat(testSources).concat(['*.js']);
const lintSources = watchSources;

// tasks
gulp.task('default', ['lint'], () => {
    return gulp.src(sources)
        .pipe(ngAnnotate())
        .pipe(sourcemaps.init())
        .pipe(concat(buildTarget))
        .pipe(uglify())
        .pipe(sourcemaps.write())
        .pipe(gulp.dest(destinationFolder));
});

gulp.task('lint', () => {
    return gulp.src(lintSources)
        .pipe(jshint())
        .pipe(jshint.reporter('default'))
        .pipe(jshint.reporter('fail'));
});

gulp.task('watch', () => {
    gulp.watch(watchSources, ['default']);
});

gulp.task('karma', (done) => {
    new KarmaServer({
        configFile: karmaConfig,
        singleRun: true
    }, done).start();
});

gulp.task('watch-karma', (done) => {
    new KarmaServer({
        configFile: karmaConfig,
        autoWatch: true
    }, done).start();
});

gulp.task('phpunit', () => {
    return gulp.src(phpSources)
        .pipe(phpunit('phpunit', {
            configurationFile: phpunitConfig
        }));
});

gulp.task('watch-phpunit', () => {
    gulp.watch(phpSources, ['phpunit']);
});
