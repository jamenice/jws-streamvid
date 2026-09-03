import { i as safeDefine } from "./context-DlE_3NHA.js";
import { c as videoFeatures, t as createPlayer } from "./create-player-Duo2X4dJ.js";

//#region src/presets/video/player.ts
const { PlayerElement, PlayerController: VideoPlayerController } = createPlayer({ features: videoFeatures });
/**
* Player-state provider registered as `<video-player>`.
*
* The element owns the configured video store but no layout. Put a skin or `<media-container>` inside it to provide the
* media, controls, and fullscreen target.
*/
var VideoPlayerElement = class extends PlayerElement {
	static {
		this.tagName = "video-player";
	}
};

//#endregion
//#region src/define/video/player.ts
safeDefine(VideoPlayerElement);

//#endregion
//# sourceMappingURL=player-DxcWi6bT.js.map