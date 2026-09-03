import { t as shallowEqual } from "./shallow-equal-C7S8rj2f.js";
import { a as createMuxVideoURL, i as createMuxStoryboardURL, n as createMuxPosterURL, o as parseMuxVideoURL } from "./source-nD7XFj1N.js";

//#region ../spf/dist/dev/playback/adapters/mux-video/adapter.js
const muxMediaDefaultProps = {
	src: "",
	source: null
};
/**
* Mux identity over any SPF Media: the structured `source`, the `src` derived from it, and the image URLs it describes.
*
* Everything here is Mux identity, so it carries no engine and both flavors get it unchanged — the video Media over the
* full HLS engine, the audio-only Media over the subtractive one. A mixin rather than a shared base class because each
* flavor extends a different SPF Media, so there is no common class to put this on, only a common `src` accessor to
* write through.
*
* Unlike the hls.js-backed `MuxMedia`, there is no inherited `source` to delegate to — the SPF Medias know only `src` —
* so this owns the structured source and dispatches `sourcechange` itself.
*
* @fires sourcechange - Fired when `source` changes, either directly or by parsing a new `src`. Read `source` for the
*   new value.
* @fires contentdatachange - Fired when the derived `contentData` changes. Read `contentData` for the new value.
*/
function MuxMediaMixin(BaseClass) {
	class MuxMediaImpl extends BaseClass {
		/**
		* Named on the error copy when this engine can't play a source: the hls.js-backed Mux Media plays the MPEG-TS and
		* DRM-protected sources that SPF does not, and it backs both `<mux-video>` and `<mux-audio>`.
		*
		* Names the flavor rather than an import path, because one Media is reached through three of them —
		* `@videojs/html`, `@videojs/react`, and this package — and each has a different counterpart. The flavor suffix is
		* the one thing common to the layers a consumer imports elements and components from.
		*/
		static get alternativeMediaSuggestion() {
			return "Try the hls.js-backed Mux media instead: import the `hls-js` flavor in place of the `spf` one.";
		}
		#source = muxMediaDefaultProps.source;
		#contentData = {};
		/**
		* Media source URL. Setting a Mux stream URL (`https://stream.mux.com/<playback-id>.m3u8?...`) extracts the
		* playback ID and query params into `source`; other URLs are kept as a plain `source.src`.
		*
		* Only playback options carry over. Mux identity comes from the URL, and the signed `poster`, `storyboard`, and
		* `drm` tokens are scoped to a playback ID, so carrying them onto a different source would build rejected URLs.
		*/
		get src() {
			return super.src;
		}
		set src(value) {
			if (super.src === value) return;
			this.source = parseMuxVideoURL(value) ?? (value ? { src: value } : null);
		}
		/**
		* Structured Mux source. Setting it derives `src` from the playback ID, custom domain, and `playback` params
		* (appended as `snake_case` query params). A `playback.token` replaces all other params — signed URLs bake them
		* into the token.
		*/
		get source() {
			return this.#source;
		}
		set source(value) {
			const source = value ?? null;
			if (source === this.#source) return;
			this.#source = source;
			const contentDataChanged = this.#refreshContentData();
			super.src = source && (createMuxVideoURL(source) ?? source.src) || "";
			this.dispatchEvent?.(new Event("sourcechange"));
			if (contentDataChanged) this.dispatchEvent?.(new Event("contentdatachange"));
		}
		/**
		* Image URLs `source` describes rather than plays: `poster` from its `poster` params, `storyboard` from its
		* `storyboard` params.
		*
		* Derived from `source` and nothing else. The same object is handed back until one of those URLs changes, and
		* `contentdatachange` announces it when it does. Nothing here is applied for you.
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
	}
	return MuxMediaImpl;
}

//#endregion
export { MuxMediaMixin as t };
//# sourceMappingURL=adapter-Cgwqjhe2.js.map