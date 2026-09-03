import { C as resolveLabel, D as findLastIndexAtOrBefore, F as defaults, N as getTemplateElement, O as toPercent, P as getTemplateRoot, T as logMissingFeature, _ as MenuRadioGroupElement, g as sliderContext, j as cloneTemplateRoot } from "./popover-element-DYgnWFFt.js";
import { a as escapeHtml } from "./attributes-C0ssqa1e.js";
import { n as I18nProviderElement, t as TextElement } from "./text-element-CYeCUUcj.js";
import { r as resolveText$1 } from "./translate-text-BWvoW5-r.js";
import { n as ContextConsumer, t as UIElement } from "./ui-element-DraIA8Ee.js";
import { i as safeDefine, r as playerContext } from "./context-DlE_3NHA.js";
import { l as createState, n as PlayerController } from "./create-player-Duo2X4dJ.js";
import { _ as selectTime, a as selectBuffer, g as selectTextTrack, i as selectAudioTrack, k as i18nContext, m as selectQuality, r as applyStateDataAttrs, y as I18nController } from "./container-element-DGG9TQ6W.js";
import { c as qualityText, i as autoWithLabelText, n as audioText, r as autoText, t as RadioOptionsController } from "./radio-options-controller-C3ZBWVO0.js";
import { translateText } from "./i18n.dev.js";

//#region src/define/i18n.ts
safeDefine(I18nProviderElement);
safeDefine(TextElement);

//#endregion
//#region ../utils/dist/array/find-range-at.js
/** Finds the ordered range containing the target value. */
function findRangeAt(ranges, value, getStart, getEnd) {
	const index = findLastIndexAtOrBefore(ranges, value, getStart);
	if (index < 0) return void 0;
	const range = ranges[index];
	const end = getEnd(range);
	const last = index === ranges.length - 1;
	return value < end || last && value === end ? range : void 0;
}

//#endregion
//#region src/i18n/render-text.ts
/** Render a text descriptor as keyed media-text markup. */
function renderText(text, attrs) {
	const attrText = Object.entries(attrs ?? {}).map(([key, value]) => ` ${key}="${escapeHtml(value)}"`).join("");
	return `<media-text token="${escapeHtml(text.key)}"${attrText}>${escapeHtml(text.text)}</media-text>`;
}

