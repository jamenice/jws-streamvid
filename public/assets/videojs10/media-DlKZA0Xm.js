import { C as endOfStream, Ct as effect, D as parseMultivariantPlaylist, E as getResolvedSelectedTrackDuration, G as collectErrors, J as trackCurrentTime, O as canPlayTrack, T as calculatePresentationDuration, Y as loadAudioSegments, c as reportUnsupportedTrackConditions, dt as makeShareSignals, f as resolvePresentation, ft as createComposition, g as setupAudioBufferActors, h as setupMediaSource, k as attachMediaSourceAsSourceElement, l as resolveAudioTrack, m as updateMediaSourceDuration, n as hasUnsupportedFeatureCause, r as withAlternativeMediaSuggestion, s as UNSUPPORTED_PLAYBACK_FEATURE_MESSAGE, t as firstFatal, vt as SVTA_NO_SUPPORTED_AUDIO_TRACK, xt as SVTA_UNSUPPORTED_PLAYBACK_FEATURE } from "./error-surface-C2oiXXnW.js";
import { t as HTMLMediaElementHost } from "./media-host-D7BMz4NW.js";
import { a as setupFailoverMonitor, c as setupAirPlay, g as trackLoadTriggers, i as syncPreload, l as deriveCdnPriority, m as establishStartMediaTime, n as relocationPipelinesFor, o as recoverEndStall, p as deriveSharedMinStartMediaTime, s as applyStartPosition, u as switchAudioTrack } from "./relocation-pipelines-BbjRRa6I.js";

//#region ../spf/dist/dev/playback/engines/hls/engine-audio-only.js
const shareSignals = makeShareSignals(["userAudioTrackSelection", "disableRemotePlayback"]);
/**
* Create an audio-only HLS playback engine.
*
* Subtractive composition variant of `createHlsVideoEngine`: omits video-side behaviors (`resolveVideoTrack`,
* `switchVideoTrack`, `setupVideoBufferActors`, `loadVideoSegments`) and text-track behaviors (`switchTextTrack`,
* `resolveTextTrack`, `syncTextTracks`, `setupTextTrackActors`, `loadTextTrackSegments`). The remaining audio pipeline
* composes unchanged.
*
* Handles both truly audio-only HLS sources (no video stream-inf) and mixed-AV HLS sources where the audio rendition is
* selected and video / subtitle renditions are ignored at composition time. The variant decision is encoded by adapter
* choice; this engine does not branch on source shape.
*
* @example
*   ```ts
*   let signals: HlsAudioEngineSignals;
*   const engine = createHlsAudioEngine({
*     preferredAudioLanguage: 'en',
*     onSignalsReady: (refs) => {
*       signals = refs;
*     },
*   });
*
*   signals.context.mediaElement.set(audioEl);
*   signals.state.presentation.set({ url: 'https://example.com/stream.m3u8' });
*   ```;
*/
function createHlsAudioEngine(config = {}) {
	const deriveStartMediaTime = config.deriveStartMediaTime ?? deriveSharedMinStartMediaTime;
	const finalConfig = {
		...config,
		deriveStartMediaTime,
		attachMediaSource: attachMediaSourceAsSourceElement,
		canPlayTrack: config.canPlayTrack ?? canPlayTrack,
		reportUnsupportedTrackConditions: config.reportUnsupportedTrackConditions ?? reportUnsupportedTrackConditions,
		resolveDuration: config.resolveDuration ?? getResolvedSelectedTrackDuration,
		parsePresentation: config.parsePresentation ?? parseMultivariantPlaylist,
		audioMessagePipelines: relocationPipelinesFor("audio", deriveStartMediaTime)
	};
	return createComposition([
		syncPreload,
		trackLoadTriggers,
		resolvePresentation,
		deriveCdnPriority,
		setupFailoverMonitor,
		collectErrors,
		switchAudioTrack,
		resolveAudioTrack,
		calculatePresentationDuration,
		setupMediaSource,
		updateMediaSourceDuration,
		establishStartMediaTime,
		setupAudioBufferActors,
		setupAirPlay,
		trackCurrentTime,
		applyStartPosition,
		loadAudioSegments,
		endOfStream,
		recoverEndStall,
		shareSignals
	], { config: finalConfig });
}

