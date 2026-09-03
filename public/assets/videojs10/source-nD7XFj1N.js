import { i as isNil } from "./predicate-DrcmolBs.js";
import { r as snakeCase, t as camelCase } from "./casing-Cu0fL85w.js";

//#region ../utils/dist/jwt/parse-jwt.js
/** Decode the payload of a JWT without verifying its signature, `undefined` for malformed tokens. */
function parseJwt(token) {
	const base64Url = (token ?? "").split(".")[1];
	if (!base64Url) return void 0;
	try {
		const base64 = base64Url.replace(/-/g, "+").replace(/_/g, "/");
		const json = decodeURIComponent(atob(base64).split("").map((char) => `%${`00${char.charCodeAt(0).toString(16)}`.slice(-2)}`).join(""));
		return JSON.parse(json);
	} catch {
		return;
	}
}

//#endregion
//#region ../media/dist/dev/dom/mux/source/source.js
/**
* The Mux source: playback identity, the params that modify it, and the URLs derived from both. Engine-neutral on
* purpose — every Mux Media needs it, and they don't share an engine. Nothing here may reach for a specific one
* (license-server derivation lives in `../drm.ts`, for the engines that license), because `@videojs/spf` imports this
* module for its own Mux Media.
*/
const MUX_VIDEO_DOMAIN = "mux.com";
/**
* Serialize params to a query string (`?a=1&b=2`), mapping camelCase keys to `snake_case` and skipping nullish values.
* A `token` replaces every other param — signed URLs bake all modifiers into the token itself.
*/
function createMuxQuery(params = {}) {
	const { token, ...rest } = params;
	if (token) return `?${new URLSearchParams({ token: String(token) })}`;
	const search = new URLSearchParams();
	for (const [key, value] of Object.entries(rest)) if (!isNil(value)) search.set(snakeCase(key), String(value));
	const query = search.toString();
	return query ? `?${query}` : "";
}
/** Build the Mux HLS stream URL for a source. */
function createMuxVideoURL(source) {
	if (!source?.playbackId) return void 0;
	const { playbackId, customDomain = MUX_VIDEO_DOMAIN, playback } = source;
	if (playback?.minResolution && playback?.maxResolution) {
		if (Number.parseInt(playback.maxResolution, 10) < Number.parseInt(playback.minResolution, 10)) console.warn(`[vjs-mux] minResolution (${playback.minResolution}) must be <= maxResolution (${playback.maxResolution})`);
	}
	return `https://stream.${customDomain}/${playbackId}.m3u8${createMuxQuery(playback)}`;
}
/**
* Parse a Mux stream URL (`https://stream.<domain>/<playback-id>.m3u8?...`) into a `MuxSourceBase`, mapping
* `snake_case` query params back to camelCase playback params. Returns `undefined` for non-Mux URLs.
*/
function parseMuxVideoURL(src) {
	if (!src) return void 0;
	let url;
	try {
		url = new URL(src);
	} catch {
		return;
	}
	const [, domain] = url.hostname.match(/^stream\.(.+)$/) ?? [];
	const [, playbackId] = url.pathname.match(/^\/([^/]+)\.m3u8$/) ?? [];
	if (!domain || !playbackId) return void 0;
	const source = { playbackId };
	if (domain !== "mux.com") source.customDomain = domain;
	const playback = {};
	for (const [key, value] of url.searchParams) playback[camelCase(key)] = key === "token" ? value : parseMuxParamValue(value);
	if (Object.keys(playback).length > 0) source.playback = playback;
	return source;
}
/**
* Coerce a query param string back to the boolean/number types declared on `MuxPlaybackParams`. Numbers only convert
* when the string round-trips exactly (so `1080p`, `007`, and JWTs stay strings).
*/
function parseMuxParamValue(value) {
	if (value === "true") return true;
	if (value === "false") return false;
	if (value !== "" && String(Number(value)) === value) return Number(value);
	return value;
}
/**
* Build the poster image URL a source describes. Read through `MuxMedia`'s `contentData`.
*
* @internal
*/
function createMuxPosterURL(source) {
	if (!source?.playbackId) return void 0;
	const { playbackId, customDomain = MUX_VIDEO_DOMAIN, poster, playback } = source;
	const { ext = "webp", token, ...query } = poster ?? {};
	if (token && parseJwt(token)?.aud !== "t") return void 0;
	if (!token && playback?.token) return void 0;
	return `https://image.${customDomain}/${playbackId}/thumbnail.${ext}${createMuxQuery({
		token,
		...query
	})}`;
}
/**
* Build the storyboard (thumbnail sprite) VTT URL a source describes. Read through `MuxMedia`'s `contentData`.
*
* @internal
*/
function createMuxStoryboardURL(source) {
	if (!source?.playbackId) return void 0;
	const { playbackId, customDomain = MUX_VIDEO_DOMAIN, storyboard, playback } = source;
	const { token, ...query } = storyboard ?? {};
	if (token && parseJwt(token)?.aud !== "s") return void 0;
	if (!token && playback?.token) return void 0;
	return `https://image.${customDomain}/${playbackId}/storyboard.vtt${createMuxQuery({
		token,
		format: "webp",
		...query
	})}`;
}

//#endregion
export { createMuxVideoURL as a, createMuxStoryboardURL as i, createMuxPosterURL as n, parseMuxVideoURL as o, createMuxQuery as r, parseJwt as s, MUX_VIDEO_DOMAIN as t };
//# sourceMappingURL=source-nD7XFj1N.js.map