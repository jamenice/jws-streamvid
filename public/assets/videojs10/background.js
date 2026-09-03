import{r as e}from"./ui-element-DI_8moll.js";import{i as t}from"./context-C2rsoDxk.js";import{a as n,t as r}from"./create-player-B1eHxSTn.js";import{t as i,w as a}from"./container-element-C28_9xEa.js";import"./background-video-BKfGmVi9.js";const{PlayerElement:o,PlayerController:s}=r({features:n});var c=class extends o{static{this.tagName=`background-video-player`}};t(c);var l=`background-video-player {
  display: contents;
}

background-video-skin {
  --media-object-fit: cover;
  object-fit: var(--media-object-fit);
  width: 100%;
  min-width: 300px;
  height: 100%;
  min-height: 150px;
  display: block;
  position: relative;
}

background-video-skin > :not(img, picture) {
  object-fit: inherit;
  width: 100%;
  height: 100%;
  position: absolute;
  inset: 0;
}

background-video-skin > img, background-video-skin > picture {
  object-fit: inherit;
  width: 100%;
  height: 100%;
}
`;function u(){return`<media-container><slot name="media"></slot><slot></slot></media-container>`}var d=class extends e{static{this.tagName=`background-video-skin`}static{this.shadowRootOptions={mode:`open`}}static{this.getTemplateHTML=u}constructor(){super(),a(`__media-background-styles`,l),this.shadowRoot||(this.attachShadow(this.constructor.shadowRootOptions),this.shadowRoot.innerHTML=u())}};t(i),t(d);
//# sourceMappingURL=background.js.map