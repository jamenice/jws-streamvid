import { i as safeDefine } from "./context-DlE_3NHA.js";
import { o as liveAudioFeatures, t as createPlayer } from "./create-player-Duo2X4dJ.js";

//#region src/presets/live-audio/player.ts
const { PlayerElement, PlayerController: LiveAudioPlayerController } = createPlayer({ features: liveAudioFeatures });
var LiveAudioPlayerElement = class extends PlayerElement {
	static {
		this.tagName = "live-audio-player";
	}
};

//#endregion
//#region src/define/live-audio/player.ts
safeDefine(LiveAudioPlayerElement);

//#endregion
//# sourceMappingURL=player-e6rq9fvT.js.map