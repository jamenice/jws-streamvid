//#region ../utils/dist/predicate/predicate.js
function isString(value) {
	return typeof value === "string";
}
function isNumber(value) {
	return typeof value === "number";
}
function isBoolean(value) {
	return typeof value === "boolean";
}
function isFunction(value) {
	return typeof value === "function";
}
function isNull(value) {
	return value === null;
}
function isUndefined(value) {
	return typeof value === "undefined";
}
function isNil(value) {
	return value == null;
}
/** Check if a value is an object, excluding null. */
function isObject(value) {
	return value !== null && typeof value === "object";
}
/**
* Check if a value is an object carrying a callable method for every given name.
*
* Recognizes a foreign object by the shape a caller needs from it, without importing the library that defines it or
* testing against its class.
*/
function hasMethods(value, methods) {
	if (!isObject(value)) return false;
	return methods.every((method) => isFunction(value[method]));
}
/** Check if a value is a plain object (not a class instance like Date, Map, etc). */
function isPlainObject(value) {
	if (!isObject(value)) return false;
	const proto = Object.getPrototypeOf(value);
	return proto === null || proto === Object.prototype;
}

//#endregion
export { isNull as a, isPlainObject as c, isNil as i, isString as l, isBoolean as n, isNumber as o, isFunction as r, isObject as s, hasMethods as t, isUndefined as u };
//# sourceMappingURL=predicate-DrcmolBs.js.map