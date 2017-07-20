del     = require 'del'
gulp    = require 'gulp'
coffee  = require 'gulp-coffee'
gutil   = require 'gulp-util'
{spawn} = require 'child_process'

# compile `index.coffee`
gulp.task 'coffee', ->
  gulp.src('index.coffee')
    .pipe(coffee bare: true)
    .pipe(gulp.dest './')

# remove `index.js` and `coverage` dir
gulp.task 'clean', (cb) ->
  del ['dist', 'coverage', 'temp'], cb

# run tests
gulp.task 'test', ['coffee'], ->
  spawn 'npm', ['test'], stdio: 'inherit'

# run `md` for testing purposes
gulp.task 'md', ->
  markdownIt = require './index.coffee'
  gulp.src('./{,test/,test/fixtures/}*.md')
    .pipe(markdownIt())
    .pipe(gulp.dest './temp')

# start workflow
gulp.task 'default', ['coffee'], ->
  gulp.watch ['./{,test/,test/fixtures/}*.coffee'], ['test']
