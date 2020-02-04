const rawToParsed = (text) => {
	text = text.replace(/<br>/g, '\n')
	text = text.replace(/&nbsp;/g, ' ')

	// Since we used innerHTML to get the content of the div.contenteditable
	// it is escaped. With this little trick from https://stackoverflow.com/a/7394787
	// We unescape the code again, so if you write `<strong>` we can display
	// it again instead of `&lt;strong&gt;`
	const temp = document.createElement('textarea')
	temp.innerHTML = text
	text = temp.value

	// Although the text is fully trimmed, at the very least the last
	// "\n" occurrence should be always removed, as browsers add a
	// "<br>" element as soon as some rich text is written in a content
	// editable div (for example, if a new line is added the div content
	// will be "<br><br>").
	return text.trim()
}

export {
	rawToParsed,
}
