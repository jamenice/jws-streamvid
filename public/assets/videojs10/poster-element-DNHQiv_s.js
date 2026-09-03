import { C as resolveLabel, E as applyElementProps, F as defaults, S as exitText$1, T as logMissingFeature, _ as MenuRadioGroupElement, o as MediaButtonElement, x as enterText$1 } from "./popover-element-DYgnWFFt.js";
import { r as resolveText$1 } from "./translate-text-BWvoW5-r.js";
import { t as UIElement } from "./ui-element-DraIA8Ee.js";
import { r as playerContext } from "./context-DlE_3NHA.js";
import { l as createState, n as PlayerController } from "./create-player-Duo2X4dJ.js";
import { c as selectFullscreen, d as selectPiP, f as selectPlayback, g as selectTextTrack, h as selectRemotePlayback, k as i18nContext, r as applyStateDataAttrs, u as selectMetadata, y as I18nController } from "./container-element-DGG9TQ6W.js";
import { n as isCaptionOrSubtitleTrack } from "./text-track-DMA7pa8W.js";
import { n as supportsWebKitAirPlay } from "./webkit-C682yTT7.js";
import { a as captionsText, d as subtitlesText, o as offText, t as RadioOptionsController } from "./radio-options-controller-C3ZBWVO0.js";
import { translateText } from "./i18n.dev.js";

//#region ../core/dist/dev/i18n/text/airplay.js
const prefix$3 = "airplay.";
const startText$1 = {
	key: `${prefix$3}start`,
	text: "Start AirPlay"
};
const stopText$1 = {
	key: `${prefix$3}stop`,
	text: "Stop AirPlay"
};

//#endregion
//#region ../core/dist/dev/i18n/text/cast.js
const prefix$2 = "cast.";
const startText = {
	key: `${prefix$2}start`,
	text: "Start casting"
};
const stopText = {
	key: `${prefix$2}stop`,
	text: "Stop casting"
};
const connectingText = {
	key: `${prefix$2}connecting`,
	text: "Connecting"
};

