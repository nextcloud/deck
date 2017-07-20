# markdown-it-link-target

> Link target plugin for [markdown-it](https://github.com/markdown-it/markdown-it) markdown parser.

## Install

node.js, browser:

```bash
npm install markdown-it-link-target --save
bower install markdown-it-link-target --save
```

## Use

```js
var md = require('markdown-it')()
var milt = require('markdown-it-link-target')
```

```js
// Basic Use
md.use(milt)

var html = md.render('![link](https://google.com)')
html // <p><a href="https://google.com" target="_blank">link</a></p>
```

```js
// With Custom Configuration
md.use(milt, {
  target: '_top',
})

var html = md.render('![link](https://google.com)')
html // <p><a href="https://google.com" target="_top">link</a></p>
```

_Differences in browser._ If you load script directly into the page, without a package system, the module will add itself globally as `window.markdownitLinkTarget`.


## License

[MIT](https://github.com/markdown-it/markdown-it-footnote/blob/master/LICENSE)
