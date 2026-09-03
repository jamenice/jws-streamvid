//#region ../media/dist/dev/core/constants.js
/** A frozen, empty `TimeRanges`-like value for hosts with no ranges. */
const EMPTY_TIME_RANGES = Object.freeze({
	length: 0,
	start: () => 0,
	end: () => 0
});
/** A frozen, empty `TextTrackList`-like value for hosts with no text tracks. */
const EMPTY_TEXT_TRACKS = Object.assign(new EventTarget(), {
	length: 0,
	*[Symbol.iterator]() {},
	getTrackById: () => null
});
const EMPTY_REMOTE = new EventTarget();

//#endregion
export { EMPTY_TEXT_TRACKS as n, EMPTY_TIME_RANGES as r, EMPTY_REMOTE as t };
//# sourceMappingURL=constants-CsSwyIX6.js.map