//#endregion
//#region ../spf/dist/dev/playback/adapters/hls-audio/adapter.js
const hlsAudioMediaDefaultProps = {
	src: "",
	preload: "",
	disableRemotePlayback: false
};
/**
* Which reported conditions this composition treats as fatal. Only the audio verdict: an audio-only engine composes no
* video selection, so `SVTA_NO_SUPPORTED_VIDEO_TRACK` is never reported and surfacing it would describe a track type
* this media doesn't have.
*/
const FATAL_SVTA_CODES = /* @__PURE__ */ new Set([SVTA_NO_SUPPORTED_AUDIO_TRACK]);
/**
* Mixin that adds SPF audio-only HLS playback to any base class.
*
* @example
*   class HlsAudioMedia extends HlsAudioMediaMixin(HTMLVideoElementHost) {}
*
*   const media = new HlsAudioMedia();
*   media.attach(document.querySelector('video'));
*   media.src = 'https://stream.mux.com/abc123.m3u8';
*
* @fires error - Fired when a fatal condition is reported. Read `error` for it.
*
*   Parallel to `HlsVideoMediaMixin` with one substantive difference: the underlying engine is the audio-only variant
*   (`createHlsAudioEngine`), which omits video and text-track behaviors. The src / preload / disableRemotePlayback /
*   play() contract per the WHATWG HTML spec is identical to the default adapter.
*
*   Selecting this adapter is the variant decision: instantiating `HlsAudioMediaElement` opts the consumer into
*   audio-only delivery even when the source is a mixed-AV HLS manifest.
*/
function HlsAudioMediaMixin(BaseClass) {
	class HlsAudioMediaImpl extends BaseClass {
		/**
		* A complete sentence naming the Media to reach for when this one can't play a source. Appended to the copy this
		* adapter logs.
		*
		* Empty here, and overridden the same way as on the video adapter — see its note. `hls-audio` has no
		* better-equipped sibling of its own; the Mux audio Media built on this engine does, and points at the
		* hls.js-backed one.
		*/
		static get alternativeMediaSuggestion() {}
		#engine;
		#config;
		#signals;
		#preload = hlsAudioMediaDefaultProps.preload;
		#disableRemotePlayback = hlsAudioMediaDefaultProps.disableRemotePlayback;
		#error = null;
		/** Reported condition currently surfaced — see the video adapter's note. */
		#reportedCode = null;
		#stopErrorSync;
		/** Pending loadstart listener from a deferred play() retry, if any. */
		#loadstartListener = null;
		constructor(...args) {
			super(...args);
			const { config } = args?.[0] ?? {};
			this.#config = config;
			this.#engine = this.#createEngine();
			this.#stopErrorSync = effect(() => {
				const errors = this.#signals.state.errors.get();
				this.#setError(firstFatal(errors, FATAL_SVTA_CODES), errors);
			});
		}
		/**
		* The current fatal error, or `null`. Only _fatal_ conditions appear here — the engine reports non-fatal ones too,
		* which stay in `engine.state.errors`. Resets per source. Fires `'error'` when set.
		*/
		get error() {
			return this.#error;
		}
		#setError(reported, errors) {
			if (!reported) {
				this.#error = null;
				this.#reportedCode = null;
				return;
			}
			if (this.#reportedCode === reported.code) return;
			this.#reportedCode = reported.code;
			const unsupported = hasUnsupportedFeatureCause(errors);
			if (unsupported) console.error(this.#withSuggestion(UNSUPPORTED_PLAYBACK_FEATURE_MESSAGE), { conditions: errors });
			this.#error = {
				code: unsupported ? SVTA_UNSUPPORTED_PLAYBACK_FEATURE : reported.code,
				message: reported.message ?? "",
				...reported.data === void 0 ? {} : { data: reported.data }
			};
			this.dispatchEvent?.(new Event("error"));
		}
		/**
		* Underlying playback engine — the low-level SPF reactive composition that drives playback. An advanced escape
		* hatch for direct engine access; normal playback is driven through this element's own properties and methods.
		*/
		get engine() {
			return this.#engine;
		}
		attach(mediaElement) {
			super.attach?.(mediaElement);
			this.#signals.context.mediaElement.set(mediaElement);
		}
		detach() {
			this.#cancelPendingPlay();
			this.#signals.context.mediaElement.set(void 0);
			super.detach?.();
		}
		destroy() {
			this.#cancelPendingPlay();
			this.#stopErrorSync();
			this.#engine.destroy();
		}
		/** Preload type (`'none'` / `'metadata'` / `'auto'`). */
		get preload() {
			return this.#preload;
		}
		set preload(value) {
			this.#preload = value;
			if (value) this.#signals.state.preload.set(value);
		}
		get disableRemotePlayback() {
			return this.#disableRemotePlayback;
		}
		set disableRemotePlayback(value) {
			this.#disableRemotePlayback = value;
			this.#signals.state.disableRemotePlayback.set(value);
		}
		get src() {
			return this.#signals.state.presentation.get()?.url ?? "";
		}
		set src(value) {
			if (value === this.src) return;
			this.#cancelPendingPlay();
			this.#signals.state.presentation.set(value ? { url: value } : void 0);
		}
		play() {
			const mediaElement = this.#signals.context.mediaElement.get();
			if (!mediaElement) return Promise.reject(/* @__PURE__ */ new Error("HlsAudioMediaElement: no media element attached"));
			this.#signals.state.loadActivated.set(true);
			return mediaElement.play().catch((err) => {
				if (this.src) return new Promise((resolve, reject) => {
					const listener = () => {
						this.#loadstartListener = null;
						mediaElement.play().then(resolve, reject);
					};
					this.#loadstartListener = listener;
					mediaElement.addEventListener("loadstart", listener, { once: true });
				});
				throw err;
			});
		}
		/** `message`, plus the alternative-Media sentence when this class names one. */
		#withSuggestion(message) {
			return withAlternativeMediaSuggestion(message, this);
		}
		#createEngine() {
			return createHlsAudioEngine({
				...this.#config,
				onSignalsReady: (signals) => {
					this.#signals = signals;
				}
			});
		}
		#cancelPendingPlay() {
			if (!this.#loadstartListener) return;
			this.#signals.context.mediaElement.get()?.removeEventListener("loadstart", this.#loadstartListener);
			this.#loadstartListener = null;
		}
	}
	return HlsAudioMediaImpl;
}
/** Standalone SPF audio-only media adapter with no base class. */
var HlsAudioMediaElement = class extends HlsAudioMediaMixin(class {}) {};

//#endregion
//#region ../media/dist/dev/dom/audio-host/audio-host.js
var HTMLAudioElementHost = class extends HTMLMediaElementHost {};

//#endregion
//#region ../spf/dist/dev/playback/adapters/hls-audio/media.js
const HlsAudioMediaBase = HlsAudioMediaMixin(HTMLAudioElementHost);
var HlsAudioMedia = class extends HlsAudioMediaBase {};

//#endregion
export { HlsAudioMedia as t };
//# sourceMappingURL=media-DlKZA0Xm.js.map