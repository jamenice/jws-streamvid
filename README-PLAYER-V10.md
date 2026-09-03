# Player engine: Video.js 7 (legacy) ↔ Video.js 10

Plugin hiện chạy song song hai engine player. Mặc định vẫn là **legacy** — không đổi
gì so với trước. Video.js 10 là tuỳ chọn, bật/tắt bằng một setting.

## Bật / tắt

**Theme Options → Video Global → Player Settings → Player Engine**
(field Redux `video_player_engine`, định nghĩa trong
`themes/streamvid/inc/admin/theme_option.php`)

| Lựa chọn | Ý nghĩa |
| --- | --- |
| Video.js 7 | Player gốc. **Mặc định.** |
| Video.js 10 (beta) | Player web-component mới (`@videojs/html`). |

Trước đây có trang riêng ở Settings → StreamVid Player; trang đó đã bỏ, cùng với hai
tuỳ chọn asset delivery và pin version — engine v10 giờ **luôn** dùng bundle self-host
trong `public/assets/videojs10/`.

> **Sau khi cập nhật:** field mới chưa có trong `jws_option`, nên `current()` tạm đọc lại
> option cũ `jws_streamvid_player_engine` để site không tự nhảy về v7. Lần đầu bạn lưu
> Theme Options, Redux sẽ ghi giá trị của field — **nhớ chọn Video.js 10 rồi Save**, nếu
> không nó lấy default `legacy`. Chọn xong thì đoạn đọc `LEGACY_OPTION` trong
> `class-jws-streamvid-player-engine.php` có thể xoá.

Xem thử một engine mà không đổi setting: thêm `?jws_player=v10` (hoặc
`?jws_player=legacy`) vào URL trang video. Chỉ user có quyền `edit_posts` thấy được
preview này — khách vào URL đó vẫn nhận engine đang cấu hình, nên page cache không bị
nhiễm.

Khoá cứng engine từ `wp-config.php` (setting sẽ bị bỏ qua):

```php
define( 'JWS_STREAMVID_PLAYER_ENGINE', 'legacy' ); // hoặc 'v10'
```

Hoặc bằng filter:

```php
add_filter( 'streamvid/player/engine', function ( $engine ) {
	return is_singular( 'videos' ) ? 'v10' : 'legacy';
} );
```

## Quay lại bản cũ

Chọn lại **Video.js 7 (legacy)** trong trang setting. Đó là toàn bộ việc cần làm —
engine v10 không ghi đè file nào của player cũ:

- `videojs.min.js`, `jws_player.js`, `jws_player.css`, các tech YouTube/Vimeo,
  videojs-ima, chromecast… đều còn nguyên, không sửa một dòng.
- Markup cũ (`<video class="jws_player video-js">` + `data-player`) vẫn được render y
  như trước khi engine là legacy.

Nếu cần khôi phục hoàn toàn về trạng thái trước khi tích hợp, **dùng bản zip**:

```
wp-content/jws-streamvid-backups/jws-streamvid-<timestamp>-pre-videojs-v10.zip
```

> **Đừng dùng `.backup-pre-v10/` một mình nữa.** Thư mục đó chứa bản gốc của 5 file bị
> sửa lúc tích hợp v10, nhưng sau đợt sắp xếp lại thư mục JS (xem mục dưới) chúng trỏ
> tới các đường dẫn cũ như `/js/videojs.min.js` — giờ nằm ở `/js/vendor/`. Chép đè riêng
> mấy file đó vào sẽ làm 404 toàn bộ script của player. Zip mới khôi phục đúng cả cây
> thư mục.

Các mốc backup có sẵn:

| File | Trạng thái |
| --- | --- |
| `...-pre-videojs-v10.zip` | trước khi tích hợp v10 |
| `...-pre-js-reorg.zip` | sau v10, trước khi sắp xếp lại thư mục JS |

## Những gì đã đổi

Thêm mới:

| File | Vai trò |
| --- | --- |
| `includes/class-jws-streamvid-player-engine.php` | Quyết định engine (đọc theme option) |
| `public/assets/js/player/jws_player_v10.js` | Nạp module v10, resume/history/next-episode |
| `public/assets/css/jws_player_v10.css` | Fit player vào khung tỉ lệ + màu theo theme |

