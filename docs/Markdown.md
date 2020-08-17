## What is Markdown

The [wikipedia markdown entry](https://en.wikipedia.org/wiki/Markdown) introduced markdown as :

> Markdown is a lightweight markup language with plain text formatting syntax. It is designed so that it can be converted to HTML and many other formats using a tool by the same name. Markdown is often used to format readme files, for writing messages in online discussion forums, and to create rich text using a plain text editor. As the initial description of Markdown contained ambiguities and unanswered questions, many implementations and extensions of Markdown appeared over the years to answer these issues.

## Markdown in Deck
The Deck application plugin uses the [markdown-it](https://github.com/markdown-it/markdown-it) script to offer support for markdown in the cards description field.

## Supported Markdown

Markdown comes in may flavors. The best way to learn markdown and understand how to use it, is simply to [try it](https://markdown-it.github.io) on the original script official playground.
That same link offers also a comprehensive list of what is supported, and what is not - rendering it unnecessary to duplicate that content in here.  

[CommonMark Markdown Reference](http://commonmark.org/help/)

## Note about checklists

It is possible to create checklists in Deck by writing it in Markdown, using the following syntax:
```md
- [ ] This is a not checked item
- [x] This is a checked item
```
Then, the items can be checked and unchecked by clicking on the rendered checkbox.
Also, a summary of the completed items will be visible at the bottom of the card element.

## Known Issues

As per [issue #127](https://github.com/nextcloud/deck/issues/127) Due to a known limitation of the current script to support markdown, Links that contain the `")"` character will not display well, or will break.
The recommended solution is to use `"<"` and `">"` to wrap those links. It should assure their integrity.
If you come by another case of broken link, or broken display of links, please report it by opening a new issue.
