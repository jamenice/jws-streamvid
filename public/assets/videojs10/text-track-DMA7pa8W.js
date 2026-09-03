//#region ../utils/dist/dom/text-track.js
/** Whether a text track is a captions or subtitles track. */
function isCaptionOrSubtitleTrack(track) {
	return track.kind === "captions" || track.kind === "subtitles";
}
/** Find the `<track>` element that owns the given `TextTrack`. */
function findTrackElement(media, track) {
	if (!(media instanceof HTMLElement)) return null;
	for (const el of media.querySelectorAll("track")) if (el.track === track) return el;
	return null;
}

//#endregion
export { isCaptionOrSubtitleTrack as n, findTrackElement as t };
//# sourceMappingURL=text-track-DMA7pa8W.js.map