Sửa (đều là nhánh `if`, đường legacy giữ nguyên hành vi):

| File | Thay đổi |
| --- | --- |
| `jws-streamvid.php` | require class engine |
| `public/class-jws-streamvid-public.php` | rẽ nhánh enqueue theo engine |
| `public/movies/player.php` | render markup v10 khi engine là v10 |
| `public/assets/js/pages/single_global.js` | `start_player()` return sớm nếu không có `videojs` |
| `public/assets/js/pages/single_video.js` | như trên |

Khi chạy v10, `videojs.min.js` **không** được enqueue. `single_global.js` và
`single_video.js` vốn đã gate phần khởi tạo player bằng `typeof videojs === 'function'`,
nên biến global vắng mặt chính là thứ giữ hai engine không tranh nhau `#videos_player`.
Hai chỗ gọi `start_player()` mà không gate được xử lý bằng guard ngay đầu hàm.

## Cấu trúc thư mục JS

`public/assets/js/` đã được chia nhóm:

```
js/
├── jws-streamvid-public.js    entry global, chạy trên mọi trang
├── vendor/                    thư viện bên thứ ba — không sửa tay
├── player/                    lớp player: jws_player.js (v7), jws_player_v10.js
├── pages/                     script theo template: single_*, archive_*, profile, upload
└── tool/                      như cũ
```

Tham chiếu nằm ở đúng 3 file PHP: `public/class-jws-streamvid-public.php`,
`admin/class-jws-streamvid-admin.php` (màn hình encode cũng dùng videojs), và
`includes/class-jws-streamvid-advertising.php` (VpaidNonLinear.js).

### File hiện không được tham chiếu ở đâu

Không xoá, chỉ dời vào chỗ hợp lý và ghi lại ở đây để bạn quyết định:

| File | Ghi chú |
| --- | --- |
| `vendor/devtools-detect.js` | **0 byte**; có `node_modules/devtools-detect` cạnh đó |
| `vendor/silvermine-videojs-quality-selector.min.js` | 50KB, không enqueue ở đâu |
| `vendor/videojs-seek-buttons.js` | 5.6KB; option `video_seek_button` có đọc nhưng nhánh `if` rỗng |
| `pages/single_video.js` | bản cũ của `single_global.js`, không enqueue ở đâu |

## Hỗ trợ nguồn video

`public/movies/player.php` chọn media element theo `type` của source:

| Nguồn | Element v10 | Module |
| --- | --- | --- |
| MP4 / native | `<video>` | (nằm sẵn trong preset) |
| HLS `.m3u8` | `<hlsjs-video>` | `media/hlsjs-video.js` |
| YouTube | `<youtube-video>` | `media/youtube-video.js` |
| Vimeo | `<vimeo-video>` | `media/vimeo-video.js` |

Dùng `hlsjs-video` chứ không phải `hls-video`: encoder của StreamVid cũng như output
Bunny/Cloudflare phát segment MPEG-TS, chỉ engine hls.js đọc được. Nó vẫn tự chuyển
sang native HLS trên Safari/iOS.

Module được nạp lazy bằng dynamic `import()` — mỗi trang chỉ tải đúng adapter nó cần.

### Thứ tự import: `video.js` trước, media adapter sau

Đừng đổi thứ tự này trong `jws_player_v10.js`, và đừng nạp song song bằng `Promise.all`.

Custom media element (`hlsjs-video`, `youtube-video`, `vimeo-video`) tự đăng ký với
player context lúc nó upgrade. Nếu nó upgrade **trước** khi `<video-player>` được định
nghĩa thì lời đăng ký đó rơi vào hư không: video vẫn phát (autoplay là do trình duyệt,
không phải do player), nhưng store không bao giờ có media target — mọi lần bấm nút đều
ném `StoreError: NO_TARGET`.

Nạp song song sẽ dính lỗi này gần như chắc chắn, vì adapter nhỏ hơn nhiều
(`media/hlsjs-video.js` 362 byte so với `video.js` 67KB) nên về đích trước.

