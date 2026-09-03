import { t as shallowEqual } from "./shallow-equal-C7S8rj2f.js";
import { t as HlsJsMedia } from "./media-dVBchfvA.js";
import { a as createMuxVideoURL, i as createMuxStoryboardURL, n as createMuxPosterURL, o as parseMuxVideoURL, r as createMuxQuery, s as parseJwt, t as MUX_VIDEO_DOMAIN } from "./source-nD7XFj1N.js";

//#region ../media/dist/dev/dom/mux/drm.js
/**
* Build the license servers a source describes, keyed by EME key system id — `source.drm` as any other source would
* name it. Mux signs one license token per playback ID and serves every system from a URL derived from it, so
* `drm.token` is all a caller provides.
*
* Returns `undefined` when no license token is present, or when the token is not scoped to DRM — an unsigned license
* request is always rejected, so there is nothing useful to configure.
*
* Separate from `./source` because it is the one part of the Mux source an engine that licenses differently — or, like
* SPF today, doesn't license at all — has nothing to do with.
*
* @internal
*/
function createMuxDrmSystems(source) {
	if (!source?.playbackId) return void 0;
	const { playbackId, customDomain = MUX_VIDEO_DOMAIN, drm } = source;
	const { token } = drm ?? {};
	if (!token || parseJwt(token)?.aud !== "d") return void 0;
	const query = createMuxQuery({ token });
	const url = (path) => `https://license.${customDomain}/${path}/${playbackId}${query}`;
	return {
		"com.apple.fps": {
			licenseUrl: url("license/fairplay"),
			serverCertificateUrl: url("appcert/fairplay")
		},
		"com.widevine.alpha": { licenseUrl: url("license/widevine") },
		"com.microsoft.playready": { licenseUrl: url("license/playready") }
	};
}

//#endregion
//#region ../media/dist/dev/dom/mux/media.js
const muxMediaDefaultProps = {
	src: "",
	source: null
};
/**
* @fires sourcechange - Fired when `source` changes, either directly or by parsing a new `src`. Read `source` for the
*   new value.
* @fires contentdatachange - Fired when the derived `contentData` changes. Read `contentData` for the new value.
*/
var MuxMedia = class extends HlsJsMedia {
	#source = muxMediaDefaultProps.source;
	#contentData = {};
	/**
	* Media source URL. Setting a Mux stream URL (`https://stream.mux.com/<playback-id>.m3u8?...`) extracts the playback
	* ID and query params into `source`; other URLs are kept as a plain `source.src`.
	*
	* Only playback options carry over. Mux identity comes from the URL, and the signed `poster`, `storyboard`, and `drm`
	* tokens are scoped to a playback ID, so carrying them onto a different source would build rejected URLs.
	*/
	get src() {
		return super.src;
	}
	set src(value) {
		if (super.src === value) return;
		const { type, preferPlayback, engine, maxAutoResolution, capRenditionToPlayerSize, minAutoResolution } = this.#source ?? {};
		const source = {
			...type && { type },
			...preferPlayback && { preferPlayback },
			...engine && { engine },
			...maxAutoResolution && { maxAutoResolution },
			...capRenditionToPlayerSize !== void 0 && { capRenditionToPlayerSize },
			...minAutoResolution && { minAutoResolution },
			...parseMuxVideoURL(value) ?? (value ? { src: value } : null)
		};
		this.source = Object.keys(source).length > 0 ? source : null;
	}
	/**
	* Structured Mux source. Setting it derives `src` from the playback ID, custom domain, and `playback` params
	* (appended as `snake_case` query params). A `playback.token` replaces all other params — signed URLs bake them into
	* the token. Engine options live under `engine`.
	*
	* A `drm.token` fills in `drm` itself: Mux's FairPlay, Widevine, and PlayReady license servers for this playback ID,
	* so protected media plays whichever path the browser takes. License servers named alongside the token win, key by
	* key, for content Mux does not license.
	*
	* `playback.maxResolution` and `playback.minResolution` are server-side: they decide which renditions Mux puts in the
	* manifest at all. The inherited `maxAutoResolution` and `minAutoResolution` only look like their pair — those are
	* client-side and bound which of the renditions that _do_ arrive adaptive selection reaches for. The two halves are
	* independent.
	*/
	get source() {
		return this.#source;
	}
	set source(value) {
		const source = value ?? null;
		if (source === this.#source) return;
		this.#source = source;
		const contentDataChanged = this.#refreshContentData();
		super.source = source && {
			...source,
			src: createMuxVideoURL(source) ?? source.src ?? "",
			...withMuxDrm(source)
		};
		if (contentDataChanged) this.dispatchEvent(new Event("contentdatachange"));
	}
	/**
	* Image URLs `source` describes rather than plays: `poster` from its `poster` params, `storyboard` from its
	* `storyboard` params. A key is absent when the URL can't be built — no playback ID, or signed playback without a
	* matching image token.
	*
	* Derived from `source` and nothing else. The same object is handed back until one of those URLs changes, and
	* `contentdatachange` announces it when it does. Nothing here is applied for you, apart from the thumbnail track
	* `<mux-video>` adds from `storyboard` (and drops for live streams).
	*/
	get contentData() {
		return this.#contentData;
	}
	/** Rebuild the derived bag, reporting whether anything about it changed. */
	#refreshContentData() {
		const poster = createMuxPosterURL(this.#source);
		const storyboard = createMuxStoryboardURL(this.#source);
		const next = {
			...poster && { poster },
			...storyboard && { storyboard }
		};
		if (shallowEqual(this.#contentData, next)) return false;
		this.#contentData = next;
		return true;
	}
};
/**
* Resolve Mux's DRM authoring input into the license servers the HLS layer licenses from. Which engine ends up playing
* is decided later, and both read `drm`, so a signed Mux source plays either way.
*
* `token` is Mux's own input and stops here — it names no license server, and a key system is what everything
* downstream expects to find. Servers the caller named win, key by key, so their own licensing replaces the derived
* URLs.
*/
function withMuxDrm(source) {
	const { token: _token, ...systems } = source.drm ?? {};
	const drm = {
		...createMuxDrmSystems(source),
		...systems
	};
	return { drm: Object.keys(drm).length > 0 ? drm : void 0 };
}

//#endregion
export { MuxMedia as t };
//# sourceMappingURL=media-CyMOaRVc.js.map