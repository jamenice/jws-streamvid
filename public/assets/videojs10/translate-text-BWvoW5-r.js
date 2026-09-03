//#region ../core/dist/dev/core/i18n/resolve-text.js
function resolveText(text) {
	return typeof text === "string" ? text : text.text;
}

//#endregion
//#region ../core/dist/dev/core/i18n/utils/interpolate.js
const PLACEHOLDER = /\{([^{}]+)\}/g;
function interpolate(template, params) {
	if (!params) return template;
	return template.replace(PLACEHOLDER, (match, name) => {
		return Object.hasOwn(params, name) ? String(params[name]) : match;
	});
}

//#endregion
//#region ../core/dist/dev/core/i18n/translate-text.js
function translateText(text, translatorOrParams, params) {
	if (typeof text === "string") return text;
	if (typeof translatorOrParams === "function") return translatorOrParams(text, params);
	return interpolate(resolveText(text), translatorOrParams ?? params);
}

//#endregion
export { interpolate as n, resolveText as r, translateText as t };
//# sourceMappingURL=translate-text-BWvoW5-r.js.map