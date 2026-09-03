import { C as resolveLabel, E as applyElementProps, F as defaults, T as logMissingFeature, k as createButton } from "./popover-element-DYgnWFFt.js";
import { r as resolveText$1 } from "./translate-text-BWvoW5-r.js";
import { t as UIElement } from "./ui-element-DraIA8Ee.js";
import { r as playerContext } from "./context-DlE_3NHA.js";
import { l as createState, n as PlayerController } from "./create-player-Duo2X4dJ.js";
import { _ as selectTime, a as selectBuffer, k as i18nContext, l as selectLive, r as applyStateDataAttrs, y as I18nController } from "./container-element-DGG9TQ6W.js";
import { resolveText, translateText } from "./i18n.dev.js";

//#region ../core/dist/dev/i18n/text/live.js
const prefix = "live.";
const playingText = {
	key: `${prefix}playing`,
	text: "Playing live"
};
const seekToEdgeText = {
	key: `${prefix}seekToEdge`,
	text: "Seek to live edge"
};
const badgeText = {
	key: `${prefix}badge`,
	text: "Live"
};

//#endregion
//#region ../core/dist/dev/core/ui/live-button/core.js
/**
* Fallback offset (in seconds) from the end of the seekable window used to decide "at live edge" when `liveEdgeStart`
* is unavailable.
*/
const LIVE_EDGE_OFFSET = 10;
/**
* Grace window (in seconds) before `liveEdgeStart` that still counts as "at the live edge". Absorbs the small gap
* between the player's initial playback position (e.g. hls.js `liveSyncDuration`) and the manifest's `HOLD-BACK`, so
* autoplay reliably reports live.
*/
const LIVE_EDGE_TOLERANCE = 5;
/**
* Core state machine for a "Live" button. Indicates whether the player is playing at the live edge and seeks to the
* Seekable Live Edge when activated.
*
* @see https://github.com/video-dev/media-ui-extensions/blob/main/proposals/0007-live-edge.md
*/
var LiveButtonCore = class LiveButtonCore {
	/** Default visible text used when no children are provided. */
	static defaultText = badgeText;
	static defaultProps = {
		label: "",
		disabled: false
	};
	state = createState({
		live: false,
		liveEdge: false,
		label: ""
	});
	#props = { ...LiveButtonCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, LiveButtonCore.defaultProps);
	}
	getLabel(state) {
		const label = resolveLabel(this.#props.label, state);
		if (label) return label;
		if (state.liveEdge) return playingText;
		return seekToEdgeText;
	}
	getAttrs(state) {
		const inactive = this.#props.disabled || state.liveEdge;
		return {
			"aria-label": this.getLabel(state),
			"aria-disabled": inactive ? "true" : void 0
		};
	}
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		const media = this.#media;
		const live = isLiveMedia(media);
		const liveEdge = live && this.#isAtLiveEdge(media);
		this.state.patch({
			live,
			liveEdge
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	/** Seek to the Seekable Live Edge. No-op when not live or already at edge. */
	async seekToLive(media) {
		if (this.#props.disabled) return;
		if (!isLiveMedia(media)) return;
		if (this.#isAtLiveEdge(media)) return;
		const target = liveEdgeTarget(media);
		if (target == null) return;
		await media.seek(target);
	}
	#isAtLiveEdge(media) {
		const { currentTime, liveEdgeStart } = media;
		if (Number.isFinite(liveEdgeStart)) return currentTime >= liveEdgeStart - LIVE_EDGE_TOLERANCE;
		const target = liveEdgeTarget(media);
		if (target == null) return false;
		return currentTime >= target - LIVE_EDGE_OFFSET;
	}
};
function isLiveMedia(media) {
	return !Number.isNaN(media.targetLiveWindow);
}
function liveEdgeTarget(media) {
	const { seekable } = media;
	if (seekable.length === 0) return null;
	const end = seekable[seekable.length - 1][1];
	return Number.isFinite(end) ? end : null;
}

//#endregion
//#region ../core/dist/dev/core/ui/live-button/data.js
const LiveButtonDataAttrs = {
	/** Present when the stream is live (or DVR). */
	live: "data-live",
	/** Present when playback is at the live edge. */
	liveEdge: "data-live-edge"
};

//#endregion
//#region src/ui/live-button/live-button-element.ts
/**
* `<media-live-button>` — selects from `live`, `time`, and `buffer` features and composes them into the
* `LiveButtonMediaState` consumed by `LiveButtonCore`.
*
* Doesn't extend `MediaButtonElement` because that base couples a button to a single feature selector; the LiveButton
* needs three.
*/
var LiveButtonElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.disabled = false;
		this.label = "";
		this.core = new LiveButtonCore();
		this.live = new PlayerController(this, playerContext, selectLive);
		this.time = new PlayerController(this, playerContext, selectTime);
		this.buffer = new PlayerController(this, playerContext, selectBuffer);
		this.#i18n = new I18nController(this, i18nContext);
		this.#defaultContent = false;
		this.#disconnect = null;
	}
	static {
		this.tagName = "media-live-button";
	}
	static {
		this.properties = {
			label: { type: String },
			disabled: { type: Boolean }
		};
	}
	#i18n;
	get $state() {
		return this.core.state;
	}
	#defaultContent;
	#disconnect;
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		this.#defaultContent ||= !this.textContent?.trim();
		if (this.#defaultContent) this.textContent = translateText(LiveButtonCore.defaultText, this.#i18n.value);
		this.#disconnect = new AbortController();
		const buttonProps = createButton({
			onActivate: () => {
				const media = this.#getMedia();
				if (media) this.core.seekToLive(media);
			},
			isDisabled: () => this.disabled || !this.#getMedia()
		});
		applyElementProps(this, buttonProps, { signal: this.#disconnect.signal });
		if (!this.#getMedia()) logMissingFeature(this.localName, this.live.displayName ?? "live");
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	/** Returns the button's current label derived from media state. */
	getLabel() {
		return this.core.state.current.label ? resolveText(this.core.state.current.label) : void 0;
	}
	/** Resolved label for tooltips and other display surfaces. */
	getResolvedLabel() {
		if (!this.#getMedia()) return void 0;
		const state = this.core.getState();
		return translateText(this.core.getLabel(state), this.#i18n.value);
	}
	willUpdate(changed) {
		super.willUpdate(changed);
		this.core.setProps(this);
	}
	update(changed) {
		super.update(changed);
		if (this.#defaultContent) this.textContent = translateText(LiveButtonCore.defaultText, this.#i18n.value);
		const media = this.#getMedia();
		if (!media) return;
		this.core.setMedia(media);
		const state = this.core.getState();
		const attrs = this.core.getAttrs(state);
		applyElementProps(this, {
			...attrs,
			"aria-label": translateText(attrs["aria-label"], this.#i18n.value)
		});
		applyStateDataAttrs(this, state, LiveButtonDataAttrs);
	}
	/**
	* Compose the LiveButton media state from the three feature slices. Returns `null` when any are missing so the button
	* stays disabled until all three features are registered on the player.
	*/
	#getMedia() {
		const live = this.live.value;
		const time = this.time.value;
		const buffer = this.buffer.value;
		if (!live || !time || !buffer) return null;
		return {
			currentTime: time.currentTime,
			seek: time.seek,
			seekable: buffer.seekable,
			liveEdgeStart: live.liveEdgeStart,
			targetLiveWindow: live.targetLiveWindow
		};
	}
};

//#endregion
export { LiveButtonElement as t };
//# sourceMappingURL=live-button-element-MT_0u1RC.js.map