Triệu chứng dễ nhận: player hiện ra, video tự chạy, nhưng bấm play/pause không ăn và
console đầy `NO_TARGET`.

### Click shield lúc khởi động

`.videos_player.vjs-waiting::after` phủ một lớp trong suốt lên player cho tới khi
`jws_player_v10.js` xác nhận media element đã đăng ký xong. Nó chặn cả trường hợp
người dùng bấm trong lúc module còn đang tải, lẫn trường hợp adapter không bao giờ tới.

Phải dùng shield chứ không dùng `pointer-events: none` trên `<video-skin>`: control bên
trong skin tự đặt `pointer-events: auto`, mà descendant thì bật lại được hit-testing bất
kể ancestor nói gì. `::before` đã bị theme dùng cho khung tỉ lệ nên shield nằm ở `::after`.

### Media element dạng iframe cần CSS riêng

Skin chỉ tự căn kích thước cho media nó biết: `<video>` native, và các adapter kiểu
`display: contents` (như `hlsjs-video`) — inner `<video>` của chúng rơi thẳng vào layout
của skin nên được cùng một rule bắt được.

`youtube-video` / `vimeo-video` không thuộc nhóm nào: chúng là `inline-block` bọc một
iframe, nên giữ nguyên 300×150 mặc định của iframe và nằm lọt thỏm ở góc player.
`jws_player_v10.css` cấp box `100% × 100%` cho chúng. Iframe bên trong nằm trong shadow
root và tự ăn theo host, không cần (và không thể) style từ ngoài.

Nếu sau này dùng thêm `twitch-video`, `tiktok-video`, `cloudflare-video` thì chúng đã có
sẵn trong cùng rule đó.

### Khi module không tải được

Thiếu bundle `videojs10/`, bị ad blocker chặn `youtube.com`, hoặc mất mạng: player hiện thông báo lỗi
(`.jws-v10-error`) thay vì để spinner quay mãi. Xem `failPlayer()` trong `jws_player_v10.js`.

## Còn hoạt động trên cả hai engine

Resume position + watch history (`history_post`), autoplay/muted, poster, phụ đề
`<track>`, tự chuyển tập khi hết video, đổi tập / đổi source bằng AJAX.

## Quảng cáo (Google IMA)

Chạy trên cả hai engine, nhưng bằng hai đường khác nhau:

| Engine | Cách chạy |
| --- | --- |
| legacy | `videojs-ima` + `videojs-contrib-ads` (plugin của Video.js 7) |
| v10 | gọi thẳng IMA SDK trong `jws_player_v10.js` — xem `setupAds()` |

`videojs-ima` không có bản cho v10, nhưng bản thân IMA SDK chưa bao giờ cần Video.js:
nó chỉ cần một ad container, một `<video>` để render ad, và một playhead đọc được.
Plugin vốn đã tự viết kiểu này cho trường hợp iOS + YouTube trong `single_global.js`;
`setupAds()` là cùng mô hình, tổng quát hoá ra.

Hỗ trợ VAST và VMAP (preroll / midroll / postroll), skip, click-through, resize khi
đổi fullscreen. Trên `ended` có gọi `adsLoader.contentComplete()` để postroll được phát,
và việc tự chuyển tập bị hoãn lại chừng nào còn ad đang chạy.

Ad luôn render vào một `<video>` riêng, không dùng element nội dung: content có thể là
`<youtube-video>` / `<vimeo-video>` — chúng không phải `HTMLVideoElement` nên không thể
làm chỗ phát ad.

### Ad tag phải là HTTPS

IMA SDK fetch ad tag từ iframe bridge của Google chạy trên HTTPS. Ad tag phục vụ qua
HTTP thuần sẽ bị chặn vì mixed content và báo:

```
AdError 1005 / VAST 900 — There was a problem requesting ads from the server.
Caused by: Error: 6 with HTTP status code: -1
```

Lỗi này xảy ra **giống hệt nhau trên cả legacy lẫn v10** — không liên quan tới engine.
Trên site dev chạy `http://localhost` thì quảng cáo sẽ luôn hỏng như vậy; phải chạy
HTTPS mới test được. Muốn thử nhanh thì tạm thay `ads_tag_url` bằng một VAST tag HTTPS
qua filter `streamvid/player/setup`.

