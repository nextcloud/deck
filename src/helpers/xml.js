const xmlToJson = (xml) => {
	let obj = {}
	if (xml.nodeType === 1) {
		if (xml.attributes.length > 0) {
			obj['@attributes'] = {}
			for (let j = 0; j < xml.attributes.length; j++) {
				const attribute = xml.attributes.item(j)
				obj['@attributes'][attribute.nodeName] = attribute.nodeValue
			}
		}
	} else if (xml.nodeType === 3) {
		obj = xml.nodeValue
	}
	if (xml.hasChildNodes()) {
		for (let i = 0; i < xml.childNodes.length; i++) {
			const item = xml.childNodes.item(i)
			const nodeName = item.nodeName
			if (typeof (obj[nodeName]) === 'undefined') {
				obj[nodeName] = xmlToJson(item)
			} else {
				if (typeof obj[nodeName].push === 'undefined') {
					const old = obj[nodeName]
					obj[nodeName] = []
					obj[nodeName].push(old)
				}
				obj[nodeName].push(xmlToJson(item))
			}
		}
	}
	return obj
}
const parseXml = (xml) => {
	let dom = null
	try {
		dom = (new DOMParser()).parseFromString(xml, 'text/xml')
	} catch (e) {
		console.error('Failed to parse xml document', e)
	}
	return dom
}

const commentToObject = (tag) => {
	let mentions = tag['d:prop']['oc:mentions']['oc:mention'] ?? []
	if (mentions && !Array.isArray(mentions)) {
		mentions = [mentions]
	}

	return {
		cardId: tag['d:prop']['oc:objectId']['#text'],
		id: tag['d:prop']['oc:id']['#text'],
		actorId: tag['d:prop']['oc:actorId']['#text'],
		actorDisplayName: tag['d:prop']['oc:actorDisplayName']['#text'],
		creationDateTime: tag['d:prop']['oc:creationDateTime']['#text'],
		message: tag['d:prop']['oc:message']['#text'],
		isUnread: tag['d:prop']['oc:isUnread']['#text'] === 'true',
		mentions: mentions.map((mention) => {
			return {
				mentionType: mention['oc:mentionType']['#text'],
				mentionId: mention['oc:mentionId']['#text'],
				mentionDisplayName: mention['oc:mentionDisplayName']['#text'],
			}
		}),
	}
}

// FIXME: make this generic and not depending on comments
const xmlToTagList = (xml) => {

	const json = xmlToJson(parseXml(xml))
	const list = json['d:multistatus']['d:response']

	// no element
	if (list === undefined) {
		return []
	}
	const result = []

	// one element
	if (Array.isArray(list) === false) {
		result.push(commentToObject(list['d:propstat']))

	// two or more elements
	} else {
		for (const index in list) {
			if (list[index]['d:propstat']['d:status']['#text'] !== 'HTTP/1.1 200 OK') {
				continue
			}
			result.push(commentToObject(list[index]['d:propstat']))
		}
	}
	return result
}

export default xmlToTagList
