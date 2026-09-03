//#region ../utils/dist/function/try-call.js
/**
* Run a function that is expected to throw when the thing it targets is gone, ignoring the failure.
*
* Prefer `tryCatch` when the error should be handled or the result is needed; this is for fire-and-forget calls into an
* API that can be torn down underneath the caller, such as an embed inside an iframe.
*
* @example
*   ```ts
*   tryCall(() => player.destroy()); // Never throws
*   ```;
*/
function tryCall(fn) {
	try {
		fn();
	} catch {}
}

//#endregion
//#region ../media/dist/dev/dom/utils/embed-params.js
/**
* Serialize embed options into a query string. Booleans become `1`/`0`, which is how both the YouTube and Vimeo embeds
* spell them, and nullish values are dropped so an unset option is absent rather than the string `"undefined"`.
*/
function serializeEmbedParams(props) {
	const params = new URLSearchParams();
	for (const key in props) {
		const val = props[key];
		if (val === true || val === "") params.set(key, "1");
		else if (val === false) params.set(key, "0");
		else if (val != null) params.set(key, String(val));
	}
	return params.toString();
}

//#endregion
export { tryCall as n, serializeEmbedParams as t };
//# sourceMappingURL=embed-params-DGT4D-c6.js.map