### Trạng thái ad đọc được từ ngoài

```js
document.querySelector('video-player.jws_player_v10').jwsV10.ads
// { active, finished, adCount, remainingTime(), contentComplete(), destroy() }
```

Có event `jws_player_v10_ad_started` bắn trên `document.body`.

Khi ad đang chạy, wrapper có class `jws-v10-ad-playing`; skin bị ẩn và tắt tương tác để
người xem không tua được nội dung đang không hiển thị. Khi `ALL_ADS_COMPLETED` (chỉ event
này, không phải kiểm tra "ad cuối" từng cái — nó race với SDK) thì container bị gỡ hẳn,
nếu không nó sẽ nuốt click và trên vài bản Android còn che luôn video.

## Bo góc khung player

Skin v10 mặc định bo góc `media-container` **1.75rem (28px)** — quá tròn với theme này.
`injectSkinStyles()` ghi đè token ở mọi trang, giá trị tuỳ layout:

| Trang | `.jws-player-global` | bo góc |
| --- | --- | --- |
| `movie/<slug>/play/` | có | **0px** (banner full-bleed) |
| `episodes/<slug>/` | có | **0px** |
| `videos/<slug>/` | không | **10px** |
| `movie/<slug>/` | không | **10px** |

`.jws-player-global` đến từ `content-single-v4-play.php`, và `single-episodes.php` dùng
chung partial đó — nên trang episodes cũng tính là full-bleed.

Đổi giá trị ở biến `--jws-player-radius` trong `jws_player_v10.css`. Đó là nguồn duy nhất:
`styleSkin()` đọc lại biến này rồi mới ghi vào shadow root, nên không có chỗ nào hardcode
số lần hai.

### Trạng thái trước khi `<video-skin>` upgrade

Đây mới là chỗ gây giật thật, và nó **không** liên quan tới `media-container`.

Skin chỉ tồn tại sau khi ES module của nó tải xong — cold cache mất hơn một giây. Trong
lúc đó `<video-skin>` chỉ là unknown element, và các con light-DOM của nó render thô. Đo
được trên trang videos:

```
1065ms  defined=.  poster 986x692  r=0px    ← vuông, và tràn khỏi khung 986x554
2238ms  defined=Y  poster r=10px            ← module về, mới bo góc
```

Tức người xem thấy ảnh poster vuông, sai kích thước, rồi nó nhảy thành player bo góc.

Xử lý bằng `:not(:defined)` trong `jws_player_v10.css`: cho `<video-skin>` lúc chưa upgrade
mang sẵn bo góc và nền đen, poster `object-fit: cover` lấp đầy khung, còn media element thì
ẩn đi. Làm ở CSS chứ không ở JS là có chủ đích — không có đoạn JS nào phải chạy đua với
lần vẽ đầu tiên.

Sau khi sửa, đo lại: `r=10px` ngay từ frame đầu và giữ nguyên qua lúc upgrade.

### Chèn style phải nằm ngay sau khi `video.js` load, không được muộn hơn

`styleSkin()` được gọi trong `.then()` đầu tiên của chuỗi load, ngay sau
`loadModule('video.js')`. Đó là microtask liền sau lúc module định nghĩa `<video-skin>`
— element upgrade và dựng shadow tree trong lúc module chạy — nên stylesheet vào trước
khi trình duyệt vẽ frame đầu tiên.

Đặt muộn hơn trong chuỗi (sau media module, hoặc sau `requestAnimationFrame`) thì **chắc
chắn** có ít nhất một frame vẽ ở 1.75rem rồi mới nhảy về giá trị mới — nhìn thấy rõ là
giật lúc player hiện ra. Đo bằng cách ghi `border-radius` mỗi animation frame từ đầu
trang: sau khi sửa, giá trị đầu tiên quan sát được đã là 10px/0px, không frame nào ở 28px.

