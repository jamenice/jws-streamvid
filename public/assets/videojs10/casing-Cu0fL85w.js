//#region ../utils/dist/string/casing.js
function pascalCase(str) {
	return str.replace(/[-_](.)/g, (_, c) => c.toUpperCase()).replace(/^(.)/, (_, c) => c.toUpperCase());
}
function camelCase(str) {
	return pascalCase(str).replace(/^(.)/, (_, c) => c.toLowerCase());
}
function kebabCase(str) {
	return str.replace(/[A-Z]/g, (m) => `-${m.toLowerCase()}`);
}
function snakeCase(str) {
	return str.replace(/[A-Z]/g, (m) => `_${m.toLowerCase()}`);
}

//#endregion
export { kebabCase as n, snakeCase as r, camelCase as t };
//# sourceMappingURL=casing-Cu0fL85w.js.map