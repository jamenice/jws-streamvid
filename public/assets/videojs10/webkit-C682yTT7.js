//#region ../utils/dist/dom/webkit.js
/** Whether WebKit's AirPlay APIs are present in this realm (Safari macOS/iOS). */
function supportsWebKitAirPlay() {
	return "WebKitPlaybackTargetAvailabilityEvent" in globalThis;
}
/** Whether `media` exposes WebKit's AirPlay APIs. */
function isWebKitAirPlayCapable(media) {
	return supportsWebKitAirPlay() && "webkitCurrentPlaybackTargetIsWireless" in media;
}

//#endregion
export { supportsWebKitAirPlay as n, isWebKitAirPlayCapable as t };
//# sourceMappingURL=webkit-C682yTT7.js.map