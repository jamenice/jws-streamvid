import { i as safeDefine } from "./context-DlE_3NHA.js";
import { s as liveVideoFeatures, t as createPlayer } from "./create-player-Duo2X4dJ.js";

//#region src/presets/live-video/player.ts
const { PlayerElement, PlayerController: LiveVideoPlayerController } = createPlayer({ features: liveVideoFeatures });
var LiveVideoPlayerElement = class extends PlayerElement {
	static {
		this.tagName = "live-video-player";
	}
};

//#endregion
//#region src/define/live-video/player.ts
safeDefine(LiveVideoPlayerElement);

//#endregion
//# sourceMappingURL=player-BCEVkmkJ.js.map