Phép kiểm tra nằm ở JS (`playerEl.closest('.jws-player-global')`) chứ không nằm trong
stylesheet chèn vào shadow root, vì selector trong shadow root không với ra được tổ tiên
bên ngoài cây của nó.

Chỗ này rất dễ mất thời gian, vì `--media-container-border-radius` trông như đúng cách
nhưng đặt ở đâu cũng không ăn. Đo cụ thể:

| Cách đặt | Kết quả |
| --- | --- |
| token trên `<video-player>` | 28px — không ăn |
| token trên `<video-skin>` | 28px — không ăn |
| token trên `media-container` (trong shadow) | 28px — không ăn |
| token trên `media-container.media-default-skin` (trong shadow) | **0px** ✓ |

Lý do: skin **khai báo lại** token đó ngay trên `.media-default-skin`, mà khai báo tại
chỗ luôn thắng giá trị thừa kế — nên set từ bất kỳ tổ tiên nào cũng vô nghĩa. Và ngay cả
khi đã vào được shadow root, selector `media-container` trần (0,0,1) vẫn thua rule của
skin (0,1,0); phải dùng compound element+class (0,1,1).

Muốn đổi giá trị thì sửa trong `injectSkinStyles()`, đừng sửa `jws_player_v10.css` — file
đó không với tới shadow root.

Bo tròn dạng viên thuốc ở các nút và control bar là thiết kế của skin, không phải cái này.

## Player logo

Logo từ theme option `player_logo` được chèn vào **cuối `.media-button-group` cuối cùng**
của control bar — tức ngay sau nút fullscreen. Xem `injectLogo()`.

Skin đóng gói giữ control bar trong shadow root, nên cả element lẫn CSS đều phải đưa vào
trong đó; `jws_player_v10.css` không với tới được.

### CSS phải scope qua `.media-button-group`

Skin style các con của control bar bằng selector mạnh hơn một class đơn, nên rule
`.jws-v10-logo` trần **thua cascade dù được append sau cùng** — riêng `display` không bao
giờ ăn. Đo cụ thể:

| Selector | Kết quả |
| --- | --- |
| `.jws-v10-logo` | thua |
| `img.jws-v10-logo` | thua |
| `.media-button-group img.jws-v10-logo` | thắng |

Không dùng `!important`: skin ẩn control bar bằng rule của nó, và `display` important sẽ
làm logo kẹt lại trên màn hình sau khi bar mờ đi.

Việc scope qua `.media-button-group` không thêm ràng buộc mới — nếu skin đổi tên class đó
thì `injectLogo()` cũng không tìm được chỗ chèn và bỏ qua luôn (có `console.warn`), player
vẫn chạy bình thường.

Logo ẩn dưới 767px, giống hành vi cũ của `.vjs-logo-bar`.

## Nút danh sách tập

Điều kiện hiện nút giống hệt legacy: `is_episodes` && `show_ep_list_btn` && danh sách tập
> 1. Vị trí: ngay **trước** `media-fullscreen-button`, đúng như legacy chèn trước
`.vjs-fullscreen-control`.

Icon là inline SVG chứ không dùng glyph Font Awesome như legacy — skin v10 không mang
icon font nào, mà phụ thuộc vào font theme có thể load hoặc không thì sẽ ra nút trống.

### Nút mượn luôn class của skin

Markup dựng theo đúng khuôn các control khác trong bar:

```html
<button class="jws-v10-ep-btn media-button media-button--subtle media-button--icon"
        commandfor="jws-v10-ep-tooltip" aria-label="Episodes">
  <svg class="media-icon" width="18" height="18" fill="currentColor" viewBox="0 0 18 18">…</svg>
</button>
<media-tooltip id="jws-v10-ep-tooltip" side="top" class="media-surface media-tooltip">
  <media-tooltip-label>Episodes</media-tooltip-label>
</media-tooltip>
```

Ba điểm phải theo đúng skin, không tự chế:

- **Class `media-button …`** — chúng ăn cả trên `<button>` thường, và mang theo size,
  bo tròn pill, màu hover, focus ring. Đo được: nút episodes và nút fullscreen đều
  `36x36`, cùng hàng, idle trong suốt, hover cùng ra `rgb(108, 82, 238)`.
