import { i as safeDefine } from "./context-DlE_3NHA.js";
import { i as audioFeatures, t as createPlayer } from "./create-player-Duo2X4dJ.js";

//#region src/presets/audio/player.ts
const { PlayerElement, PlayerController: AudioPlayerController } = createPlayer({ features: audioFeatures });
var AudioPlayerElement = class extends PlayerElement {
	static {
		this.tagName = "audio-player";
	}
};

//#endregion
//#region src/define/audio/player.ts
safeDefine(AudioPlayerElement);

//#endregion
//# sourceMappingURL=player-oDl9XOBH.js.map