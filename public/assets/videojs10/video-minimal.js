import{a as e,c as t,f as n,h as r,i,j as a,l as o,m as s,n as c,p as l,r as u,s as d,t as f,u as p,w as m}from"./popover-element-BlqlJ30i.js";import{i as h}from"./context-C2rsoDxk.js";import{C as g,t as _}from"./container-element-C28_9xEa.js";import{a as v,i as y,n as b,r as x,t as S}from"./time-slider-chapters-element-DCP5P5_L.js";import{t as C}from"./gesture-element-DwdBnLeH.js";import"./player-Dcd9SEOm.js";import{a as w,c as T,l as E,n as D,u as O}from"./radio-options-controller-DKaHoTNW.js";import{a as k,i as A,n as j,o as M,r as N,s as P,t as F}from"./poster-element-vMzM5cZo.js";import{n as I,r as L,t as R}from"./seek-button-element-DHl8rc_2.js";import{t as z}from"./minimal-CK2UxVpi.js";var B=`video-player, live-video-player, media-i18n, media-dialog, media-alert-dialog, media-error-dialog, media-controls {
  display: contents;
}

media-container video, media-container [slot="poster"] {
  width: 100%;
  height: 100%;
  display: block;
}

media-container video::-webkit-media-text-track-container {
  z-index: 1;
  scale: .98;
  translate: 0 var(--media-caption-track-y, 0);
  transition: translate var(--media-caption-track-duration, 0) ease-out;
  transition-delay: var(--media-caption-track-delay, 0);
  font-family: inherit;
}

media-tooltip-group, media-dialog, media-alert-dialog, media-error-dialog, media-controls {
  display: contents;
}

:host {
  width: 100%;
  display: grid;
}

media-container {
  min-width: 0;
  min-height: 0;
}

.media-popover--volume:has(media-volume-slider[data-hidden]) {
  display: none;
}

.media-sr-only {
  white-space: nowrap;
  clip: rect(0, 0, 0, 0);
  border: 0;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  position: absolute;
  overflow: hidden;
}

.media-minimal-skin *, .media-minimal-skin :before, .media-minimal-skin :after {
  box-sizing: border-box;
}

.media-minimal-skin img, .media-minimal-skin video, .media-minimal-skin svg {
  max-width: 100%;
  display: block;
}

.media-minimal-skin button {
  font: inherit;
}

.media-minimal-skin [hidden][hidden] {
  display: none;
}

@media (prefers-reduced-motion: no-preference) {
  .media-minimal-skin {
    interpolate-size: allow-keywords;
  }
}

.media-minimal-skin {
  --media-internal-accent-color: var(--media-accent-color, var(--media-default-accent-color));
  --media-accent-contrast-color: contrast-color(var(--media-internal-accent-color));
  --media-accent-background-color: var(--media-accent-color, oklch(from var(--media-default-accent-color) l c h / calc(alpha * .1)));
  --media-internal-accent-text-color: var(--media-accent-text-color, contrast-color(var(--media-accent-color, oklch(0% 0 0))));
  --media-shadow-current-color: oklch(from currentColor 0 0 0 / clamp(0, calc((l - .5) * .5), .15));
  --media-shadow-subtle-current-color: oklch(from var(--media-shadow-current-color) l c h / calc(alpha * .4));
  --media-scrollbar-thumb-color: oklch(from currentColor l c h / .3);
  --media-scale: 1;
  --media-internal-scale-unit: var(--media-scale-unit, 16px);
  --media-size: calc(var(--media-internal-scale-unit) * var(--media-scale));
  --media-spacing: calc(var(--media-size) / 4);
  --media-font-size-medium: calc(.9375 * var(--media-size));
  --media-font-size-base: calc(.8125 * var(--media-size));
  --media-font-size-small: calc(.6875 * var(--media-size));
  --media-font-size-tiny: calc(.5625 * var(--media-size));
  --media-icon-size: calc(1.125 * var(--media-size));
  --media-container-border-radius: var(--media-border-radius, .75rem);
  width: 100%;
  height: 100%;
  font-family: Inter Variable, Inter, ui-sans-serif, system-ui, sans-serif;
  font-size: var(--media-font-size-base);
  -webkit-font-smoothing: auto;
  -moz-osx-font-smoothing: auto;
  letter-spacing: normal;
  outline-offset: -4px;
  scrollbar-color: var(--media-scrollbar-thumb-color) transparent;
  scrollbar-width: thin;
  border-radius: var(--media-container-border-radius);
  isolation: isolate;
  outline: 2px solid #0000;
  line-height: 1.5;
  transition-property: outline-offset, outline-color;
  transition-duration: .1s;
  transition-timing-function: ease-out;
  display: block;
  position: relative;
  container: media-root / inline-size;

  &:focus-visible {
    outline-color: var(--media-focus-ring-color);
    outline-offset: 2px;
  }

  &::-webkit-scrollbar-thumb {
    background: var(--media-scrollbar-thumb-color);
    border-radius: 9999px;
  }

  @media (prefers-reduced-transparency: reduce) or (prefers-contrast: more) {
    --media-scrollbar-thumb-color: oklch(from currentColor l c h / .8);
    scrollbar-width: auto;
  }
}

.media-minimal-skin ::slotted(video), .media-minimal-skin video {
  object-fit: var(--media-object-fit, contain);
  object-position: var(--media-object-position, center);
  width: 100%;
  height: 100%;
  display: block;
}

.media-minimal-skin ::slotted(video) {
  border-radius: var(--media-container-border-radius);
}

.media-minimal-skin video {
  border-radius: inherit;
}

.media-minimal-skin:fullscreen ::slotted(video), .media-minimal-skin:fullscreen video {
  object-fit: contain;
}

.media-minimal-skin .media-controls__backdrop {
  z-index: 10;
  pointer-events: none;
  background-image: linear-gradient(to top,
    oklch(0% 0 0 / .7),
    oklch(0% 0 0 / .5) calc(var(--media-spacing) * 30),
    oklch(0% 0 0 / 0));
  border-radius: inherit;
  opacity: 0;
  transition-timing-function: ease-out;
  transition-duration: var(--media-controls-transition-duration);
  transition-property: opacity;
  position: absolute;
  inset: 0;

  &[data-visible] {
    opacity: 1;
  }
}

.media-minimal-skin .media-buffering-indicator {
  z-index: 10;
  color: oklch(100% 0 0);
  pointer-events: none;
  place-content: center;
  display: none;
  position: absolute;
  inset: 0;

  &:before {
    content: "";
    backdrop-filter: blur(8px);
    background: oklch(0% 0 0 / .35);
    position: absolute;
    inset: 0;
  }

  & > * {
    z-index: 20;
    position: relative;
  }

  &:not([data-visible]) {
    --media-spinner-animation: none;
  }

  &[data-visible] {
    display: grid;
  }

  @media (prefers-reduced-motion: reduce) {
    --media-spinner-animation: none;
  }
}

.media-minimal-skin {
  & media-error-dialog {
    z-index: 20;
    outline: none;
    justify-content: center;
    align-items: center;
    display: flex;
    position: absolute;
    inset: 0;

    &:not([data-open]) {
      display: none;
    }
  }

  & .media-dialog__backdrop {
    z-index: 10;
    pointer-events: none;
    backdrop-filter: blur(16px) saturate(1.2);
    opacity: 1;
    transition-timing-function: var(--media-dialog-transition-timing-function);
    transition-duration: var(--media-dialog-transition-duration);
    transition-property: opacity;
    transition-delay: var(--media-dialog-transition-delay);
    background: oklch(0% 0 0 / .2);
    position: absolute;
    inset: 0;

    &[data-starting-style], &[data-ending-style] {
      opacity: 0;
    }

    &[data-ending-style] {
      transition-delay: 0s;
    }

    &:not([data-open]) {
      display: none;
    }
  }

  & .media-dialog__popup {
    outline: none;
  }

  & .media-dialog__title {
    font-weight: 600;
    line-height: 1.25;
  }

  & .media-dialog__description {
    overflow-wrap: anywhere;
    opacity: .7;
  }

  & .media-dialog__actions {
    gap: calc(var(--media-spacing) * 2);
    display: flex;

    & > * {
      flex: 1;
    }
  }
}

.media-minimal-skin .media-controls {
  --media-popover-side-offset: calc(var(--media-spacing) * (var(--media-base-side-offset, 2) + 1));
  --media-tooltip-side-offset: var(--media-popover-side-offset);
  --media-popover-boundary-offset: calc(var(--media-spacing) * (var(--media-base-boundary-offset, 0) + 1));
  --media-tooltip-boundary-offset: var(--media-popover-boundary-offset);
  padding: calc(var(--media-spacing) * 1);
  text-shadow: 0 1px 0 var(--media-shadow-current-color);
  background-color: var(--media-controls-background-color);
  backdrop-filter: var(--media-controls-backdrop-filter);
  align-items: center;
  display: flex;
  container: media-controls / inline-size;

  &:dir(rtl) {
    flex-direction: row-reverse;
  }
}

.media-minimal-skin .media-time-controls {
  gap: calc(var(--media-spacing) * 3);
  flex-direction: row-reverse;
  flex: 1;
  align-items: center;
  display: flex;
  container: media-time-controls / inline-size;

  &:dir(rtl) {
    flex-direction: row;
  }
}

.media-minimal-skin .media-time-group {
  gap: calc(var(--media-spacing) * 1);
  align-items: center;
  display: flex;

  &:dir(rtl) {
    flex-direction: row-reverse;
  }
}

.media-minimal-skin .media-time {
  font-variant-numeric: tabular-nums;
}

.media-minimal-skin .media-time[role="button"] {
  cursor: pointer;
  outline-offset: -2px;
  border-radius: calc(var(--media-spacing) * 1);
  outline: 2px solid #0000;
  transition-property: outline-color, outline-offset;
  transition-duration: .1s;
  transition-timing-function: ease-out;

  @supports (corner-shape: squircle) {
    border-radius: calc(var(--media-spacing) * 4);
    corner-shape: squircle;
  }

  &:focus-visible {
    outline-color: var(--media-focus-ring-color);
    outline-offset: 2px;
  }
}

.media-minimal-skin .media-time--current, .media-minimal-skin .media-time-separator {
  display: none;
}

@container media-root (width > 42rem) {
  .media-minimal-skin .media-time-controls {
    flex-direction: row;

    &:dir(rtl) {
      flex-direction: row-reverse;
    }
  }

  .media-minimal-skin .media-time--duration, .media-minimal-skin .media-time-separator {
    color: oklch(from currentColor l c h / .6);
  }

  .media-minimal-skin .media-time--current, .media-minimal-skin .media-time-separator {
    display: inline;
  }
}

.media-minimal-skin .media-button {
  height: calc(var(--media-spacing) * 9.5);
  min-height: 0;
  padding: calc(var(--media-spacing) * 2) calc(var(--media-spacing) * 4);
  text-align: center;
  touch-action: manipulation;
  cursor: pointer;
  user-select: none;
  outline-offset: -2px;
  border-radius: calc(var(--media-spacing) * 2);
  will-change: scale;
  border: none;
  outline: 2px solid #0000;
  flex-shrink: 0;
  justify-content: center;
  align-items: center;
  transition-property: background-color, outline-offset, scale;
  transition-duration: .15s;
  transition-timing-function: ease-out;
  display: flex;

  &:focus-visible {
    outline-color: var(--media-focus-ring-color);
    outline-offset: 2px;
  }

  &:active:not([aria-disabled="true"]) {
    scale: .97;
  }

  &[aria-disabled="true"] {
    cursor: not-allowed;
    opacity: .5;
  }
}

@supports (corner-shape: squircle) {
  .media-minimal-skin .media-button {
    border-radius: calc(var(--media-spacing) * 4);
    corner-shape: squircle;
  }
}

.media-minimal-skin .media-button--primary {
  color: var(--media-accent-contrast-color);
  text-shadow: none;
  background: var(--media-internal-accent-color);
  font-weight: 500;
}

.media-minimal-skin .media-button--subtle {
  color: inherit;
  text-shadow: inherit;
  background: none;

  &:not([aria-disabled="true"]) {
    &:hover, &:focus-visible, &[aria-expanded="true"] {
      color: var(--media-internal-accent-text-color);
      background-color: var(--media-accent-background-color);
      text-decoration: none;
    }
  }
}

.media-minimal-skin .media-button--icon {
  aspect-ratio: 1;
  padding: 0;
  display: grid;

  &:active:not([aria-disabled="true"]) {
    scale: .97;
  }

  & .media-icon__container {
    display: grid;
  }

  & .media-icon {
    filter: drop-shadow(0 1px 0 var(--media-shadow-current-color));
    grid-area: 1 / 1;
    transition-property: opacity, scale;
    transition-duration: .15s;
    transition-timing-function: ease-out;
  }
}

.media-minimal-skin .media-button--seek {
  & .media-icon__label {
    font-variant-numeric: tabular-nums;
    letter-spacing: -.05em;
    font-size: .715em;
    font-weight: 500;
    position: absolute;
    bottom: -3px;
    right: -1px;
  }

  &:has(.media-icon--flipped) .media-icon__label {
    right: unset;
    left: -1px;
  }
}

.media-minimal-skin .media-button--playback-rate {
  font-variant-numeric: tabular-nums;
  padding: 0;

  &:after {
    content: attr(data-rate) "×";
    width: 4ch;
  }

  &[data-inline-rate-label]:after {
    content: none;
  }
}

.media-minimal-skin .media-button--settings {
  & .media-icon--settings {
    transition: transform .15s ease-in-out;

    @media (prefers-reduced-motion: reduce) {
      transition-duration: 0s;
    }
  }

  &[aria-expanded="true"] .media-icon--settings {
    transform: rotate(90deg);
  }
}

.media-minimal-skin .media-button--live {
  gap: calc(var(--media-spacing) * 1.5);
  aspect-ratio: auto;
  width: auto;
  padding: calc(var(--media-spacing) * 2) calc(var(--media-spacing) * 3);
  font-size: var(--media-font-size-small);
  text-transform: uppercase;
  letter-spacing: .05em;
  align-items: center;
  font-weight: 600;
  line-height: 1;
  display: inline-flex;

  &:before {
    width: calc(var(--media-spacing) * 2);
    height: calc(var(--media-spacing) * 2);
    content: "";
    background-color: oklch(from currentColor l c h / .4);
    border-radius: 50%;
    flex-shrink: 0;
    transition: background-color .15s ease-out;
    display: inline-block;
  }

  &[data-live-edge]:before {
    background-color: oklch(65% .22 27);
  }
}

@media (prefers-reduced-motion: reduce) {
  .media-minimal-skin .media-button {
    will-change: auto;
    transition-property: background-color;
    scale: 1;
  }
}

.media-minimal-skin .media-button-group {
  align-items: center;
  gap: 1px;
  display: flex;

  &:dir(rtl) {
    flex-direction: row-reverse;
  }
}

.media-minimal-skin .media-badge {
  padding: calc(var(--media-spacing) * 1) calc(var(--media-spacing) * 1.5);
  font-size: var(--media-font-size-small);
  color: oklch(from currentColor l c h / .85);
  white-space: nowrap;
  background-color: oklch(from currentColor l c h / .1);
  border-radius: calc(var(--media-spacing) * 1);
  font-weight: 500;
  line-height: 1;
}

.media-minimal-skin .media-icon__container {
  position: relative;
}

.media-minimal-skin .media-icon {
  width: var(--media-icon-size);
  height: var(--media-icon-size);
  flex-shrink: 0;
}

.media-minimal-skin .media-icon--flipped, .media-minimal-skin:dir(rtl) .media-menu__chevron {
  scale: -1 1;
}

.media-minimal-skin:dir(rtl) .media-menu__chevron.media-icon--flipped {
  scale: 1;
}

.media-minimal-skin media-poster, .media-minimal-skin > img {
  pointer-events: none;
  width: 100%;
  height: 100%;
  transition: opacity .25s;
  position: absolute;
  inset: 0;
}

.media-minimal-skin media-poster:not([data-visible]), .media-minimal-skin > img:not([data-visible]) {
  opacity: 0;
}

.media-minimal-skin media-poster ::slotted(img), .media-minimal-skin media-poster img {
  object-fit: var(--media-object-fit, contain);
  object-position: var(--media-object-position, center);
  border-radius: var(--media-container-border-radius);
  width: 100%;
  height: 100%;
  position: absolute;
  inset: 0;
}

.media-minimal-skin > img {
  object-fit: var(--media-object-fit, contain);
  object-position: var(--media-object-position, center);
  border-radius: inherit;
}

.media-minimal-skin:fullscreen media-poster ::slotted(img), .media-minimal-skin:fullscreen media-poster img, .media-minimal-skin:fullscreen > img {
  object-fit: contain;
}

.media-minimal-skin .media-thumbnail {
  pointer-events: none;
  border-radius: calc(var(--media-spacing) * 2);
  background-color: oklch(0% 0 0 / .9);
  position: relative;

  &:after {
    content: "";
    border-radius: inherit;
    position: absolute;
    inset: 0;
    box-shadow: 0 0 0 1px oklch(0% 0 0 / .05), 0 1px 3px oklch(0% 0 0 / .2), 0 1px 2px -1px oklch(0% 0 0 / .2);
  }

  & .media-thumbnail__image {
    max-width: var(--media-thumbnail-max-width);
    max-height: var(--media-thumbnail-max-height);
    border-radius: inherit;
    display: block;
    position: relative;
    overflow: clip;
  }

  & .media-thumbnail__spinner {
    opacity: 0;
    position: absolute;
    top: 50%;
    left: 50%;
    translate: -50% -50%;
  }

  & .media-thumbnail__image, & .media-thumbnail__spinner {
    transition: opacity .15s ease-out;
  }

  &:not(:has(.media-thumbnail__image[data-loading])) {
    & .media-thumbnail__spinner {
      --media-spinner-animation: none;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    --media-spinner-animation: none;
  }

  &:has(.media-thumbnail__image[data-loading]) {
    width: var(--media-thumbnail-max-width);
    aspect-ratio: 16 / 9;
    max-width: 100%;
    overflow: hidden;

    & .media-thumbnail__image {
      opacity: 0;
    }

    & .media-thumbnail__spinner {
      opacity: 1;
    }
  }
}

.media-minimal-skin .media-slider {
  --media-track-size: calc(var(--media-spacing) * 1);
  --media-track-highlighted-size: calc(var(--media-spacing) * 1.75);
  --media-track-border-radius: 99px;
  --media-track-transition-duration: .1s;
  --media-thumb-size: calc(var(--media-spacing) * 3);
  --media-chapter-gap: calc(var(--media-spacing) * 1);
  --media-internal-chapter-inset-start: calc(var(--media-chapter-gap) / 2);
  --media-internal-chapter-inset-end: calc(var(--media-chapter-gap) / 2);
  cursor: pointer;
  border-radius: var(--media-track-border-radius);
  outline: none;
  flex: 1;
  justify-content: center;
  align-items: center;
  display: flex;
  position: relative;

  &[data-orientation="horizontal"] {
    width: var(--media-slider-width, 100%);
    min-width: calc(var(--media-spacing) * 20);
    height: var(--media-slider-height, calc(var(--media-spacing) * 8));
  }

  &[data-orientation="vertical"] {
    width: var(--media-slider-width, calc(var(--media-spacing) * 8));
    height: var(--media-slider-height, calc(var(--media-spacing) * 20));
  }

  & .media-slider__track {
    user-select: none;
    background-color: oklch(from currentColor l c h / .2);
    border-radius: inherit;
    isolation: isolate;
    position: relative;
    overflow: hidden;

    &[data-orientation="horizontal"] {
      width: 100%;
      height: var(--media-track-size);
    }

    &[data-orientation="vertical"] {
      width: var(--media-track-size);
      height: 100%;
    }
  }

  & .media-slider__buffer, & .media-slider__fill {
    pointer-events: none;
    border-radius: inherit;
    position: absolute;

    &[data-orientation="horizontal"] {
      inset-block: 0;
      width: 100%;
      left: 0;
    }

    &[data-orientation="vertical"] {
      inset-inline: 0;
      height: 100%;
      bottom: 0;
    }

    @media (prefers-reduced-motion: no-preference) {
      transition: clip-path var(--media-track-transition-duration) ease-out;
    }
  }

  &[data-dragging] {
    & .media-slider__fill, & .media-slider__buffer {
      transition-duration: 0s;
    }
  }

  & .media-slider__buffer {
    background-color: oklch(from currentColor l c h / .2);

    &[data-orientation="horizontal"] {
      clip-path: inset(0 calc(100% - var(--media-slider-buffer)) 0 0 round var(--media-track-border-radius));
    }

    &[data-orientation="vertical"] {
      clip-path: inset(calc(100% - var(--media-slider-buffer)) 0 0 0 round var(--media-track-border-radius));
    }
  }

  & .media-slider__fill {
    background-color: var(--media-internal-accent-color);

    &[data-orientation="horizontal"] {
      clip-path: inset(0 calc(100% - var(--media-slider-fill)) 0 0 round var(--media-track-border-radius));
    }

    &[data-orientation="vertical"] {
      clip-path: inset(calc(100% - var(--media-slider-fill)) 0 0 0 round var(--media-track-border-radius));
    }
  }

  &[data-dragging] {
    & .media-slider__fill[data-orientation="horizontal"] {
      clip-path: inset(0 calc(100% - var(--media-slider-pointer)) 0 0 round var(--media-track-border-radius));
    }

    & .media-slider__fill[data-orientation="vertical"] {
      clip-path: inset(calc(100% - var(--media-slider-pointer)) 0 0 0 round var(--media-track-border-radius));
    }
  }

  & .media-slider__chapters {
    border-radius: inherit;
    flex: 1;
    align-items: center;
    min-width: 0;
    min-height: 0;
    display: flex;
    position: relative;

    &[data-orientation="horizontal"] {
      width: 100%;
      height: 100%;
    }

    &[data-orientation="vertical"] {
      flex-direction: column-reverse;
      width: 100%;
      height: 100%;
    }
  }

  & .media-slider__chapter {
    justify-content: center;
    align-items: center;
    min-width: 0;
    min-height: 0;
    display: flex;
    position: absolute;
    inset: 0;

    &:first-child {
      --media-internal-chapter-inset-start: 0px;
    }

    &:last-child {
      --media-internal-chapter-inset-end: 0px;
    }

    & .media-slider__chapter-track {
      border-radius: var(--media-track-border-radius);

      @media (prefers-reduced-motion: no-preference) {
        transition: height .2s ease-out, width .2s ease-out;
      }
    }

    &[data-orientation="horizontal"] {
      clip-path: inset(0 calc(100% - var(--media-slider-chapter-end)) 0 var(--media-slider-chapter-start));

      & .media-slider__chapter-track {
        height: var(--media-track-size);
        clip-path: inset(0 calc(100% - var(--media-slider-chapter-end) + var(--media-internal-chapter-inset-end)) 0
            calc(var(--media-slider-chapter-start) + var(--media-internal-chapter-inset-start)) round
            var(--media-track-border-radius));
      }

      &[data-highlighted] .media-slider__chapter-track {
        height: var(--media-track-highlighted-size);
      }
    }

    &[data-orientation="vertical"] {
      clip-path: inset(calc(100% - var(--media-slider-chapter-end)) 0 var(--media-slider-chapter-start) 0);

      & .media-slider__chapter-track {
        width: var(--media-track-size);
        clip-path: inset(calc(100% - var(--media-slider-chapter-end) + var(--media-internal-chapter-inset-end)) 0
            calc(var(--media-slider-chapter-start) + var(--media-internal-chapter-inset-start)) 0 round
            var(--media-track-border-radius));
      }

      &[data-highlighted] .media-slider__chapter-track {
        width: var(--media-track-highlighted-size);
      }
    }
  }

  & .media-slider__thumb {
    z-index: 10;
    width: var(--media-thumb-size);
    height: var(--media-thumb-size);
    user-select: none;
    outline-offset: -2px;
    box-shadow: 0 0 0 1px var(--media-shadow-current-color, oklch(0% 0 0 / .15)),
      0 1px 3px 0 oklch(0% 0 0 / .15),
      0 1px 2px -1px oklch(0% 0 0 / .15);
    opacity: 0;
    transform-origin: center;
    background-color: currentColor;
    border-radius: 3.40282e38px;
    outline: 2px solid #0000;
    position: absolute;
    translate: -50% -50%;
    scale: .7;

    &[data-orientation="horizontal"] {
      top: 50%;
      left: var(--media-slider-fill);
    }

    &[data-orientation="vertical"] {
      top: calc(100% - var(--media-slider-fill));
      left: 50%;
    }

    &:focus-visible {
      outline-color: var(--media-focus-ring-color);
      outline-offset: 2px;
    }

    &:focus-visible, &.media-slider__thumb--persistent {
      opacity: 1;
      scale: 1;
    }

    @media (prefers-reduced-motion: no-preference) {
      transition-timing-function: ease-out;
      transition-duration: var(--media-track-transition-duration);
      transition-property: opacity, outline-offset, left, top, scale;
    }
  }

  @media (hover: hover) and (pointer: fine) {
    &:hover .media-slider__thumb {
      opacity: 1;
      scale: 1;
    }
  }

  &[data-dragging] .media-slider__thumb {
    opacity: 1;
    scale: .9;

    @media (prefers-reduced-motion: no-preference) {
      transition-property: opacity, outline-offset, scale;
    }

    &[data-orientation="horizontal"] {
      left: var(--media-slider-pointer);
    }

    &[data-orientation="vertical"] {
      top: calc(100% - var(--media-slider-pointer));
    }
  }

  & .media-slider__preview {
    --media-max-size-factor: 28;
    --media-max-size: min(calc(var(--media-spacing) * var(--media-max-size-factor)), 100cqi);
    min-width: 100%;
    height: calc(var(--media-spacing) * 1);

    @container media-root (width > 32rem) {
      --media-max-size-factor: 36;
    }

    @container media-root (width > 42rem) {
      --media-max-size-factor: 48;
    }

    & .media-slider__thumbnail, & .media-slider__value {
      left: var(--media-preview-left, var(--media-slider-pointer));
      max-width: var(--media-max-size);
      opacity: 0;
      filter: blur(8px);
      transform-origin: bottom;
      scale: .8;
      translate: -50% calc(var(--media-spacing) * 2);
      transition-property: filter, opacity, scale;
      transition-duration: .15s;
      transition-timing-function: ease-out;
      position: absolute;
    }

    & .media-slider__thumbnail {
      --media-thumbnail-max-width: var(--media-max-size);
      --media-thumbnail-max-height: var(--media-max-size);
      bottom: calc(100% + (var(--media-spacing) * 11));
    }

    & .media-slider__value {
      bottom: calc(100% + (var(--media-spacing) * 5));
      gap: calc(var(--media-spacing) * 2);
      flex-direction: row-reverse;
      justify-content: center;
      display: flex;
    }

    & .media-slider__chapter-title {
      text-overflow: ellipsis;
      white-space: nowrap;
      min-width: 0;
      overflow: hidden;

      &:empty {
        display: none;
      }
    }

    &:before {
      z-index: 1;
      pointer-events: none;
      content: "";
      background-color: oklch(from currentColor l c h / .35);
      opacity: 0;
      transition-property: opacity, scale;
      transition-duration: .2s;
      transition-timing-function: ease-out;
      position: absolute;
      translate: -50% -50%;
      scale: .5;
    }

    &[data-orientation="horizontal"]:before {
      top: 50%;
      left: var(--media-slider-pointer);
      width: 1px;
      height: calc(var(--media-spacing) * 5);
    }

    &[data-orientation="vertical"]:before {
      top: calc(100% - var(--media-slider-pointer));
      width: calc(var(--media-spacing) * 5);
      height: 1px;
      left: 50%;
    }

    &[data-pointing]:not([data-dragging]):before {
      opacity: 1;
      scale: 1;
    }
  }

  &:is([data-pointing], :has(:focus-visible)) .media-slider__preview :is(.media-slider__value, .media-slider__thumbnail) {
    opacity: 1;
    filter: blur();
    scale: 1;
  }
}

.media-minimal-skin {
  --media-popup-transition: opacity var(--media-popup-transition-timing-function) var(--media-popup-transition-duration),
    filter var(--media-popup-transition-timing-function) var(--media-popup-transition-duration),
    transform var(--media-popup-transition-timing-function) var(--media-popup-transition-duration),
    scale var(--media-popup-transition-timing-function) var(--media-popup-transition-duration);
}

.media-minimal-skin .media-popover, .media-minimal-skin .media-tooltip {
  --media-popup-translate-distance: calc(var(--media-spacing) * 2);
  color: inherit;
  transition: var(--media-popup-transition);
  border: 0;
  margin: 0;
  overflow: visible;

  &[data-starting-style], &[data-ending-style] {
    opacity: 0;
    transform: translate(var(--media-popup-translate-x-distance, 0), var(--media-popup-translate-y-distance, 0));
    scale: .95;
  }

  &[data-ending-style] {
    filter: blur(4px);
    transition-duration: max(0s, calc(var(--media-popup-transition-duration) - 50ms));
    transform: none;
  }

  &[data-side="top"] {
    --media-popup-translate-y-distance: var(--media-popup-translate-distance);
    transform-origin: bottom;
  }

  &[data-side="bottom"] {
    --media-popup-translate-y-distance: calc(var(--media-popup-translate-distance) * -1);
    transform-origin: top;
  }

  &[data-side="left"] {
    --media-popup-translate-x-distance: var(--media-popup-translate-distance);
    transform-origin: 100%;
  }

  &[data-side="right"] {
    --media-popup-translate-x-distance: calc(var(--media-popup-translate-distance) * -1);
    transform-origin: 0;
  }

  &:before {
    pointer-events: inherit;
    content: "";
    position: absolute;
  }

  &[data-side="top"]:before, &[data-side="bottom"]:before {
    width: 100%;
    inset-inline: 0;
  }

  &[data-side="top"]:before {
    top: 100%;
  }

  &[data-side="bottom"]:before {
    bottom: 100%;
  }

  &[data-side="left"]:before, &[data-side="right"]:before {
    height: 100%;
    inset-block: 0;
  }

  &[data-side="left"]:before {
    left: 100%;
  }

  &[data-side="right"]:before {
    right: 100%;
  }
}

.media-minimal-skin .media-popover {
  &[data-side="top"]:before, &[data-side="bottom"]:before {
    height: var(--media-popover-side-offset);
  }

  &[data-side="left"]:before, &[data-side="right"]:before {
    width: var(--media-popover-side-offset);
  }
}

.media-minimal-skin .media-tooltip {
  padding: calc(var(--media-spacing) * 1) calc(var(--media-spacing) * 2);
  font-size: var(--media-font-size-base);
  color: var(--media-tooltip-text-color);
  white-space: nowrap;
  background-color: var(--media-tooltip-background-color);
  border-radius: calc(var(--media-spacing) * 2);
  box-shadow: 0 0 0 1px var(--media-tooltip-border-color),
    0 4px 6px -1px oklch(0% 0 0 / .2),
    0 2px 4px -2px oklch(0% 0 0 / .2);
  backdrop-filter: var(--media-tooltip-backdrop-filter);

  &[data-open] {
    column-gap: calc(var(--media-spacing) * 1);
    align-items: center;
    display: flex;
  }

  &[data-side="top"]:before, &[data-side="bottom"]:before {
    height: var(--media-tooltip-side-offset);
  }

  &[data-side="left"]:before, &[data-side="right"]:before {
    width: var(--media-tooltip-side-offset);
  }

  & .media-tooltip__kbd {
    min-width: 1.5em;
    margin-right: calc(var(--media-spacing) * -1);
    font-family: inherit;
    font-size: var(--media-font-size-small);
    text-align: center;
    background-color: oklch(from currentColor l c h / .15);
    border-radius: calc(var(--media-spacing) * 1);
    padding: .1em;
    font-weight: 600;
    line-height: 1.25;
  }
}

.media-minimal-skin .media-popover--volume:has(media-volume-slider[data-hidden]) {
  display: none;
}

.media-minimal-skin .media-menu {
  --media-menu-transition-duration: .25s;
  --media-menu-max-height: calc(var(--media-spacing) * 56);
  --media-menu-padding: calc(var(--media-spacing) * 1);
  --media-menu-border-radius: calc(var(--media-spacing) * 2.5);
  --media-menu-item-border-radius: calc(var(--media-menu-border-radius) - var(--media-menu-padding));
  box-sizing: border-box;
  min-width: max-content;
  max-width: var(--media-menu-available-width, none);
  max-height: min(var(--media-menu-available-height, var(--media-menu-max-height)), var(--media-menu-max-height));
  padding: var(--media-menu-padding);
  overscroll-behavior: none;
  background-color: var(--media-popover-background-color);
  border-radius: var(--media-menu-border-radius);
  box-shadow: 0 0 0 1px var(--media-popover-border-color),
    0 4px 6px -1px oklch(0% 0 0 / .1),
    0 2px 4px -2px oklch(0% 0 0 / .1);
  backdrop-filter: var(--media-popover-backdrop-filter);
  overflow: auto;

  @media (prefers-reduced-motion: reduce) {
    --media-menu-transition-duration: 0s;
  }

  & > .media-menu__panel {
    --media-menu-content-enter-translate: 100%;
    inset-inline: 0;
    z-index: 10;
    max-height: inherit;
    padding: var(--media-menu-padding);
    overscroll-behavior: none;
    transition-timing-function: ease-out;
    transition-duration: var(--media-menu-transition-duration);
    outline: none;
    transition-property: translate, filter;
    position: absolute;
    top: 0;
    overflow: auto;
    translate: 0;

    &:where([data-starting-style], [data-ending-style]) {
      pointer-events: none;
      filter: blur(8px);
      translate: var(--media-menu-content-enter-translate) 0;
      overflow: hidden;
    }

    &:dir(rtl):where([data-starting-style], [data-ending-style]) {
      --media-menu-content-enter-translate: -100%;
    }
  }

  & .media-menu__separator {
    margin: calc(var(--media-spacing) * 1) 0;
    border-bottom: 1px solid oklch(100% 0 0 / .1);
  }

  & .media-menu__content, & .media-menu__group {
    anchor-scope: --menu-item-highlight-anchor;
    gap: calc(var(--media-spacing) * .5);
    flex-direction: column;
    display: flex;

    @supports (top: anchor(top)) {
      &:before {
        position-anchor: --menu-item-highlight-anchor;
        inset: anchor(inside);
        overflow-anchor: none;
        pointer-events: none;
        content: "";
        background-color: var(--media-accent-background-color);
        border-radius: var(--media-menu-item-border-radius);
        transition: inset .1s ease-in-out;
        position: absolute;
      }

      &:has([data-highlighted=""]):before {
        transition-duration: 0s;
      }
    }
  }

  & .media-menu__item, & .media-menu__back {
    gap: calc(var(--media-spacing) * 1.5);
    padding: calc(var(--media-spacing) * 1.5) calc(var(--media-spacing) * 2);
    text-align: start;
    white-space: nowrap;
    text-shadow: 0 1px 0 var(--media-shadow-current-color);
    cursor: pointer;
    user-select: none;
    outline-offset: -2px;
    border-radius: var(--media-menu-item-border-radius);
    outline: 2px solid #0000;
    align-items: center;
    transition: background-color .1s ease-in-out, color .1s ease-in-out;
    display: flex;
    position: relative;

    & .media-icon {
      color: oklch(from currentColor l c h / .5);
      filter: drop-shadow(0 1px 0 var(--media-shadow-current-color));
      flex-shrink: 0;
    }

    &:focus-visible {
      outline-color: var(--media-focus-ring-color);
      outline-offset: 2px;
    }

    &:hover, &[data-highlighted] {
      color: var(--media-internal-accent-text-color);
      background-color: var(--media-accent-background-color);

      & .media-icon {
        color: inherit;
      }
    }

    @supports (top: anchor(top)) {
      transition-duration: 50ms;

      &:hover, &[data-highlighted] {
        transition-duration: .2s;
      }
    }
  }

  & .media-menu__indicator {
    margin-inline: auto calc(var(--media-spacing) * -1);
    opacity: 0;
    flex-shrink: 0;
  }

  & .media-menu__item {
    font-variant-numeric: tabular-nums;
    color: inherit;
    justify-content: space-between;

    &[aria-disabled="true"] {
      pointer-events: none;
      cursor: not-allowed;
      opacity: .5;
    }

    &[aria-checked="true"] .media-menu__indicator {
      opacity: 1;
    }

    &[data-availability="unavailable"], &[data-availability="unsupported"] {
      display: none;
    }

    &[data-highlighted] {
      @supports (top: anchor(top)) {
        anchor-name: --menu-item-highlight-anchor;
        background-color: #0000;
      }
    }
  }

  & .media-menu__tier {
    padding-inline-start: calc(var(--media-spacing) * .5);
    font-size: var(--media-font-size-tiny);
    color: oklch(from currentColor l c h / .7);
    padding-top: 1px;
    font-weight: 600;
    line-height: 1;
  }

  & .media-menu__back {
    width: 100%;
    margin-bottom: calc(var(--media-spacing) * .5);
  }

  & .media-menu__hint {
    gap: calc(var(--media-spacing) * 1);
    min-width: 0;
    color: oklch(from currentColor l c h / .65);
    align-items: center;
    margin-inline-start: auto;
    padding-inline-start: calc(var(--media-spacing) * 2);
    display: inline-flex;
  }

  & .media-menu__hint-label {
    max-width: calc(var(--media-spacing) * 24);
    text-overflow: ellipsis;
    white-space: nowrap;
    overflow: hidden;
  }

  & .media-menu__chevron {
    width: calc(var(--media-spacing) * 3.5);
    height: calc(var(--media-spacing) * 3.5);
  }

  &.media-menu--settings {
    width: var(--media-menu-width);
    min-width: calc(var(--media-spacing) * 44);
    height: var(--media-menu-height);
    transition: var(--media-popup-transition),
      width var(--media-popup-transition-timing-function) var(--media-menu-transition-duration),
      height var(--media-popup-transition-timing-function) var(--media-menu-transition-duration);
    overflow: hidden;

    & > .media-menu__content {
      --media-menu-content-exit-translate: -100%;
      transition: translate var(--media-menu-transition-duration) ease-out,
        filter var(--media-menu-transition-duration) ease-out;
      translate: 0;

      &:dir(rtl) {
        --media-menu-content-exit-translate: 100%;
      }
    }

    & > .media-menu__content[data-child-open] {
      filter: blur(8px);
      translate: var(--media-menu-content-exit-translate) 0;
    }

    & > .media-menu__content[data-child-open]:before, &:has( > .media-menu__panel[data-ending-style]) > .media-menu__content:before {
      display: none;
    }

    &[data-starting-style], &[data-ending-style] {
      transition: var(--media-popup-transition);
    }
  }
}

.media-minimal-skin {
  --media-caption-track-duration: var(--media-controls-transition-duration);
  --media-caption-track-delay: 25ms;
  --media-caption-track-y: calc(var(--media-spacing) * -2);

  &:has(.media-controls[data-visible]) {
    --media-caption-track-y: calc(var(--media-spacing) * -18);
  }

  @container media-root (width > 42rem) {
    &:has(.media-controls[data-visible]) > * {
      --media-caption-track-y: calc(var(--media-spacing) * -12);
    }
  }
}

.media-minimal-skin video::-webkit-media-text-track-container {
  z-index: 1;
  scale: .98;
  translate: 0 var(--media-caption-track-y);
  transition: translate var(--media-caption-track-duration) ease-out;
  transition-delay: var(--media-caption-track-delay);
  font-family: inherit;
}

.media-minimal-skin .media-input-indicator {
  color: oklch(100% 0 0);
  pointer-events: none;
  border-radius: inherit;
  grid-template-columns: 1fr 1fr 1fr;
  place-items: center;
  display: grid;
  position: absolute;
  inset: 0;
  overflow: hidden;
}

.media-minimal-skin {
  & .media-volume-indicator, & .media-status-indicator--state {
    inset-inline: 0;
    padding-top: calc(var(--media-spacing) * 3);
    padding-bottom: calc(var(--media-spacing) * 32);
    color: inherit;
    text-shadow: 0 1px 0 var(--media-shadow-current-color);
    pointer-events: none;
    background-image: linear-gradient(to bottom,
      oklch(0% 0 0 / .35),
      oklch(0% 0 0 / .2) calc(var(--media-spacing) * 12),
      oklch(0% 0 0 / 0));
    transform-origin: top;
    justify-content: center;
    transition-duration: .1s;
    transition-timing-function: ease-out;
    display: flex;
    position: absolute;
    top: 0;

    & .media-volume-indicator__content, & .media-status-indicator__content {
      gap: calc(var(--media-spacing) * 2);
      padding: calc(var(--media-spacing) * 1) calc(var(--media-spacing) * 2.5);
      justify-content: space-between;
      align-items: center;
      display: flex;
    }

    & .media-icon {
      filter: drop-shadow(0 1px 0 var(--media-shadow-current-color));
      flex-shrink: 0;
      display: none;
    }

    & .media-volume-indicator__value, & .media-status-indicator__value {
      margin-left: auto;
    }

    @media (pointer: fine) {
      will-change: translate, filter, opacity;
      transition-property: translate, filter, opacity;
    }

    @media (pointer: coarse) {
      will-change: translate, opacity;
      transition-property: translate, opacity;
    }

    @media (pointer: fine) and (prefers-reduced-motion: no-preference) {
      transition-property: translate, filter, opacity;
    }

    @media (prefers-reduced-transparency: reduce) or (prefers-contrast: more) {
      & .media-volume-indicator__content, & .media-status-indicator__content {
        background: var(--media-controls-background-color);
        border-radius: calc(var(--media-spacing) * 2);
      }
    }

    &[data-starting-style], &[data-ending-style] {
      opacity: 0;
      transition-duration: .4s;
      transition-timing-function: ease-in;

      @media (pointer: fine) and (prefers-reduced-motion: no-preference) {
        filter: blur(8px);
      }
    }

    &[data-ending-style] {
      @media (prefers-reduced-motion: no-preference) {
        translate: 0 -100%;
      }
    }
  }

  & .media-seek-indicator, & .media-status-indicator--playback {
    padding: calc(var(--media-spacing) * 4);
    text-align: center;
    grid-area: 1 / 2;
    place-content: center;
    display: grid;
  }
}

.media-minimal-skin .media-volume-indicator {
  & .media-volume-indicator__content {
    width: min(80%, calc(var(--media-spacing) * 56));
    transform: translateX(0);
  }

  & .media-volume-indicator__progress {
    width: 100%;
    height: calc(var(--media-spacing) * .75);
    background: oklch(from currentColor l c h / .2);
    box-shadow: 0 1px 0 var(--media-shadow-subtle-current-color);
    border-radius: 3.40282e38px;
    position: relative;

    &:before {
      inset-block: 0;
      width: var(--media-volume-fill, 0%);
      content: "";
      background: var(--media-internal-accent-color);
      border-radius: inherit;
      transition: width .2s linear;
      position: absolute;
      left: 0;
    }
  }

  &[data-level="high"] .media-icon--volume-high, &[data-level="low"] .media-icon--volume-low, &[data-level="off"] .media-icon--volume-off {
    display: block;
  }

  @media (prefers-reduced-motion: no-preference) {
    &[data-min]:not([data-starting-style], [data-ending-style]) .media-volume-indicator__content, &[data-max]:not([data-starting-style], [data-ending-style]) .media-volume-indicator__content {
      transition: transform .3s linear(0, -24 20%, 16 40%, -8 60%, 4 80%, 1);
      transform: translateX(.25px);
    }
  }
}

.media-minimal-skin .media-status-indicator--state {
  &[data-status="captions-on"] .media-icon--captions-on, &[data-status="captions-off"] .media-icon--captions-off, &[data-status="fullscreen"] .media-icon--fullscreen-enter, &[data-status="exit-fullscreen"] .media-icon--fullscreen-exit, &[data-status="pip"] .media-icon--pip-enter, &[data-status="exit-pip"] .media-icon--pip-exit {
    display: block;
  }
}

.media-minimal-skin .media-status-indicator--playback {
  transition-property: opacity, scale;
  transition-duration: .2s;
  transition-timing-function: ease-out;

  & .media-icon {
    width: calc(var(--media-icon-size) * 2);
    height: calc(var(--media-icon-size) * 2);
    opacity: 0;
    grid-area: 1 / 1;
    transition-property: opacity, scale;
    transition-duration: .15s;
    transition-timing-function: ease-out;
    scale: 0;
  }

  &[data-status="pause"] .media-icon--pause, &[data-status="play"] .media-icon--play {
    opacity: 1;
    scale: 1;
  }

  &[data-starting-style], &[data-ending-style] {
    opacity: 0;
    scale: .85;
  }

  &[data-ending-style] {
    transition-duration: .1s;
    transition-timing-function: ease-in;
  }

  @media (prefers-reduced-motion: reduce) {
    transition-property: opacity;
    transition-duration: 50ms;

    &[data-starting-style], &[data-ending-style], & .media-icon {
      scale: 1;
    }

    & .media-icon {
      transition-property: opacity;
      transition-duration: 50ms;
    }
  }
}

.media-minimal-skin .media-seek-indicator {
  gap: calc(var(--media-spacing) * 1);

  & .media-seek-indicator__value {
    font-variant-numeric: tabular-nums;
  }

  @container media-root (width > 24rem) {
    padding: calc(var(--media-spacing) * 6);
  }

  &[data-direction="backward"] {
    grid-column: 1;
    justify-self: left;
  }

  &[data-direction="forward"] {
    grid-column: 3;
    justify-self: right;
  }

  & .media-icon--seek {
    width: calc(var(--media-icon-size) * 1.5);
    height: calc(var(--media-icon-size) * 1.5);
    display: block;
  }

  &[data-direction="backward"] .media-icon--seek {
    scale: -1 1;
  }

  @media (prefers-reduced-motion: no-preference) {
    & .media-icon--seek {
      transition-property: translate, opacity;
      transition-duration: .2s;
      transition-timing-function: ease-in-out;
    }

    &[data-starting-style] .media-icon--seek, &[data-ending-style] .media-icon--seek {
      opacity: 0;
    }

    &[data-direction="forward"][data-starting-style] .media-icon--seek {
      translate: -60%;
    }

    &[data-direction="backward"][data-starting-style] .media-icon--seek {
      translate: 60%;
    }
  }
}

.media-button--play .media-icon, .media-button--mute .media-icon, .media-button--fullscreen .media-icon, .media-button--pip .media-icon, .media-button--cast .media-icon, .media-button--airplay .media-icon, .media-button--captions .media-icon {
  opacity: 0;
}

.media-button--play .media-icon {
  scale: 0;
}

.media-button--play[data-ended] .media-icon--restart, .media-button--play:not([data-ended])[data-paused] .media-icon--play, .media-button--play:not([data-ended]):not([data-started]) .media-icon--play, .media-button--play[data-started]:not([data-paused]):not([data-ended]) .media-icon--pause, .media-button--mute[data-muted] .media-icon--volume-off, .media-button--mute:not([data-muted])[data-volume-level="low"] .media-icon--volume-low, .media-button--mute:not([data-muted]):not([data-volume-level="low"]) .media-icon--volume-high, .media-button--fullscreen:not([data-fullscreen]) .media-icon--fullscreen-enter, .media-button--fullscreen[data-fullscreen] .media-icon--fullscreen-exit, .media-button--pip:not([data-pip]) .media-icon--pip-enter, .media-button--pip[data-pip] .media-icon--pip-exit, .media-button--cast:not([data-cast-state="connected"]) .media-icon--cast-enter, .media-button--cast[data-cast-state="connected"] .media-icon--cast-exit, .media-button--airplay:not([data-airplay-state="connected"]) .media-icon--airplay-enter, .media-button--airplay[data-airplay-state="connected"] .media-icon--airplay-exit, .media-button--captions:not([data-active]) .media-icon--captions-off, .media-button--captions[data-active] .media-icon--captions-on {
  opacity: 1;
  scale: 1;
}

.media-button--airplay:not([data-airplay-state="connected"]) {
  --media-icon-airplay-fill-animation: none;
  --media-icon-airplay-triangle-animation: none;
}

@media (prefers-reduced-motion: reduce) {
  .media-button--airplay {
    --media-icon--airplay__fill-animation: none;
    --media-icon--airplay__triangle-animation: none;
  }
}

.media-minimal-skin--video {
  --media-default-accent-color: oklch(100% 0 0);
  --media-border-color: light-dark(oklch(0% 0 0 / .15), oklch(100% 0 0 / .15));
  --media-focus-ring-color: light-dark(oklch(0% 0 0), oklch(100% 0 0));
  --media-video-border-radius: var(--media-container-border-radius);
  --media-controls-background-color: transparent;
  --media-controls-transition-duration: .1s;
  --media-controls-transition-timing-function: ease-out;
  --media-dialog-transition-duration: .15s;
  --media-dialog-transition-delay: .1s;
  --media-dialog-transition-timing-function: ease-out;
  --media-popup-transition-duration: .1s;
  --media-popup-transition-timing-function: ease-out;
  --media-popover-backdrop-filter: blur(16px) saturate(1.5);
  --media-popover-background-color: oklch(0% 0 0 / .5);
  --media-popover-border-color: oklch(100% 0 0 / .1);
  --media-tooltip-backdrop-filter: var(--media-popover-backdrop-filter);
  --media-tooltip-background-color: var(--media-popover-background-color);
  --media-tooltip-border-color: var(--media-popover-border-color);
  --media-tooltip-text-color: currentColor;
  background: oklch(0% 0 0);
  overflow: clip;

  @media (prefers-reduced-motion: reduce) {
    --media-dialog-transition-duration: 50ms;
    --media-dialog-transition-delay: 0s;
    --media-popup-transition-duration: 0s;

    & .media-dialog__popup {
      transition-property: opacity;
      scale: 1;
    }
  }

  @media (prefers-reduced-transparency: reduce) or (prefers-contrast: more) {
    --media-controls-background-color: oklch(0% 0 0);
    --media-tooltip-background-color: oklch(0% 0 0);
  }

  &:has(.media-controls:not([data-visible])) {
    @media (pointer: fine) {
      --media-controls-transition-duration: .3s;
    }

    @media (pointer: coarse) {
      --media-controls-transition-duration: .15s;
    }

    @media (prefers-reduced-motion: reduce) {
      --media-controls-transition-duration: 50ms;
    }
  }

  &:after {
    z-index: 10;
    pointer-events: none;
    content: "";
    border-radius: inherit;
    box-shadow: inset 0 0 0 1px var(--media-border-color);
    position: absolute;
    inset: 0;
  }

  &:fullscreen {
    --media-container-border-radius: 0;

    &:after {
      display: none;
    }

    @media (width >= 1280px) {
      --media-scale: 1.25;
    }

    @media (width >= 1536px) {
      --media-scale: 1.5;
    }

    @media (width >= 1920px) {
      --media-scale: 1.75;
    }
  }

  & * {
    --media-focus-ring-color: oklch(100% 0 0);
  }
}

.media-minimal-skin--video .media-dialog__popup {
  z-index: 20;
  gap: calc(var(--media-spacing) * 3);
  width: 100%;
  max-width: calc(var(--media-spacing) * 64);
  padding: calc(var(--media-spacing) * 4);
  color: oklch(100% 0 0);
  text-shadow: 0 1px oklch(0% 0 0 / .5);
  transition-delay: var(--media-dialog-transition-delay);
  transition-timing-function: var(--media-dialog-transition-timing-function);
  transition-duration: var(--media-dialog-transition-duration);
  flex-direction: column;
  transition-property: opacity, scale;
  display: flex;
  position: absolute;
  top: 50%;
  left: 50%;
  translate: -50% -50%;

  &[data-starting-style], &[data-ending-style], media-error-dialog[data-starting-style] &, media-error-dialog[data-ending-style] & {
    opacity: 0;
    scale: .95;
  }

  &[data-ending-style], media-error-dialog[data-ending-style] & {
    transition-delay: 0s;
  }
}

.media-minimal-skin--video .media-dialog__content {
  gap: calc(var(--media-spacing) * 2);
  padding: calc(var(--media-spacing) * 1.5) 0;
  flex-direction: column;
  display: flex;
}

.media-minimal-skin--video .media-dialog__title {
  font-size: var(--media-font-size-medium);
}

.media-minimal-skin--video .media-controls {
  --media-base-side-offset: 5;
  --media-base-boundary-offset: 1;
  --media-inset-factor: 1;
  --media-inset: calc(var(--media-spacing) * var(--media-inset-factor));
  --media-volume-mask: linear-gradient(to right, transparent 10%, #000 25%, #000 100%);
  inset-inline: var(--media-inset);
  bottom: var(--media-inset);
  z-index: 10;
  column-gap: calc(var(--media-spacing) * 2);
  color: oklch(100% 0 0);
  border-radius: calc(var(--media-spacing) * 3);
  transition-timing-function: var(--media-controls-transition-timing-function);
  transition-duration: calc(var(--media-controls-transition-duration) / 2);
  flex-wrap: wrap;
  position: absolute;

  @media (pointer: fine) {
    transition-property: translate, filter, opacity;
  }

  @media (pointer: coarse) {
    transition-property: translate, opacity;
  }

  &:not([data-visible]) {
    pointer-events: none;
    opacity: 0;
    transition-duration: var(--media-controls-transition-duration);

    @media (pointer: fine) and (prefers-reduced-motion: no-preference) {
      filter: blur(8px);
    }

    @media (prefers-reduced-motion: no-preference) {
      translate: 0 100%;
    }
  }

  & .media-time-controls {
    --media-slider-height: calc(var(--media-spacing) * 5);
    padding-inline: calc(var(--media-spacing) * 1.5);
    flex: 0 0 100%;
    order: -1;
  }

  & .media-button-group:first-child {
    text-align: left;
    flex: 1;
  }

  & .media-button-group:last-child {
    flex: 1;
    justify-content: end;
  }

  & :is(.media-time-controls, .media-button-group:last-child) {
    mask-image: var(--media-volume-mask-image, none);
    mask-repeat: no-repeat;
    mask-position: var(--media-volume-mask-position, 100% 0);
    mask-size: var(--media-volume-mask-size, 200% 100%);
    transition: mask-position 50ms ease-out;
  }

  &:has(.media-button--mute[aria-expanded="true"]) {
    @container media-root (width <= 42rem) {
      & .media-button-group:last-child {
        --media-volume-mask-image: var(--media-volume-mask);
        --media-volume-mask-position: 0 0;
        --media-volume-mask-size: 400% 100%;
      }
    }

    @container media-root (width > 42rem) {
      & .media-time-controls {
        --media-volume-mask-image: var(--media-volume-mask);
        --media-volume-mask-position: 0 0;
      }
    }
  }

  @container media-root (width > 42rem) {
    --media-inset-factor: 2;
    --media-base-side-offset: 2;
    flex-wrap: nowrap;

    & .media-time-controls {
      --media-slider-height: calc(var(--media-spacing) * 8);
      flex: 1;
      order: unset;
    }

    & .media-button-group:first-child, & .media-button-group:last-child {
      flex: none;
    }
  }
}

.media-minimal-skin--video:has(.media-controls:not([data-visible])) {
  cursor: none;
}

.media-minimal-skin--video .media-popover--volume {
  --media-popover-side-offset: 0rem;
  padding: 0 calc(var(--media-spacing) * 3);
  background: none;
}

.media-minimal-skin--video .media-slider__value {
  padding-inline: calc(var(--media-spacing) * 3);
  text-shadow: 0 1px 0 var(--media-shadow-current-color);
}

.media-minimal-skin--video .media-slider .media-slider__preview {
  --media-preview-end-inset: calc(100cqi - 100%);
  --media-preview-left: clamp(calc(var(--media-max-size) / 2),
    var(--media-slider-pointer),
    calc(100% - var(--media-max-size) / 2 + var(--media-preview-end-inset)));

  @container media-root (width > 42rem) {
    --media-preview-left: var(--media-slider-pointer);
  }
}
`;function V(){return`<media-container class="media-minimal-skin media-minimal-skin--video"><slot name="media"></slot><slot></slot><media-poster><slot name="poster"><img alt="" decoding="async"></slot></media-poster><media-buffering-indicator class="media-buffering-indicator"> ${z(`spinner`,{class:`media-icon`})} </media-buffering-indicator><media-error-dialog><media-dialog-backdrop class="media-dialog__backdrop"></media-dialog-backdrop><media-dialog-popup class="media-dialog__popup media-surface"><div class="media-dialog__content"><media-dialog-title class="media-dialog__title"></media-dialog-title><media-dialog-description class="media-dialog__description"></media-dialog-description></div><div class="media-dialog__actions"><media-dialog-close class="media-button media-button--primary"></media-dialog-close></div></media-dialog-popup></media-error-dialog><media-controls><media-controls-backdrop class="media-controls__backdrop"></media-controls-backdrop><media-controls-content class="media-controls"><media-tooltip-group><div class="media-button-group"><media-play-button commandfor="play-tooltip" class="media-button media-button--subtle media-button--icon media-button--play"> ${z(`restart`,{class:`media-icon media-icon--restart`})} ${z(`play`,{class:`media-icon media-icon--play`})} ${z(`pause`,{class:`media-icon media-icon--pause`})} </media-play-button><media-tooltip id="play-tooltip" side="top" class="media-tooltip"><media-tooltip-label></media-tooltip-label><media-tooltip-shortcut class="media-tooltip__kbd"></media-tooltip-shortcut></media-tooltip><media-mute-button id="video-mute-trigger" commandfor="video-volume-popover" class="media-button media-button--subtle media-button--icon media-button--mute"> ${z(`volume-off`,{class:`media-icon media-icon--volume-off`})} ${z(`volume-low`,{class:`media-icon media-icon--volume-low`})} ${z(`volume-high`,{class:`media-icon media-icon--volume-high`})} </media-mute-button><media-tooltip trigger="video-mute-trigger" delay="0" sticky side="top" class="media-tooltip"><media-tooltip-label></media-tooltip-label><media-tooltip-shortcut class="media-tooltip__kbd"></media-tooltip-shortcut></media-tooltip><media-popover id="video-volume-popover" open-on-hover delay="200" close-delay="100" side="right" class="media-popover media-popover--volume"><media-volume-slider class="media-slider" orientation="horizontal" thumb-alignment="edge"><media-slider-track class="media-slider__track"><media-slider-fill class="media-slider__fill"></media-slider-fill></media-slider-track><media-slider-thumb class="media-slider__thumb media-slider__thumb--persistent"></media-slider-thumb></media-volume-slider></media-popover></div><div class="media-time-controls"><media-time-group class="media-time-group"><media-time toggle type="current" class="media-time media-time--current"></media-time><media-time-separator class="media-time-separator"></media-time-separator><media-time type="duration" class="media-time media-time--duration"></media-time></media-time-group><media-time-slider class="media-slider"><media-time-slider-chapters class="media-slider__chapters"><template><div class="media-slider__chapter"><media-slider-track class="media-slider__track media-slider__chapter-track"><media-slider-buffer class="media-slider__buffer"></media-slider-buffer><media-slider-fill class="media-slider__fill"></media-slider-fill></media-slider-track></div></template></media-time-slider-chapters><media-slider-thumb class="media-slider__thumb"></media-slider-thumb><media-slider-preview class="media-slider__preview"><div class="media-thumbnail media-slider__thumbnail"><media-slider-thumbnail class="media-thumbnail__image"></media-slider-thumbnail> ${z(`spinner`,{class:`media-thumbnail__spinner media-icon`})} </div><div class="media-slider__value"><media-time-slider-chapter-title class="media-slider__chapter-title"></media-time-slider-chapter-title><media-slider-value type="pointer" class="media-time"></media-slider-value></div></media-slider-preview></media-time-slider></div><div class="media-button-group"><media-captions-button commandfor="captions-tooltip" class="media-button media-button--subtle media-button--icon media-button--captions"> ${z(`captions-off`,{class:`media-icon media-icon--captions-off`})} ${z(`captions-on`,{class:`media-icon media-icon--captions-on`})} </media-captions-button><media-tooltip id="captions-tooltip" side="top" class="media-tooltip"><media-tooltip-label></media-tooltip-label><media-tooltip-shortcut class="media-tooltip__kbd"></media-tooltip-shortcut></media-tooltip><button id="settings-trigger" commandfor="settings-menu" aria-labelledby="settings-label" class="media-button media-button--subtle media-button--icon media-button--settings"> ${z(`gear`,{class:`media-icon media-icon--settings`})} ${v(E,{id:`settings-label`,class:`media-sr-only`})} </button><media-menu id="settings-menu" side="top" align="center" class="media-popover media-menu media-menu--settings"><media-menu-content class="media-menu__content"><media-menu-item commandfor="settings-quality-menu" class="media-menu__item media-menu__item--submenu"> ${z(`switches`,{class:`media-icon`})} ${v(T)} <span class="media-menu__hint"><bdi data-part="hint" dir="auto" class="media-menu__hint-label"></bdi> ${z(`chevron`,{class:`media-icon media-menu__chevron`})} </span></media-menu-item><media-menu-item commandfor="settings-audio-menu" class="media-menu__item media-menu__item--submenu"> ${z(`speech`,{class:`media-icon`})} ${v(D)} <span class="media-menu__hint"><bdi data-part="hint" dir="auto" class="media-menu__hint-label"></bdi> ${z(`chevron`,{class:`media-icon media-menu__chevron`})} </span></media-menu-item><media-menu-item commandfor="settings-speed-menu" class="media-menu__item media-menu__item--submenu"> ${z(`speed`,{class:`media-icon`})} ${v(O)} <span class="media-menu__hint"><bdi data-part="hint" dir="auto" class="media-menu__hint-label"></bdi> ${z(`chevron`,{class:`media-icon media-menu__chevron`})} </span></media-menu-item><media-menu-item commandfor="settings-captions-menu" class="media-menu__item media-menu__item--submenu"> ${z(`captions-off`,{class:`media-icon`})} ${v(w)} <span class="media-menu__hint"><bdi data-part="hint" dir="auto" class="media-menu__hint-label"></bdi> ${z(`chevron`,{class:`media-icon media-menu__chevron`})} </span></media-menu-item></media-menu-content><media-menu-content id="settings-quality-menu" class="media-menu__panel"><media-menu-item class="media-menu__back"> ${z(`chevron`,{class:`media-icon media-menu__chevron media-icon--flipped`})} ${v(T)} </media-menu-item><div class="media-menu__separator"></div><media-quality-radio-group class="media-menu__group"><template><media-menu-radio-item class="media-menu__item"><span><bdi data-part="label" dir="auto"></bdi><sup data-part="tier" class="media-menu__tier"></sup></span><span data-part="badge" class="media-badge"></span><media-menu-item-indicator force-mount class="media-menu__indicator"> ${z(`check`,{class:`media-icon`})} </media-menu-item-indicator></media-menu-radio-item></template></media-quality-radio-group></media-menu-content><media-menu-content id="settings-audio-menu" class="media-menu__panel"><media-menu-item class="media-menu__back"> ${z(`chevron`,{class:`media-icon media-menu__chevron media-icon--flipped`})} ${v(D)} </media-menu-item><div class="media-menu__separator"></div><media-audio-track-radio-group class="media-menu__group"><template><media-menu-radio-item class="media-menu__item"><bdi data-part="label" dir="auto"></bdi><media-menu-item-indicator force-mount class="media-menu__indicator"> ${z(`check`,{class:`media-icon`})} </media-menu-item-indicator></media-menu-radio-item></template></media-audio-track-radio-group></media-menu-content><media-menu-content id="settings-speed-menu" class="media-menu__panel"><media-menu-item class="media-menu__back"> ${z(`chevron`,{class:`media-icon media-menu__chevron media-icon--flipped`})} ${v(O)} </media-menu-item><div class="media-menu__separator"></div><media-playback-rate-radio-group class="media-menu__group"><template><media-menu-radio-item class="media-menu__item"><bdi data-part="label" dir="auto"></bdi><media-menu-item-indicator force-mount class="media-menu__indicator"> ${z(`check`,{class:`media-icon`})} </media-menu-item-indicator></media-menu-radio-item></template></media-playback-rate-radio-group></media-menu-content><media-menu-content id="settings-captions-menu" class="media-menu__panel"><media-menu-item class="media-menu__back"> ${z(`chevron`,{class:`media-icon media-menu__chevron media-icon--flipped`})} ${v(w)} </media-menu-item><div class="media-menu__separator"></div><media-captions-radio-group class="media-menu__group"><template><media-menu-radio-item class="media-menu__item"><bdi data-part="label" dir="auto"></bdi><media-menu-item-indicator force-mount class="media-menu__indicator"> ${z(`check`,{class:`media-icon`})} </media-menu-item-indicator></media-menu-radio-item></template></media-captions-radio-group></media-menu-content></media-menu><media-tooltip id="settings-tooltip" trigger="settings-trigger" side="top" class="media-tooltip"> ${v(E)} </media-tooltip><media-cast-button commandfor="cast-tooltip" class="media-button media-button--subtle media-button--icon media-button--cast"> ${z(`cast-enter`,{class:`media-icon media-icon--cast-enter`})} ${z(`cast-exit`,{class:`media-icon media-icon--cast-exit`})} </media-cast-button><media-tooltip id="cast-tooltip" side="top" class="media-tooltip"><media-tooltip-label></media-tooltip-label><media-tooltip-shortcut class="media-tooltip__kbd"></media-tooltip-shortcut></media-tooltip><media-airplay-button commandfor="airplay-tooltip" class="media-button media-button--subtle media-button--icon media-button--airplay"> ${z(`airplay-enter`,{class:`media-icon media-icon--airplay-enter`})} ${z(`airplay-exit`,{class:`media-icon media-icon--airplay-exit`})} </media-airplay-button><media-tooltip id="airplay-tooltip" side="top" class="media-tooltip"><media-tooltip-label></media-tooltip-label><media-tooltip-shortcut class="media-tooltip__kbd"></media-tooltip-shortcut></media-tooltip><media-pip-button commandfor="pip-tooltip" class="media-button media-button--subtle media-button--icon media-button--pip"> ${z(`pip-enter`,{class:`media-icon media-icon--pip-enter`})} ${z(`pip-exit`,{class:`media-icon media-icon--pip-exit`})} </media-pip-button><media-tooltip id="pip-tooltip" side="top" class="media-tooltip"><media-tooltip-label></media-tooltip-label><media-tooltip-shortcut class="media-tooltip__kbd"></media-tooltip-shortcut></media-tooltip><media-fullscreen-button commandfor="fullscreen-tooltip" class="media-button media-button--subtle media-button--icon media-button--fullscreen"> ${z(`fullscreen-enter`,{class:`media-icon media-icon--fullscreen-enter`})} ${z(`fullscreen-exit`,{class:`media-icon media-icon--fullscreen-exit`})} </media-fullscreen-button><media-tooltip id="fullscreen-tooltip" side="top" class="media-tooltip"><media-tooltip-label></media-tooltip-label><media-tooltip-shortcut class="media-tooltip__kbd"></media-tooltip-shortcut></media-tooltip></div></media-tooltip-group></media-controls-content></media-controls><media-hotkey keys="Space" action="togglePaused"></media-hotkey><media-hotkey keys="k" action="togglePaused"></media-hotkey><media-hotkey keys="m" action="toggleMuted"></media-hotkey><media-hotkey keys="f" action="toggleFullscreen"></media-hotkey><media-hotkey keys="c" action="toggleSubtitles"></media-hotkey><media-hotkey keys="i" action="togglePictureInPicture"></media-hotkey><media-hotkey keys="ArrowRight" action="seekStep" value="5"></media-hotkey><media-hotkey keys="ArrowLeft" action="seekStep" value="-5"></media-hotkey><media-hotkey keys="l" action="seekStep" value="10"></media-hotkey><media-hotkey keys="j" action="seekStep" value="-10"></media-hotkey><media-hotkey keys="ArrowUp" action="volumeStep" value="0.05"></media-hotkey><media-hotkey keys="ArrowDown" action="volumeStep" value="-0.05"></media-hotkey><media-hotkey keys="0-9" action="seekToPercent"></media-hotkey><media-hotkey keys="Home" action="seekToPercent" value="0"></media-hotkey><media-hotkey keys="End" action="seekToPercent" value="100"></media-hotkey><media-hotkey keys=">" action="speedUp"></media-hotkey><media-hotkey keys="<" action="speedDown"></media-hotkey><media-gesture type="tap" action="togglePaused" pointer="mouse" region="center"></media-gesture><media-gesture type="tap" action="toggleControls" pointer="touch"></media-gesture><media-gesture type="doubletap" action="seekStep" value="-10" region="left"></media-gesture><media-gesture type="doubletap" action="toggleFullscreen" region="center"></media-gesture><media-gesture type="doubletap" action="seekStep" value="10" region="right"></media-gesture><media-status-announcer class="media-sr-only"></media-status-announcer><div class="media-input-indicator"><media-volume-indicator hidden class="media-volume-indicator"><media-volume-indicator-fill class="media-volume-indicator__content"> ${z(`volume-high`,{class:`media-icon media-icon--volume-high`})} ${z(`volume-low`,{class:`media-icon media-icon--volume-low`})} ${z(`volume-off`,{class:`media-icon media-icon--volume-off`})} <div class="media-volume-indicator__progress" aria-hidden="true"></div><media-volume-indicator-value class="media-volume-indicator__value"></media-volume-indicator-value></media-volume-indicator-fill></media-volume-indicator><media-status-indicator hidden actions="toggleSubtitles toggleFullscreen togglePictureInPicture" class="media-status-indicator media-status-indicator--state"><div class="media-status-indicator__content"> ${z(`captions-on`,{class:`media-icon media-icon--captions-on`})} ${z(`captions-off`,{class:`media-icon media-icon--captions-off`})} ${z(`fullscreen-enter`,{class:`media-icon media-icon--fullscreen-enter`})} ${z(`fullscreen-exit`,{class:`media-icon media-icon--fullscreen-exit`})} ${z(`pip-enter`,{class:`media-icon media-icon--pip-enter`})} ${z(`pip-exit`,{class:`media-icon media-icon--pip-exit`})} <media-status-indicator-value class="media-status-indicator__value"></media-status-indicator-value></div></media-status-indicator><media-seek-indicator hidden class="media-seek-indicator"> ${z(`chevron`,{class:`media-icon media-icon--seek`})} <media-seek-indicator-value class="media-seek-indicator__value"></media-seek-indicator-value></media-seek-indicator><media-status-indicator hidden actions="togglePaused" class="media-status-indicator media-status-indicator--playback"> ${z(`play`,{class:`media-icon media-icon--play`})} ${z(`pause`,{class:`media-icon media-icon--pause`})} </media-status-indicator></div></media-container>`}var H=class extends m{static{this.tagName=`video-minimal-skin`}static{this.styles=g(B)}static{this.template=a(V())}};h(_),d(),t(),o(),l(),h(S),h(b),r(),n(),p(),s(),h(P),h(y),h(e),h(M),h(A),h(N),h(C),h(i),h(u),h(j),h(c),h(L),h(I),h(k),h(f),h(F),h(x),h(R),h(H);
//# sourceMappingURL=video-minimal.js.map