- **Icon `fill`, không `stroke`** — icon của skin là path tô đặc trong khung `0 0 18 18`,
  ăn `currentColor`. Vẽ theo kiểu stroke sẽ lệch hẳn về độ dày nét.
- **`media-tooltip` + `commandfor`** — cách skin gắn tooltip. Có nó thì nút hiện tooltip
  hover y như các nút gốc.

Không cần thêm CSS nào cho nút này. `.media-button` reset sẵn background, border, font,
cursor của `<button>` — đã kiểm chứng bằng cách dựng một `<button>` trắng chỉ gắn class
skin: ra `rgba(0,0,0,0)`, `border: 0px none`, font Inter, `cursor: pointer`.

### Panel dùng chung, không viết lại

Panel (`#jws-ep-panel`) do `single_global.js` dựng và được append vào `.videos_player` —
light DOM, giống nhau trên cả hai engine. Nên v10 **dùng lại toàn bộ** panel đó.

Hai chỗ phải chỉnh để dùng lại được:

1. **`jwsSingleGlobal.toggle_episode_panel()`** — hàm mới, tách ra từ chính handler
   `$(document).on('click', '.jws-ep-list-btn')`. Handler cũ giờ gọi hàm này, nên legacy
   không đổi hành vi. Cần tách vì nút của v10 nằm trong shadow root: event từ đó bị
   retarget về element host trước khi tới `document`, nên delegation `.jws-ep-list-btn`
   không bao giờ khớp. `jws_player_v10.js` bind thẳng vào nút rồi gọi hàm này.

2. **`jws_ep_panel.css`** — CSS của nút + panel vốn nằm ở cuối `jws_player.css`
   (dòng 3926–4138), mà file đó không được nạp trên v10. Đã tách nguyên khối sang file
   riêng và enqueue cho **cả hai** engine, đặt sau `jws_player.css` để thứ tự cascade của
   legacy y như cũ. Nội dung không sửa một ký tự.

Handler của nút v10 gọi `stopPropagation()`: `single_global.js` có handler "click ra ngoài
thì đóng panel" gắn ở `document`, và nó kiểm tra `closest('.jws-ep-list-btn')` — điều kiện
không bao giờ đúng với nút trong shadow root, nên nếu để event thoát ra thì panel sẽ đóng
ngay lập tức bởi chính cú click vừa mở nó.

Nút ăn theo trạng thái ẩn/hiện của control bar giống các nút gốc của skin (khi bar ẩn,
`pointer-events` là `none`).

### Fullscreen: panel phải được dời vào trong

`single_global.js` append panel vào `.videos_player`. Với legacy thì đúng — element
fullscreen là `.video-js`, nằm *trong* `.videos_player`, nên panel ở trong vùng fullscreen.

Với v10 thì ngược lại: `.videos_player` là **cha** của element fullscreen. Mọi thứ nằm
ngoài element fullscreen đều ngừng render, nên panel biến mất ngay khi vào fullscreen.
`placeEpisodePanel()` dời panel vào trong lúc vào fullscreen và trả về chỗ cũ lúc thoát.

Hai chi tiết dễ sập bẫy:

**`document.fullscreenElement` nói dối.** Nó trả về `<video-skin>`, nhưng đó là do shadow
retargeting — element thật sự được đưa vào fullscreen là `<media-container>` bên trong
shadow root. Panel append vào `<video-skin>` vẫn render (nó được slot vào trong), nhưng
cha DOM của nó là `<video-skin>` — element này **không** khớp `:fullscreen`. Mà descendant
selector ở stylesheet document thì so trên cây DOM chứ không phải flat tree, nên rule
`:fullscreen .jws-ep-panel` sẵn có không bao giờ ăn. Vì vậy phải dùng class
`.jws-ep-panel--fs`.

**`.jws-ep-panel--fs` cố tình không đặt `bottom`.** Rule `:fullscreen` của legacy nâng
panel lên 52px để tránh control bar; bản v10 để panel ở `bottom: 0` như lúc windowed,
tức là nó nằm chồng lên control bar — giống hệt hành vi windowed của cả hai engine.
Nếu sau này muốn nâng lên thì thêm `bottom` vào chính rule đó trong `jws_ep_panel.css`,
đừng tính toán trong JS.