//#endregion
//#region ../core/dist/dev/core/ui/airplay-button/core.js
var AirPlayButtonCore = class AirPlayButtonCore {
	static defaultProps = {
		label: "",
		disabled: false
	};
	state = createState({
		state: "disconnected",
		availability: "unsupported",
		disabled: true,
		hidden: true,
		label: ""
	});
	#props = { ...AirPlayButtonCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, AirPlayButtonCore.defaultProps);
	}
	getLabel(state) {
		const label = resolveLabel(this.#props.label, state);
		if (label) return label;
		if (state.state === "connected") return stopText$1;
		if (state.state === "connecting") return connectingText;
		return startText$1;
	}
	getAttrs(state) {
		return {
			"aria-label": this.getLabel(state),
			"aria-disabled": state.disabled ? "true" : void 0,
			hidden: state.hidden ? "" : void 0
		};
	}
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		const media = this.#media;
		const availability = supportsWebKitAirPlay() ? media.remotePlaybackAvailability : "unsupported";
		this.state.patch({
			state: media.remotePlaybackState,
			availability,
			disabled: this.#props.disabled || availability !== "available",
			hidden: availability !== "available"
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	async toggle(media) {
		this.setMedia(media);
		if (this.getState().disabled) return;
		try {
			await media.toggleRemotePlayback();
		} catch {}
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/airplay-button/data.js
const AirPlayButtonDataAttrs = {
	/**
	* Current AirPlay connection state.
	*
	* @see https://developer.mozilla.org/en-US/docs/Web/API/RemotePlayback/state
	*/
	state: "data-airplay-state",
	/**
	* Whether AirPlay is available on the active platform and media.
	*
	* @see https://developer.mozilla.org/en-US/docs/Web/API/RemotePlayback
	*/
	availability: "data-availability",
	/** Present when the button is non-interactive (mirrors `aria-disabled`). */
	disabled: "data-disabled",
	/** Present when the button is hidden because AirPlay is unavailable. */
	hidden: "data-hidden"
};

//#endregion
//#region ../core/dist/dev/i18n/text/captions.js
const prefix$1 = "captions.";
const enableText = {
	key: `${prefix$1}enable`,
	text: "Enable captions"
};
const disableText = {
	key: `${prefix$1}disable`,
	text: "Disable captions"
};

//#endregion
//#region ../core/dist/dev/core/ui/captions-button/core.js
var CaptionsButtonCore = class CaptionsButtonCore {
	static defaultProps = {
		label: "",
		disabled: false,
		menuTrigger: false
	};
	state = createState({
		subtitlesShowing: false,
		availability: "unavailable",
		disabled: true,
		hidden: true,
		label: ""
	});
	#props = { ...CaptionsButtonCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, CaptionsButtonCore.defaultProps);
	}
	getLabel(state) {
		const label = resolveLabel(this.#props.label, state);
		if (label) return label;
		return state.subtitlesShowing ? disableText : enableText;
	}
	getAttrs(state) {
		return {
			"aria-label": this.getLabel(state),
			"aria-disabled": state.disabled ? "true" : void 0,
			hidden: state.hidden ? "" : void 0
		};
	}
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		const media = this.#media;
		const availability = media.textTrackList.some(isCaptionOrSubtitleTrack) ? "available" : "unavailable";
		this.state.patch({
			subtitlesShowing: media.subtitlesShowing,
			availability,
			disabled: this.#props.disabled || availability !== "available",
			hidden: availability === "unavailable"
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	toggle(media) {
		this.setMedia(media);
		if (this.getState().disabled) return;
		if (this.#props.menuTrigger && getCaptionTrackCount$1(media) > 1) return;
		media.toggleSubtitles();
	}
};
function getCaptionTrackCount$1(media) {
	return media.textTrackList.filter(isCaptionOrSubtitleTrack).length;
}

//#endregion
//#region ../core/dist/dev/core/ui/captions-button/data.js
const CaptionsButtonDataAttrs = {
	/** Present when captions are enabled. */
	subtitlesShowing: "data-active",
	/** Indicates captions availability (`available` or `unavailable`). */
	availability: "data-availability",
	/** Present when the button is non-interactive (mirrors `aria-disabled`). */
	disabled: "data-disabled",
	/** Present when the button is hidden because no caption tracks are present. */
	hidden: "data-hidden"
};

//#endregion
//#region ../core/dist/dev/core/ui/captions-radio-group/core.js
function formatTrackLabel(track) {
	if (track.label) return track.label;
	if (track.language) return track.language;
	return track.kind === "captions" ? captionsText : subtitlesText;
}
function sortCaptionTracks(a, b) {
	return a.kind > b.kind ? 1 : a.kind < b.kind ? -1 : 0;
}
function getCaptionTracks(textTrackList) {
	return textTrackList.filter(isCaptionOrSubtitleTrack).sort(sortCaptionTracks);
}
var CaptionsRadioGroupCore = class CaptionsRadioGroupCore {
	static defaultProps = {
		label: "",
		formatTrack: formatTrackLabel,
		disabled: false
	};
	state = createState({
		options: [{
			value: "off",
			label: offText,
			disabled: false
		}],
		value: "off",
		subtitlesShowing: false,
		disabled: true,
		hidden: true,
		availability: "unavailable",
		label: ""
	});
	#props = { ...CaptionsRadioGroupCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, CaptionsRadioGroupCore.defaultProps);
	}
	getLabel(state) {
		const label = resolveLabel(this.#props.label, state);
		if (label) return label;
		return captionsText;
	}
	getTrackLabel(track) {
		return this.#props.formatTrack(track);
	}
	getAttrs(state) {
		return {
			"aria-label": this.getLabel(state),
			"aria-disabled": state.disabled ? "true" : void 0,
			hidden: state.hidden ? "" : void 0
		};
	}
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		const media = this.#media;
		const captionTracks = getCaptionTracks(media.textTrackList);
		const showingIndex = captionTracks.findIndex((track) => track.mode === "showing");
		const options = [{
			value: "off",
			label: offText,
			disabled: false
		}, ...captionTracks.map((track, index) => ({
			value: track.id || String(index),
			label: this.getTrackLabel(track),
			disabled: false
		}))];
		const availability = captionTracks.length > 0 ? "available" : "unavailable";
		this.state.patch({
			options,
			value: showingIndex === -1 ? "off" : captionTracks[showingIndex].id || String(showingIndex),
			subtitlesShowing: media.subtitlesShowing,
			disabled: this.#props.disabled || captionTracks.length === 0,
			hidden: availability === "unavailable",
			availability
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	select(media, value) {
		if (this.#props.disabled) return;
		const captionTracks = getCaptionTracks(media.textTrackList);
		if (!captionTracks.length) return;
		if (value === "off") {
			media.selectSubtitlesTrack("off");
			return;
		}
		if (!captionTracks.some((track, index) => (track.id || String(index)) === value)) return;
		media.selectSubtitlesTrack(value);
	}
	selectValue(media, value) {
		this.select(media, value);
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/captions-radio-group/data.js
const CaptionsRadioGroupDataAttrs = {
	/** Present when captions are enabled. */
	subtitlesShowing: "data-active",
	/** Present when track selection is disabled. */
	disabled: "data-disabled",
	/** Present when track selection is unavailable. */
	hidden: "data-hidden",
	/** Indicates captions availability (`available` or `unavailable`). */
	availability: "data-availability"
};

//#endregion
//#region ../core/dist/dev/core/ui/cast-button/core.js
var CastButtonCore = class CastButtonCore {
	static defaultProps = {
		label: "",
		disabled: false
	};
	state = createState({
		connection: "disconnected",
		availability: "unsupported",
		disabled: true,
		hidden: true,
		label: ""
	});
	#props = { ...CastButtonCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, CastButtonCore.defaultProps);
	}
	getLabel(state) {
		const label = resolveLabel(this.#props.label, state);
		if (label) return label;
		if (state.connection === "connected") return stopText;
		if (state.connection === "connecting") return connectingText;
		return startText;
	}
	getAttrs(state) {
		return {
			"aria-label": this.getLabel(state),
			"aria-disabled": state.disabled ? "true" : void 0,
			hidden: state.hidden ? "" : void 0
		};
	}
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		const media = this.#media;
		const availability = !!globalThis.chrome ? media.remotePlaybackAvailability : "unsupported";
		this.state.patch({
			connection: media.remotePlaybackState,
			availability,
			disabled: this.#props.disabled || availability !== "available",
			hidden: availability === "unsupported"
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	async toggle(media) {
		this.setMedia(media);
		if (this.getState().disabled) return;
		return media.toggleRemotePlayback();
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/cast-button/data.js
const CastButtonDataAttrs = {
	/**
	* Current remote playback connection state.
	*
	* @see https://developer.mozilla.org/en-US/docs/Web/API/RemotePlayback/state
	*/
	connection: "data-cast-state",
	/**
	* Whether remote playback can be requested on this platform.
	*
	* @see https://developer.mozilla.org/en-US/docs/Web/API/RemotePlayback
	*/
	availability: "data-availability",
	/** Present when the button is non-interactive (mirrors `aria-disabled`). */
	disabled: "data-disabled",
	/** Present when the button is hidden because the feature is unsupported. */
	hidden: "data-hidden"
};

//#endregion
//#region ../core/dist/dev/core/ui/fullscreen-button/core.js
var FullscreenButtonCore = class FullscreenButtonCore {
	static defaultProps = {
		label: "",
		disabled: false
	};
	state = createState({
		fullscreen: false,
		availability: "unavailable",
		disabled: true,
		hidden: true,
		label: ""
	});
	#props = { ...FullscreenButtonCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, FullscreenButtonCore.defaultProps);
	}
	getLabel(state) {
		const label = resolveLabel(this.#props.label, state);
		if (label) return label;
		return state.fullscreen ? exitText$1 : enterText$1;
	}
	getAttrs(state) {
		return {
			"aria-label": this.getLabel(state),
			"aria-disabled": state.disabled ? "true" : void 0,
			hidden: state.hidden ? "" : void 0
		};
	}
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		const media = this.#media;
		const availability = media.fullscreenAvailability;
		this.state.patch({
			fullscreen: media.fullscreen,
			availability,
			disabled: this.#props.disabled || availability !== "available",
			hidden: availability !== "available"
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	async toggle(media) {
		this.setMedia(media);
		if (this.getState().disabled) return;
		return media.fullscreen ? media.exitFullscreen() : media.requestFullscreen();
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/fullscreen-button/data.js
const FullscreenButtonDataAttrs = {
	/** Present when fullscreen mode is active. */
	fullscreen: "data-fullscreen",
	/** Indicates fullscreen availability (`available`, `unavailable`, `unsupported`). */
	availability: "data-availability",
	/** Present when the button is non-interactive (mirrors `aria-disabled`). */
	disabled: "data-disabled",
	/** Present when the button is hidden because fullscreen is not available. */
	hidden: "data-hidden"
};

//#endregion
//#region ../core/dist/dev/i18n/text/pip.js
const prefix = "pip.";
const enterText = {
	key: `${prefix}enter`,
	text: "Enter picture-in-picture"
};
const exitText = {
	key: `${prefix}exit`,
	text: "Exit picture-in-picture"
};

//#endregion
//#region ../core/dist/dev/core/ui/pip-button/core.js
var PiPButtonCore = class PiPButtonCore {
	static defaultProps = {
		label: "",
		disabled: false
	};
	state = createState({
		pip: false,
		availability: "unavailable",
		disabled: true,
		hidden: true,
		label: ""
	});
	#props = { ...PiPButtonCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, PiPButtonCore.defaultProps);
	}
	getLabel(state) {
		const label = resolveLabel(this.#props.label, state);
		if (label) return label;
		return state.pip ? exitText : enterText;
	}
	getAttrs(state) {
		return {
			"aria-label": this.getLabel(state),
			"aria-disabled": state.disabled ? "true" : void 0,
			hidden: state.hidden ? "" : void 0
		};
	}
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		const media = this.#media;
		const availability = media.pipAvailability;
		this.state.patch({
			pip: media.pip,
			availability,
			disabled: this.#props.disabled || availability !== "available",
			hidden: availability !== "available"
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	async toggle(media) {
		this.setMedia(media);
		if (this.getState().disabled) return;
		return media.pip ? media.exitPictureInPicture() : media.requestPictureInPicture();
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/pip-button/data.js
const PiPButtonDataAttrs = {
	/** Present when picture-in-picture mode is active. */
	pip: "data-pip",
	/** Indicates picture-in-picture availability (`available`, `unavailable`, `unsupported`). */
	availability: "data-availability",
	/** Present when the button is non-interactive (mirrors `aria-disabled`). */
	disabled: "data-disabled",
	/** Present when the button is hidden because picture-in-picture is not available. */
	hidden: "data-hidden"
};

//#endregion
//#region ../core/dist/dev/core/ui/poster/core.js
/**
* Turns playback and metadata into poster presentation state.
*
* Owns no image of its own: a binding finds one, supplies how it is faring through {@link PosterCore.setImageLoadState},
* and paints the result.
*/
var PosterCore = class {
	#media = null;
	#loadState = "none";
	/** Supply the latest player state. Call before reading {@link PosterCore.getState}. */
	setMedia(media) {
		this.#media = media;
	}
	/** Supply how the binding's image is faring. */
	setImageLoadState(loadState) {
		this.#loadState = loadState;
	}
	/** Derive the presentation state to paint. */
	getState() {
		const media = this.#media;
		return {
			visible: !media.started,
			src: media.poster,
			loading: this.#loadState === "loading",
			loaded: this.#loadState === "loaded",
			error: this.#loadState === "error"
		};
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/poster/data.js
const PosterDataAttrs = {
	/** Present until playback starts. */
	visible: "data-visible",
	/** Present while the poster image is fetching. */
	loading: "data-loading",
	/** Present once the poster image has decoded. */
	loaded: "data-loaded",
	/** Present when the poster image failed. */
	error: "data-error"
};

//#endregion
//#region src/ui/airplay-button/airplay-button-element.ts
var AirPlayButtonElement = class extends MediaButtonElement {
	constructor(..._args) {
		super(..._args);
		this.core = new AirPlayButtonCore();
		this.stateAttrMap = AirPlayButtonDataAttrs;
		this.mediaState = new PlayerController(this, playerContext, selectRemotePlayback);
	}
	static {
		this.tagName = "media-airplay-button";
	}
	activate(state) {
		this.core.toggle(state);
	}
};

//#endregion
//#region src/ui/command-for.ts
/** Toggle a popup host linked via `commandfor` (menu, popover, etc.). */
function toggleCommandTarget(host, commandfor) {
	const root = host.getRootNode();
	const target = ("getElementById" in root ? root.getElementById(commandfor) : null) ?? root.querySelector(`#${CSS.escape(commandfor)}`);
	if (!target || !("open" in target)) return;
	const popup = target;
	popup.open = !popup.open;
}

//#endregion
//#region src/ui/captions-button/captions-button-element.ts
function getCaptionTrackCount(state) {
	return state.textTrackList.filter(isCaptionOrSubtitleTrack).length;
}
var CaptionsButtonElement = class extends MediaButtonElement {
	constructor(..._args) {
		super(..._args);
		this.commandfor = void 0;
		this.menuFor = void 0;
		this.#defaultCommandfor = void 0;
		this.core = new CaptionsButtonCore();
		this.stateAttrMap = CaptionsButtonDataAttrs;
		this.mediaState = new PlayerController(this, playerContext, selectTextTrack);
		this.hotkeyAction = "toggleSubtitles";
	}
	static {
		this.tagName = "media-captions-button";
	}
	static {
		this.properties = {
			label: { type: String },
			disabled: { type: Boolean },
			commandfor: { type: String },
			menuFor: {
				type: String,
				attribute: "menu-for"
			}
		};
	}
	#defaultCommandfor;
	connectedCallback() {
		super.connectedCallback();
		if (this.commandfor && this.commandfor !== this.menuFor) this.#defaultCommandfor = this.commandfor;
	}
	activate(state, event) {
		if (this.menuFor && getCaptionTrackCount(state) > 1) {
			if (event instanceof KeyboardEvent) toggleCommandTarget(this, this.menuFor);
			return;
		}
		this.core.toggle(state);
	}
	getIsButtonDisabled() {
		const media = this.mediaState.value;
		if (super.getIsButtonDisabled()) return true;
		if (media && getCaptionTrackCount(media) === 0) return true;
		return false;
	}
	willUpdate(changed) {
		super.willUpdate(changed);
		if (changed.has("commandfor") && this.commandfor !== this.menuFor) this.#defaultCommandfor = this.commandfor;
		if (changed.has("commandfor") || changed.has("menuFor")) this.#syncCommandFor();
	}
	update(changed) {
		super.update(changed);
		const media = this.mediaState.value;
		if (!media) return;
		this.#syncCommandFor(media);
		if (this.menuFor && getCaptionTrackCount(media) > 1) applyElementProps(this, { "aria-disabled": this.getIsButtonDisabled() ? "true" : void 0 });
	}
	#syncCommandFor(media) {
		const state = media ?? this.mediaState.value;
		const target = state && this.menuFor && getCaptionTrackCount(state) > 1 ? this.menuFor : this.#defaultCommandfor;
		if (target) this.setAttribute("commandfor", target);
		else this.removeAttribute("commandfor");
	}
};

//#endregion
//#region src/ui/captions-radio-group/captions-radio-group-element.ts
var CaptionsRadioGroupElement = class extends MenuRadioGroupElement {
	constructor(..._args) {
		super(..._args);
		this.disabled = false;
		this.label = "";
		this.formatTrack = CaptionsRadioGroupCore.defaultProps.formatTrack;
		this.#core = new CaptionsRadioGroupCore();
		this.#i18n = new I18nController(this, i18nContext);
		this.#mediaState = new PlayerController(this, playerContext, selectTextTrack);
		this.#options = new RadioOptionsController(this, {
			setItemAttributes: (item, option) => item.setAttribute("data-track", option.value),
			onValueChange: (value) => {
				const media = this.#mediaState.value;
				if (media) this.#core.selectValue(media, value);
			}
		});
	}
	static {
		this.tagName = "media-captions-radio-group";
	}
	static {
		this.properties = {
			...MenuRadioGroupElement.properties,
			disabled: { type: Boolean },
			label: { type: String }
		};
	}
	#core;
	#i18n;
	#mediaState;
	#options;
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		if (!this.#mediaState.value && this.#mediaState.displayName) logMissingFeature(this.localName, this.#mediaState.displayName);
	}
	update(changed) {
		const media = this.#mediaState.value;
		let state = null;
		if (media) {
			this.#core.setProps({
				formatTrack: this.formatTrack,
				disabled: this.disabled,
				label: this.label
			});
			this.#core.setMedia(media);
			state = this.#core.getState();
			this.applyDefaultAriaLabel(translateText(this.#core.getLabel(state), this.#i18n.value));
			this.#options.sync(state, this.#i18n.value, this.#i18n.locale);
			this.publishMenuTriggerState(state.disabled, state.availability);
		}
		super.update(changed);
		if (state) applyStateDataAttrs(this, state, CaptionsRadioGroupDataAttrs);
	}
};

//#endregion
//#region src/ui/cast-button/cast-button-element.ts
var CastButtonElement = class extends MediaButtonElement {
	constructor(..._args) {
		super(..._args);
		this.core = new CastButtonCore();
		this.stateAttrMap = CastButtonDataAttrs;
		this.mediaState = new PlayerController(this, playerContext, selectRemotePlayback);
	}
	static {
		this.tagName = "media-cast-button";
	}
	activate(state) {
		return this.core.toggle(state);
	}
};

//#endregion
//#region src/ui/fullscreen-button/fullscreen-button-element.ts
var FullscreenButtonElement = class extends MediaButtonElement {
	constructor(..._args) {
		super(..._args);
		this.core = new FullscreenButtonCore();
		this.stateAttrMap = FullscreenButtonDataAttrs;
		this.mediaState = new PlayerController(this, playerContext, selectFullscreen);
		this.hotkeyAction = "toggleFullscreen";
	}
	static {
		this.tagName = "media-fullscreen-button";
	}
	activate(state) {
		return this.core.toggle(state);
	}
};

//#endregion
//#region src/ui/pip-button/pip-button-element.ts
var PiPButtonElement = class extends MediaButtonElement {
	constructor(..._args) {
		super(..._args);
		this.core = new PiPButtonCore();
		this.stateAttrMap = PiPButtonDataAttrs;
		this.mediaState = new PlayerController(this, playerContext, selectPiP);
		this.hotkeyAction = "togglePictureInPicture";
	}
	static {
		this.tagName = "media-pip-button";
	}
	activate(state) {
		return this.core.toggle(state);
	}
};

//#endregion
//#region src/ui/poster/poster-element.ts
/** What an element composes: whatever fills a slot, or the element's own children. */
function composedChildren(element) {
	if (element instanceof HTMLSlotElement) {
		const assigned = element.assignedElements();
		if (assigned.length > 0) return assigned;
	}
	return [...element.children];
}
/**
* The first image an element composes. A skin forwards its own `<slot name="poster">` in, so the image is a slot or two
* down and may be wrapped in a `<picture>` or a framework image component.
*/
function findImage(element) {
	for (const child of composedChildren(element)) {
		if (child instanceof HTMLImageElement) return child;
		const nested = findImage(child);
		if (nested) return nested;
	}
	return null;
}
/**
* Whether anything already points this image somewhere, which answers both whether the author owns the source and
* whether there is a download to wait for. A `<source>` counts: inside a `<picture>` it can win over the `src`.
*/
function hasSource(img) {
	if (img.hasAttribute("src") || img.hasAttribute("srcset")) return true;
	const parent = img.parentElement;
	return parent?.localName === "picture" && parent.querySelector("source") !== null;
}
/**
* Whether `complete` on this image describes a request. It is also true for one that omits both `src` and `srcset`,
* whatever a parent `<picture>` is fetching on its behalf, so only an image sourced from its own attributes can be
* read.
*/
function hasOwnSource(img) {
	return !!img.getAttribute("src") || img.hasAttribute("srcset");
}
/**
* `<media-poster>` — sets `src` on a poster image it does not own.
*
* The image is a child, as in `<picture>`, but sourcing runs the other way around: `<picture>` treats the `src` on its
* `<img>` as the fallback, while here an image with no source of its own is the one this element fills in. Give the
* child a `src`, a `srcset`, or `<source>` candidates and it is yours, left alone.
*
* Renders no image of its own, so include one: `<media-poster><img alt=""></media-poster>`. Inside a skin, an `<img
* slot="poster">` of yours replaces the one the skin carries.
*/
var PosterElement = class extends UIElement {
	static {
		this.tagName = "media-poster";
	}
	#core = new PosterCore();
	#children = new MutationObserver(() => this.requestUpdate());
	#playback = new PlayerController(this, playerContext, selectPlayback);
	#metadata = new PlayerController(this, playerContext, selectMetadata);
	#image = null;
	/** Whether `#image` had no source of its own when it became active. */
	#owned = false;
	#imageLoadState = "pending";
	#imageEvents = null;
	#disconnect = null;
	#warnedMissingImage = false;
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		this.#disconnect = new AbortController();
		const { signal } = this.#disconnect;
		this.addEventListener("slotchange", () => this.requestUpdate(), { signal });
		this.#children.observe(this, {
			childList: true,
			subtree: true
		});
		if (!this.#playback.value) logMissingFeature(this.localName, this.#playback.displayName ?? "playback");
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#adopt(null);
		this.#children.disconnect();
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	get #loadState() {
		if (!this.#image || !hasSource(this.#image)) return "none";
		return this.#imageLoadState === "pending" ? "loading" : this.#imageLoadState;
	}
	update(changed) {
		super.update(changed);
		const playback = this.#playback.value;
		if (!playback) return;
		this.#core.setMedia({
			started: playback.started,
			poster: this.#metadata.value?.poster ?? ""
		});
		const { src } = this.#core.getState();
		this.#adopt(findImage(this));
		this.#applySource(src);
		this.#core.setImageLoadState(this.#loadState);
		applyStateDataAttrs(this, this.#core.getState(), PosterDataAttrs);
	}
	/**
	* Ownership is settled once, when an image becomes active: after the first fill the `src` we set would itself look
	* authored. Re-slot an image with a source to hand it back, the way React decides a field is controlled at mount.
	*/
	#adopt(next) {
		if (next === this.#image) return;
		if (this.#owned) this.#image?.removeAttribute("src");
		this.#imageEvents?.abort();
		this.#imageEvents = null;
		this.#image = next;
		this.#owned = next !== null && !hasSource(next);
		this.#imageLoadState = "pending";
		if (!next) return;
		if (next.naturalWidth > 0) this.#imageLoadState = "loaded";
		else if (next.complete && hasOwnSource(next)) this.#imageLoadState = "error";
		this.#imageEvents = new AbortController();
		const { signal } = this.#imageEvents;
		const settle = (loadState) => () => {
			this.#imageLoadState = loadState;
			this.requestUpdate();
		};
		next.addEventListener("load", settle("loaded"), { signal });
		next.addEventListener("error", settle("error"), { signal });
	}
	#applySource(src) {
		const img = this.#image;
		if (!img) {
			if (src && !this.#warnedMissingImage) {
				this.#warnedMissingImage = true;
				console.warn(`<${this.localName}> resolved a poster but has no image to put it in. Add one as a child: <${this.localName}><img alt=""></${this.localName}>`);
			}
			return;
		}
		if (!this.#owned) return;
		if (!src) {
			this.#imageLoadState = "pending";
			img.removeAttribute("src");
		} else if (img.getAttribute("src") !== src) {
			this.#imageLoadState = "pending";
			img.setAttribute("src", src);
		}
	}
};

//#endregion
export { CaptionsRadioGroupElement as a, CastButtonElement as i, PiPButtonElement as n, CaptionsButtonElement as o, FullscreenButtonElement as r, AirPlayButtonElement as s, PosterElement as t };
//# sourceMappingURL=poster-element-DNHQiv_s.js.map