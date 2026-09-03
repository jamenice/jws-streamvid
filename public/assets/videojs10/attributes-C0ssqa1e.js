//#region ../utils/dist/object/pick.js
/**
* Creates a new object with only the specified keys.
*
* @example
*   const obj = { a: 1, b: 2, c: 3 };
*   pick(obj, ['a', 'c']); // { a: 1, c: 3 }
*/
function pick(obj, keys) {
	const result = {};
	for (const key of keys) if (Object.hasOwn(obj, key)) result[key] = obj[key];
	return result;
}

//#endregion
//#region ../utils/dist/string/escape-html.js
function escapeHtml(str) {
	return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;").replace(/`/g, "&#96;");
}

//#endregion
//#region ../utils/dist/dom/attributes.js
/** Capture authored values for the selected attributes. */
function snapshotAttributes(element, names) {
	return [...names].map((name) => ({
		name,
		value: element.getAttribute(name)
	}));
}
/** Restore a snapshot created by `snapshotAttributes`. */
function restoreAttributes(element, snapshot) {
	for (const { name, value } of snapshot) if (value === null) element.removeAttribute(name);
	else element.setAttribute(name, value);
}
/** Convert a NamedNodeMap to a plain object. */
function namedNodeMapToObject(namedNodeMap) {
	const obj = {};
	for (const attr of namedNodeMap) obj[attr.name] = attr.value;
	return obj;
}
/** Helper function to serialize attributes into a string. */
function serializeAttributes(attrs) {
	let html = "";
	for (const key in attrs) {
		const value = attrs[key];
		if (value === "") html += ` ${key}`;
		else html += ` ${key}="${escapeHtml(value)}"`;
	}
	return html;
}

//#endregion
export { escapeHtml as a, snapshotAttributes as i, restoreAttributes as n, pick as o, serializeAttributes as r, namedNodeMapToObject as t };
//# sourceMappingURL=attributes-C0ssqa1e.js.map