## Chưa có trên engine v10

- **Chromecast** (`@silvermine/videojs-chromecast`). v10 có `media/google-cast.js`
  nhưng skin đóng gói chưa expose nút cast — cần eject skin mới thêm được.
- **Skin `jws` tự viết**: menu share, zoom, related, logo overlay, chapter markers.
  Toàn bộ nằm trong `jws_player.js` và build trên `videojs.registerPlugin`, không
  chuyển thẳng sang v10 được.
- **Menu chất lượng từ `quality_lists`.** v10 đọc rendition từ manifest HLS thay vì
  từ danh sách source dựng tay. Khi post có `quality_lists`, engine v10 phát entry đầu
  tiên (đúng cái legacy đánh dấu default).
- **`videojs.hotkeys`** — skin v10 có hotkey riêng, không cấu hình được như plugin cũ.

Nếu cần những thứ trên, giữ engine legacy.

## Một khác biệt cần biết: URL video nằm trong HTML gốc

Legacy nhét source đã base64 vào `data-player` rồi JS mới giải mã và gỡ attribute đi sau
500ms. Engine v10 phải đặt `src` thật lên media element ngay từ HTML server trả về, nên
URL video (và các URL trong `quality_lists`) xuất hiện trong view-source.

Đây là che mắt chứ không phải bảo mật — legacy cũng lộ URL ngay khi player khởi tạo — nhưng
nếu đang dựa vào việc URL không có trong HTML gốc thì cần biết. `jws_get_security_video_url()`
vẫn chạy như cũ trên cả hai engine; muốn chặn thật thì dùng signed URL ở tầng CDN.

## Self-host assets (bắt buộc)

Engine v10 **luôn** nạp module từ `public/assets/videojs10/`. Không còn tuỳ chọn CDN —
thiếu thư mục này là player không chạy (có overlay báo lỗi). Cách dựng:

```bash
npm pack @videojs/html@10.0.0-beta.32
tar -xzf videojs-html-10.0.0-beta.32.tgz
# bỏ bản dev và sourcemap: 890 file / 27MB -> 410 file / 6.4MB
find package/cdn -name '*.dev.js' -delete
find package/cdn -name '*.map' -delete
rm -rf wp-content/plugins/jws-streamvid/public/assets/videojs10
cp -R package/cdn wp-content/plugins/jws-streamvid/public/assets/videojs10
```

Không cần bật gì thêm — engine v10 luôn đọc từ thư mục này.

### Phải copy CẢ thư mục `cdn/`

Đây là lỗi dễ mắc nhất. Entry `video.js` import 9 chunk có content-hash nằm cạnh nó
(`context-*.js`, `ui-*.js`, `skin-element-*.js`…). Copy lẻ mình `video.js` thì file đó
vẫn trả về 200, nhưng import graph gãy và console báo:

```
[StreamVid] Video.js 10 failed to load: TypeError: Failed to fetch dynamically
imported module: .../assets/videojs10/video.js
```

Thông báo trỏ vào entry chứ không trỏ vào chunk thiếu, nên rất dễ tưởng là lỗi entry.

Hash đổi theo từng bản beta, nên **đừng trộn file giữa hai version**. Nâng version thì
xoá cả thư mục rồi copy lại.

### Đo được sau khi self-host

Thời điểm player hiện ra, cache tắt hoàn toàn:

| | Trước (CDN jsDelivr) | Nay (self-hosted) |
| --- | --- | --- |
| videos (HLS) | 2.4–3.2s | **1.7–2.2s** |
| movie `/play/` | ~1.4s | ~1.7s |

Và 0 request ra ngoài: toàn bộ 39–41 module đều từ local.

## Lưu ý về version

Video.js 10 đang là **beta** (`10.0.0-beta.32` tại thời điểm tích hợp). Trang setting
pin version cụ thể — đừng để float, vì API còn thay đổi giữa các bản beta.
Changelog: <https://github.com/videojs/v10/blob/main/CHANGELOG.md>
