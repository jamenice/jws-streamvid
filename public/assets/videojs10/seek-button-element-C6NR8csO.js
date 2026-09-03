import { u as isUndefined } from "./predicate-DrcmolBs.js";
import { C as resolveLabel, E as applyElementProps, F as defaults, T as logMissingFeature, _ as MenuRadioGroupElement, b as rateText, o as MediaButtonElement } from "./popover-element-DYgnWFFt.js";
import { r as resolveText$1 } from "./translate-text-BWvoW5-r.js";
import { r as playerContext } from "./context-DlE_3NHA.js";
import { l as createState, n as PlayerController } from "./create-player-Duo2X4dJ.js";
import { _ as selectTime, k as i18nContext, p as selectPlaybackRate, r as applyStateDataAttrs, y as I18nController } from "./container-element-DGG9TQ6W.js";
import { s as playbackRateText, t as RadioOptionsController } from "./radio-options-controller-C3ZBWVO0.js";
import { translateText } from "./i18n.dev.js";

//#region ../core/dist/dev/core/ui/playback-rate-button/core.js
var PlaybackRateButtonCore = class PlaybackRateButtonCore {
	static defaultProps = {
		label: "",
		disabled: false,
		menuTrigger: false
	};
	state = createState({
		rate: 1,
		label: ""
	});
	#props = { ...PlaybackRateButtonCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, PlaybackRateButtonCore.defaultProps);
	}
	getLabel(state) {
		const custom = resolveLabel(this.#props.label, state);
		if (custom !== void 0) return custom;
		return rateText;
	}
	getLabelParams(state) {
		if (resolveLabel(this.#props.label, state) !== void 0) return void 0;
		return { rate: state.rate };
	}
	getAttrs(state) {
		return {
			"aria-label": this.getLabel(state),
			"aria-disabled": this.#props.disabled ? "true" : void 0
		};
	}
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		const media = this.#media;
		this.state.patch({ rate: media.playbackRate });
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	cycle(media) {
		if (this.#props.disabled) return;
		if (this.#props.menuTrigger) return;
		const { playbackRates, playbackRate } = media;
		if (playbackRates.length === 0) return;
		const idx = playbackRates.indexOf(playbackRate);
		const next = idx === -1 ? playbackRates.find((r) => r > playbackRate) ?? playbackRates[0] : playbackRates[(idx + 1) % playbackRates.length];
		media.setPlaybackRate(next);
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/playback-rate-button/data.js
const PlaybackRateButtonDataAttrs = { 
/** Current playback rate. */
rate: "data-rate" };

//#endregion
//#region ../core/dist/dev/core/ui/playback-rate-radio-group/core.js
function formatPlaybackRate(rate) {
	return `${rate}×`;
}
var PlaybackRateRadioGroupCore = class PlaybackRateRadioGroupCore {
	static defaultProps = {
		label: "",
		formatRate: formatPlaybackRate,
		disabled: false
	};
	state = createState({
		rate: 1,
		value: "1",
		options: [],
		disabled: true,
		hidden: true,
		availability: "unavailable",
		label: ""
	});
	#props = { ...PlaybackRateRadioGroupCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, PlaybackRateRadioGroupCore.defaultProps);
	}
	getLabel(state) {
		const custom = resolveLabel(this.#props.label, state);
		if (custom !== void 0) return custom;
		return playbackRateText;
	}
	getLabelParams(_state) {}
	getRateLabel(rate) {
		return this.#props.formatRate(rate);
	}
	getRateValue(rate) {
		return String(rate);
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
		const availability = media.playbackRates.length > 0 ? "available" : "unavailable";
		this.state.patch({
			rate: media.playbackRate,
			value: this.getRateValue(media.playbackRate),
			options: media.playbackRates.map((rate) => ({
				rate,
				value: this.getRateValue(rate),
				label: this.getRateLabel(rate),
				disabled: false
			})),
			disabled: this.#props.disabled || media.playbackRates.length === 0,
			hidden: availability === "unavailable",
			availability
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	select(media, rate) {
		if (this.#props.disabled) return;
		if (!media.playbackRates.includes(rate)) return;
		media.setPlaybackRate(rate);
	}
	selectValue(media, value) {
		const rate = media.playbackRates.find((candidate) => this.getRateValue(candidate) === value);
		if (isUndefined(rate)) return;
		this.select(media, rate);
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/playback-rate-radio-group/data.js
const PlaybackRateRadioGroupDataAttrs = {
	/** Current playback rate. */
	rate: "data-rate",
	/** Present when playback rate selection is disabled. */
	disabled: "data-disabled",
	/** Present when playback rate selection is unavailable. */
	hidden: "data-hidden",
	/** Indicates playback rate availability (`available` or `unavailable`). */
	availability: "data-availability"
};

//#endregion
//#region ../core/dist/dev/i18n/text/seek.js
const prefix = "seek.";
const forwardText = {
	key: `${prefix}forward`,
	text: "Seek forward {seconds} seconds"
};
const backwardText = {
	key: `${prefix}backward`,
	text: "Seek backward {seconds} seconds"
};

//#endregion
//#region ../core/dist/dev/core/ui/seek-button/core.js
var SeekButtonCore = class SeekButtonCore {
	static defaultProps = {
		seconds: 30,
		label: "",
		disabled: false
	};
	state = createState({
		seeking: false,
		direction: "forward",
		label: ""
	});
	#props = { ...SeekButtonCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, SeekButtonCore.defaultProps);
	}
	getLabel(state) {
		const custom = resolveLabel(this.#props.label, state);
		if (custom !== void 0) return custom;
		return state.direction === "backward" ? backwardText : forwardText;
	}
	getLabelParams(state) {
		if (resolveLabel(this.#props.label, state) !== void 0) return void 0;
		return { seconds: Math.abs(this.#props.seconds) };
	}
	getAttrs(state) {
		return {
			"aria-label": this.getLabel(state),
			"aria-disabled": this.#props.disabled ? "true" : void 0
		};
	}
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		const media = this.#media;
		const direction = this.#props.seconds < 0 ? "backward" : "forward";
		this.state.patch({
			seeking: media.seeking,
			direction
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	async seek(media) {
		if (this.#props.disabled) return;
		await media.seek(media.currentTime + this.#props.seconds);
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/seek-button/data.js
const SeekButtonDataAttrs = {
	/** Present when a seek is in progress. */
	seeking: "data-seeking",
	/** Indicates the seek direction: `"forward"` or `"backward"`. */
	direction: "data-direction"
};

//#endregion
//#region src/ui/playback-rate-button/playback-rate-button-element.ts
var PlaybackRateButtonElement = class extends MediaButtonElement {
	constructor(..._args) {
		super(..._args);
		this.commandfor = void 0;
		this.core = new PlaybackRateButtonCore();
		this.stateAttrMap = PlaybackRateButtonDataAttrs;
		this.mediaState = new PlayerController(this, playerContext, selectPlaybackRate);
		this.hotkeyAction = "speedUp";
	}
	static {
		this.tagName = "media-playback-rate-button";
	}
	static {
		this.properties = {
			label: { type: String },
			disabled: { type: Boolean },
			commandfor: { type: String }
		};
	}
	activate(state, event) {
		if (this.commandfor) {
			if (event instanceof KeyboardEvent) this.click();
			return;
		}
		this.core.cycle(state);
	}
	getIsButtonDisabled() {
		const media = this.mediaState.value;
		if (super.getIsButtonDisabled()) return true;
		if (this.commandfor && media && media.playbackRates.length === 0) return true;
		return false;
	}
	willUpdate(changed) {
		super.willUpdate(changed);
		if (changed.has("commandfor")) if (this.commandfor) this.setAttribute("commandfor", this.commandfor);
		else this.removeAttribute("commandfor");
	}
	update(changed) {
		super.update(changed);
		if (!this.mediaState.value || !this.commandfor) return;
		applyElementProps(this, { "aria-disabled": this.getIsButtonDisabled() ? "true" : void 0 });
	}
};

//#endregion
//#region src/ui/playback-rate-radio-group/playback-rate-radio-group-element.ts
var PlaybackRateRadioGroupElement = class extends MenuRadioGroupElement {
	constructor(..._args) {
		super(..._args);
		this.disabled = false;
		this.formatRate = PlaybackRateRadioGroupCore.defaultProps.formatRate;
		this.#core = new PlaybackRateRadioGroupCore();
		this.#i18n = new I18nController(this, i18nContext);
		this.#mediaState = new PlayerController(this, playerContext, selectPlaybackRate);
		this.#options = new RadioOptionsController(this, {
			setItemAttributes: (item, option) => item.setAttribute("data-rate", option.value),
			onValueChange: (value) => {
				const media = this.#mediaState.value;
				if (media) this.#core.selectValue(media, value);
			}
		});
	}
	static {
		this.tagName = "media-playback-rate-radio-group";
	}
	static {
		this.properties = {
			...MenuRadioGroupElement.properties,
			disabled: { type: Boolean }
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
				formatRate: this.formatRate,
				disabled: this.disabled
			});
			this.#core.setMedia(media);
			state = this.#core.getState();
			this.applyDefaultAriaLabel(translateText(this.#core.getLabel(state), this.#i18n.value, this.#core.getLabelParams(state)));
			this.#options.sync(state, this.#i18n.value, this.#i18n.locale);
			this.publishMenuTriggerState(state.disabled, state.availability);
		}
		super.update(changed);
		if (state) applyStateDataAttrs(this, state, PlaybackRateRadioGroupDataAttrs);
	}
};

//#endregion
//#region src/ui/seek-button/seek-button-element.ts
var SeekButtonElement = class extends MediaButtonElement {
	constructor(..._args) {
		super(..._args);
		this.seconds = SeekButtonCore.defaultProps.seconds;
		this.core = new SeekButtonCore();
		this.stateAttrMap = SeekButtonDataAttrs;
		this.mediaState = new PlayerController(this, playerContext, selectTime);
		this.hotkeyAction = "seekStep";
	}
	static {
		this.tagName = "media-seek-button";
	}
	static {
		this.properties = {
			...MediaButtonElement.properties,
			seconds: { type: Number }
		};
	}
	get hotkeyValue() {
		return this.seconds;
	}
	activate(state) {
		this.core.seek(state);
	}
};

//#endregion
export { PlaybackRateRadioGroupElement as n, PlaybackRateButtonElement as r, SeekButtonElement as t };
//# sourceMappingURL=seek-button-element-C6NR8csO.js.map