//#endregion
//#region ../core/dist/dev/core/ui/audio-track-radio-group/core.js
function formatTrackLabel(track) {
	if (track.label) return track.label;
	if (track.language) return track.language;
	if (track.kind) return track.kind;
	return audioText;
}
function getTrackValue(track, index) {
	return track.id || String(index);
}
var AudioTrackRadioGroupCore = class AudioTrackRadioGroupCore {
	static defaultProps = {
		label: "",
		formatTrack: formatTrackLabel,
		disabled: false
	};
	state = createState({
		options: [],
		value: "",
		disabled: true,
		hidden: true,
		availability: "unavailable",
		label: ""
	});
	#props = { ...AudioTrackRadioGroupCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, AudioTrackRadioGroupCore.defaultProps);
	}
	getLabel(state) {
		const label = resolveLabel(this.#props.label, state);
		if (label) return label;
		return audioText;
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
		const enabledIndex = media.audioTrackList.findIndex((track) => track.enabled);
		const options = media.audioTrackList.map((track, index) => ({
			value: getTrackValue(track, index),
			label: this.getTrackLabel(track),
			disabled: false
		}));
		const availability = options.length > 1 ? "available" : "unavailable";
		this.state.patch({
			options,
			value: enabledIndex === -1 ? "" : getTrackValue(media.audioTrackList[enabledIndex], enabledIndex),
			disabled: this.#props.disabled || availability === "unavailable",
			hidden: availability === "unavailable",
			availability
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	select(media, value) {
		if (this.#props.disabled) return;
		if (!media.audioTrackList.some((track, index) => getTrackValue(track, index) === value)) return;
		media.selectAudioTrack(value);
	}
	selectValue(media, value) {
		this.select(media, value);
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/audio-track-radio-group/data.js
const AudioTrackRadioGroupDataAttrs = {
	/** Current audio track value. */
	value: "data-audio-track",
	/** Present when audio track selection is disabled. */
	disabled: "data-disabled",
	/** Present when audio track selection is unavailable. */
	hidden: "data-hidden",
	/** Indicates audio track availability (`available` or `unavailable`). */
	availability: "data-availability"
};

//#endregion
//#region ../core/dist/dev/core/ui/quality-radio-group/core.js
const QUALITY_AUTO_VALUE = "auto";
const STANDARD_RENDITION_SIZES = [
	4320,
	2160,
	1440,
	1080,
	720,
	480,
	360,
	240
];
function formatBitrate(bitrate) {
	return bitrate >= 1e6 ? `${Math.round(bitrate / 1e5) / 10} Mbps` : `${Math.round(bitrate / 1e3)} kbps`;
}
function getWidescreenSize(width) {
	const size = Math.round(width * 9 / 16);
	return STANDARD_RENDITION_SIZES.includes(size) ? size : void 0;
}
function getRenditionSize(rendition) {
	const { width, height } = rendition;
	if (width && height) {
		if (width > height && width * 9 > height * 16) return getWidescreenSize(width) ?? height;
		return Math.min(width, height);
	}
	if (height) return height;
	if (width) return getWidescreenSize(width) ?? width;
}
function hasSameSize(rendition, renditions) {
	const size = getRenditionSize(rendition);
	return Boolean(size && renditions.some((other) => other !== rendition && getRenditionSize(other) === size));
}
function formatRenditionLabel(rendition) {
	const size = getRenditionSize(rendition);
	if (size) return `${size}p`;
	if (rendition.bitrate) return formatBitrate(rendition.bitrate);
	return qualityText;
}
function formatRenditionBadge(rendition, renditions = []) {
	if (!getRenditionSize(rendition) || !rendition.bitrate || !hasSameSize(rendition, renditions)) return void 0;
	return formatBitrate(rendition.bitrate);
}
function formatRenditionTier(rendition) {
	const size = getRenditionSize(rendition);
	if (!size) return void 0;
	if (size >= 4320) return "8K";
	if (size >= 2160) return "4K";
	if (size >= 1080) return "HD";
}
function getRenditionValue(rendition, index) {
	return rendition.id || String(index);
}
function isSameRendition(a, b) {
	if (a.id !== void 0 || b.id !== void 0) return a.id === b.id;
	return a.width === b.width && a.height === b.height && a.bitrate === b.bitrate && a.frameRate === b.frameRate && a.codec === b.codec;
}
var QualityRadioGroupCore = class QualityRadioGroupCore {
	static defaultProps = {
		label: "",
		formatRendition: formatRenditionLabel,
		disabled: false
	};
	state = createState({
		options: [{
			value: QUALITY_AUTO_VALUE,
			label: autoText,
			disabled: false
		}],
		value: QUALITY_AUTO_VALUE,
		disabled: true,
		hidden: true,
		availability: "unavailable",
		label: ""
	});
	#props = { ...QualityRadioGroupCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, QualityRadioGroupCore.defaultProps);
	}
	getLabel(state) {
		const label = resolveLabel(this.#props.label, state);
		if (label) return label;
		return qualityText;
	}
	getRenditionLabel(rendition) {
		if (this.#props.formatRendition !== QualityRadioGroupCore.defaultProps.formatRendition) return this.#props.formatRendition(rendition);
		return formatRenditionLabel(rendition);
	}
	getRenditionBadge(rendition, renditions = []) {
		if (this.#props.formatRendition !== QualityRadioGroupCore.defaultProps.formatRendition) return void 0;
		return formatRenditionBadge(rendition, renditions);
	}
	getRenditionTier(rendition) {
		if (this.#props.formatRendition !== QualityRadioGroupCore.defaultProps.formatRendition) return void 0;
		return formatRenditionTier(rendition);
	}
	getRenditionValue(rendition, index) {
		return getRenditionValue(rendition, index);
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
		const selectedIndex = media.videoRenditionList.findIndex((rendition) => rendition.selected);
		const availability = media.videoRenditionList.length > 1 ? "available" : "unavailable";
		const toOption = (rendition, index) => {
			const tier = this.getRenditionTier(rendition);
			const badge = this.getRenditionBadge(rendition, media.videoRenditionList);
			return {
				value: this.getRenditionValue(rendition, index),
				label: this.getRenditionLabel(rendition),
				disabled: false,
				...tier && { tier },
				...badge && { badge }
			};
		};
		const activeIndex = media.activeVideoRendition === null ? -1 : media.videoRenditionList.findIndex((rendition) => isSameRendition(rendition, media.activeVideoRendition));
		const active = media.activeVideoRendition && activeIndex !== -1 ? toOption(media.activeVideoRendition, activeIndex) : void 0;
		const autoOption = {
			value: QUALITY_AUTO_VALUE,
			label: selectedIndex === -1 && active ? autoWithLabelText : autoText,
			disabled: false,
			...selectedIndex === -1 && active && { labelParams: { label: resolveText$1(active.label) } }
		};
		this.state.patch({
			options: [autoOption, ...media.videoRenditionList.map(toOption)],
			value: selectedIndex === -1 ? QUALITY_AUTO_VALUE : this.getRenditionValue(media.videoRenditionList[selectedIndex], selectedIndex),
			disabled: this.#props.disabled || availability === "unavailable",
			hidden: availability === "unavailable",
			availability
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	select(media, value) {
		if (this.#props.disabled) return;
		if (value === "auto") {
			media.selectVideoRendition(value);
			return;
		}
		if (!media.videoRenditionList.some((rendition, index) => this.getRenditionValue(rendition, index) === value)) return;
		media.selectVideoRendition(value);
	}
	selectValue(media, value) {
		this.select(media, value);
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/quality-radio-group/data.js
const QualityRadioGroupDataAttrs = {
	/** Current quality value. */
	value: "data-quality",
	/** Present when quality selection is disabled. */
	disabled: "data-disabled",
	/** Present when quality selection is unavailable. */
	hidden: "data-hidden",
	/** Indicates quality availability (`available` or `unavailable`). */
	availability: "data-availability"
};

//#endregion
//#region ../core/dist/dev/core/ui/slider/segments-core.js
/** Localizes ordered numeric ranges into slider geometry and interaction state. */
var SliderSegmentsCore = class {
	getGeometry(input) {
		const { ranges, min, max, orientation } = input;
		const domain = max - min;
		if (!Number.isFinite(domain) || domain <= 0) return [];
		const valid = ranges.filter((segment) => {
			const size = (segment.end - segment.start) / domain;
			const offset = (segment.start - min) / domain;
			return Number.isFinite(size) && Number.isFinite(offset) && size > 0;
		});
		return valid.map((segment, index) => {
			const offset = (segment.start - min) / domain;
			const size = (segment.end - segment.start) / domain;
			const segmentSize = `${size * 100}%`;
			return {
				...segment,
				index,
				last: index === valid.length - 1,
				orientation,
				width: orientation === "horizontal" ? segmentSize : void 0,
				height: orientation === "vertical" ? segmentSize : void 0,
				startPercent: `${offset * 100}%`,
				endPercent: `${(offset + size) * 100}%`
			};
		});
	}
	getState(segment, slider, pointerValue) {
		const { last, ...geometry } = segment;
		const contains = (value) => value >= segment.start && (value < segment.end || last && value === segment.end);
		const active = contains(slider.value);
		const pointing = slider.pointing && contains(pointerValue);
		const dragging = slider.dragging && contains(pointerValue);
		const focused = slider.interactive && !slider.pointing && !slider.dragging;
		return {
			...geometry,
			fillPercent: toPercent(slider.value, segment.start, segment.end),
			active,
			pointing,
			dragging,
			highlighted: segment.highlight !== false && pointing,
			interactive: pointing || dragging || focused && active
		};
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/time-slider/time-slider-chapters/core.js
const cueKeys = /* @__PURE__ */ new WeakMap();
let cueKey = 0;
function getCueKey(cue) {
	let key = cueKeys.get(cue);
	if (!key) {
		const id = cue.id;
		key = `cue-${typeof id === "string" && id ? `${id}-` : ""}${cueKey++}`;
		cueKeys.set(cue, key);
	}
	return key;
}
/** Produces an ordered, non-overlapping, contiguous partition of the slider domain. */
function normalizeChapterCues(cues, min, max) {
	if (!Number.isFinite(min) || !Number.isFinite(max) || max <= min) return [];
	const sorted = cues.map((cue, index) => ({
		cue,
		index,
		key: getCueKey(cue)
	})).filter(({ cue }) => Number.isFinite(cue.startTime) && Number.isFinite(cue.endTime)).sort((a, b) => a.cue.startTime - b.cue.startTime || a.index - b.index);
	const chapters = [];
	let end = min;
	let previousKey = "start";
	for (const { cue, key } of sorted) {
		const start = Math.max(min, cue.startTime);
		const cueEnd = Math.min(max, cue.endTime);
		if (cueEnd <= start) continue;
		if (start > end) chapters.push({
			key: `gap-${previousKey}-${key}`,
			start: end,
			end: start,
			cue: null
		});
		const segmentStart = Math.max(start, end);
		if (cueEnd <= segmentStart) continue;
		chapters.push({
			key,
			start: segmentStart,
			end: cueEnd,
			cue
		});
		end = cueEnd;
		previousKey = key;
	}
	if (chapters.length === 0) return [{
		key: "gap-start-end",
		start: min,
		end: max,
		cue: null
	}];
	if (end < max) chapters.push({
		key: `gap-${previousKey}-end`,
		start: end,
		end: max,
		cue: null
	});
	return chapters;
}
/** Prepares chapter ranges and state for platform renderers. */
var TimeSliderChaptersCore = class {
	#cues = null;
	#min = 0;
	#max = 0;
	#result = null;
	getRanges(cues, min, max) {
		if (this.#result && (this.#cues === cues || this.#cues?.length === 0 && cues.length === 0) && this.#min === min && this.#max === max) return this.#result;
		const hasRange = max > min;
		const rangeMax = hasRange ? max : min + 1;
		const chapters = normalizeChapterCues(hasRange ? cues : [], min, rangeMax);
		const ranges = chapters.map(({ key, start, end, cue }) => ({
			key,
			start,
			end,
			highlight: cue !== null
		}));
		this.#cues = cues;
		this.#min = min;
		this.#max = max;
		this.#result = {
			chapters,
			ranges,
			max: rangeMax
		};
		return this.#result;
	}
	findChapter(chapters, value) {
		return findRangeAt(chapters, value, (chapter) => chapter.start, (chapter) => chapter.end);
	}
	getState(segment, chapters, bufferedEnd) {
		return {
			...segment,
			cue: chapters[segment.index]?.cue ?? null,
			bufferPercent: toPercent(bufferedEnd, segment.start, segment.end)
		};
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/time-slider/time-slider-chapters/data.js
const TimeSliderChapterDataAttrs = {
	/** Present when playback is within the chapter. */
	active: "data-active",
	/** Present when pointer interaction highlights the chapter. */
	highlighted: "data-highlighted"
};

//#endregion
//#region ../core/dist/dev/core/ui/time-slider/time-slider-chapters/vars.js
/** CSS geometry and progress local to each chapter. */
const TimeSliderChapterCSSVars = {
	start: "--media-slider-chapter-start",
	end: "--media-slider-chapter-end",
	width: "--media-slider-chapter-width",
	fill: "--media-slider-chapter-fill",
	buffer: "--media-slider-chapter-buffer"
};

//#endregion
//#region src/ui/audio-track-radio-group/audio-track-radio-group-element.ts
var AudioTrackRadioGroupElement = class extends MenuRadioGroupElement {
	constructor(..._args) {
		super(..._args);
		this.disabled = false;
		this.label = "";
		this.formatTrack = AudioTrackRadioGroupCore.defaultProps.formatTrack;
		this.#core = new AudioTrackRadioGroupCore();
		this.#i18n = new I18nController(this, i18nContext);
		this.#mediaState = new PlayerController(this, playerContext, selectAudioTrack);
		this.#options = new RadioOptionsController(this, {
			setItemAttributes: (item, option) => item.setAttribute("data-track", option.value),
			onValueChange: (value) => {
				const media = this.#mediaState.value;
				if (media) this.#core.selectValue(media, value);
			}
		});
	}
	static {
		this.tagName = "media-audio-track-radio-group";
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
		if (state) applyStateDataAttrs(this, state, AudioTrackRadioGroupDataAttrs);
	}
};

//#endregion
//#region src/ui/quality-radio-group/quality-radio-group-element.ts
var QualityRadioGroupElement = class extends MenuRadioGroupElement {
	constructor(..._args) {
		super(..._args);
		this.disabled = false;
		this.label = "";
		this.formatRendition = QualityRadioGroupCore.defaultProps.formatRendition;
		this.#core = new QualityRadioGroupCore();
		this.#i18n = new I18nController(this, i18nContext);
		this.#mediaState = new PlayerController(this, playerContext, selectQuality);
		this.#options = new RadioOptionsController(this, {
			renderItem: (item, label, option) => this.#setContent(item, label, option.tier, option.badge),
			setItemAttributes: (item, option) => item.setAttribute("data-rendition", option.value),
			getOptionCacheKey: (option) => `${option.tier ?? ""}:${option.badge ?? ""}`,
			onValueChange: (value) => {
				const media = this.#mediaState.value;
				if (media) this.#core.selectValue(media, value);
			}
		});
	}
	static {
		this.tagName = "media-quality-radio-group";
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
				formatRendition: this.formatRendition,
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
		if (state) applyStateDataAttrs(this, state, QualityRadioGroupDataAttrs);
	}
	#setContent(item, label, tier, badge) {
		const labelPart = item.querySelector("[data-part~=\"label\"]");
		const tierPart = item.querySelector("[data-part~=\"tier\"]");
		const badgePart = item.querySelector("[data-part~=\"badge\"]");
		if (labelPart) labelPart.textContent = label;
		if (tierPart) {
			tierPart.textContent = tier ?? "";
			tierPart.hidden = !tier;
		}
		if (badgePart) {
			badgePart.textContent = badge ?? "";
			badgePart.hidden = !badge;
		}
		if (!labelPart && !tierPart && !badgePart) item.textContent = [
			label,
			tier,
			badge
		].filter(Boolean).join(" ");
	}
};

//#endregion
//#region src/ui/time-slider/time-slider-chapters/time-slider-chapter-title-element.ts
/** Displays the chapter title at the current pointer or keyboard position. */
var TimeSliderChapterTitleElement = class extends UIElement {
	static {
		this.tagName = "media-time-slider-chapter-title";
	}
	#core = new TimeSliderChaptersCore();
	#slider = new ContextConsumer(this, {
		context: sliderContext,
		subscribe: true
	});
	#textTrack = new PlayerController(this, playerContext, selectTextTrack);
	#time = new PlayerController(this, playerContext, selectTime);
	update(_changed) {
		super.update(_changed);
		const slider = this.#slider.value;
		if (!slider) return;
		const duration = this.#time.value?.duration ?? 0;
		const { chapters } = this.#core.getRanges(this.#textTrack.value?.chaptersCues ?? [], 0, duration);
		const keyboard = slider.state.interactive && !slider.state.pointing && !slider.state.dragging;
		const value = slider.state.pointing || slider.state.dragging ? slider.pointerValue : slider.state.value;
		const chapter = this.#core.findChapter(chapters, value);
		this.textContent = chapter?.cue?.text ?? "";
		if (keyboard) {
			this.removeAttribute("aria-hidden");
			this.setAttribute("aria-live", "polite");
		} else {
			this.setAttribute("aria-hidden", "true");
			this.removeAttribute("aria-live");
		}
	}
};

//#endregion
//#region src/ui/time-slider/time-slider-chapters/time-slider-chapters-element.ts
/**
* Clones a light-DOM template once per normalized chapter range.
*
* The required template must contain exactly one HTML root element. When no chapter cues are available, the template is
* cloned once for a full-duration range.
*/
var TimeSliderChaptersElement = class extends UIElement {
	static {
		this.tagName = "media-time-slider-chapters";
	}
	#segments = new SliderSegmentsCore();
	#core = new TimeSliderChaptersCore();
	#slider = new ContextConsumer(this, {
		context: sliderContext,
		subscribe: true
	});
	#textTrack = new PlayerController(this, playerContext, selectTextTrack);
	#buffer = new PlayerController(this, playerContext, selectBuffer);
	#time = new PlayerController(this, playerContext, selectTime);
	#rendered = /* @__PURE__ */ new Map();
	#templateRoot = null;
	#templateChecked = false;
	connectedCallback() {
		super.connectedCallback();
		this.setAttribute("aria-hidden", "true");
	}
	update(_changed) {
		super.update(_changed);
		const slider = this.#slider.value;
		const duration = this.#time.value?.duration ?? 0;
		const templateRoot = this.#getTemplateRoot();
		if (!slider) return;
		applyStateDataAttrs(this, slider.state, slider.stateAttrMap);
		if (!templateRoot) return;
		const { chapters, ranges, max } = this.#core.getRanges(this.#textTrack.value?.chaptersCues ?? [], 0, duration);
		const geometry = this.#segments.getGeometry({
			ranges,
			min: 0,
			max,
			orientation: slider.state.orientation
		});
		const buffered = this.#buffer.value?.buffered ?? [];
		const bufferedEnd = buffered.length ? buffered[buffered.length - 1][1] : 0;
		const next = /* @__PURE__ */ new Map();
		for (const segment of geometry) {
			const state = this.#core.getState(this.#segments.getState(segment, slider.state, slider.pointerValue), chapters, bufferedEnd);
			let root = this.#rendered.get(state.key);
			if (!root) root = cloneTemplateRoot(templateRoot, this.ownerDocument);
			this.#setStyle(root, "pointer-events", state.cue ? void 0 : "none");
			this.#setStyle(root, TimeSliderChapterCSSVars.start, state.startPercent);
			this.#setStyle(root, TimeSliderChapterCSSVars.end, state.endPercent);
			this.#setStyle(root, TimeSliderChapterCSSVars.width, state.width ?? state.height);
			this.#setStyle(root, TimeSliderChapterCSSVars.fill, `${state.fillPercent}%`);
			this.#setStyle(root, TimeSliderChapterCSSVars.buffer, `${state.bufferPercent}%`);
			applyStateDataAttrs(root, slider.state, slider.stateAttrMap);
			applyStateDataAttrs(root, state, TimeSliderChapterDataAttrs);
			next.set(state.key, root);
		}
		for (const [key, root] of this.#rendered) if (!next.has(key)) root.remove();
		let before = null;
		for (const root of [...next.values()].reverse()) {
			if (root.parentNode !== this || root.nextSibling !== before) this.insertBefore(root, before);
			before = root;
		}
		this.#rendered.clear();
		for (const [key, rendered] of next) this.#rendered.set(key, rendered);
	}
	#getTemplateRoot() {
		if (this.#templateChecked) return this.#templateRoot;
		const template = getTemplateElement(this);
		if (!template) {
			for (const node of [...this.childNodes]) node.remove();
			return null;
		}
		this.#templateChecked = true;
		const root = getTemplateRoot(template);
		for (const node of [...this.childNodes]) if (node !== template) node.remove();
		if (root?.namespaceURI !== "http://www.w3.org/1999/xhtml") {
			console.warn(`[${this.localName}] template must contain exactly one HTML root element.`);
			return null;
		}
		this.#templateRoot = root;
		return this.#templateRoot;
	}
	#setStyle(element, name, value) {
		if (value === void 0) element.style.removeProperty(name);
		else element.style.setProperty(name, value);
	}
};

//#endregion
export { renderText as a, AudioTrackRadioGroupElement as i, TimeSliderChapterTitleElement as n, QualityRadioGroupElement as r, TimeSliderChaptersElement as t };
//# sourceMappingURL=time-slider-chapters-element-evnF3P5M.js.map