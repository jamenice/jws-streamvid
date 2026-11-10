<?php
/**
 * "Download" invoice buttons (.download-invoice-woo, data-invoice = order
 * number): fetches the invoice HTML (get_invoice_html_woo) and saves it as
 * a PDF in the browser. Shared by the Pay Per View and Rented tabs.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="hidden-invoice-wrapper" style="display:none;padding:20px;position:fixed; top:-100%;left:-100%;background:white;"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
<script>
jQuery(document).ready(function($) {

	var failed = <?php echo wp_json_encode( esc_html__( 'The invoice could not be created. Please try again.', 'jws_streamvid' ) ); ?>;

	/* The theme's toast when it is on the page, a plain alert otherwise. */
	function notify(message) {
		var theme = window.jwsThemeModule;

		if (theme && "function" === typeof theme.show_notification && "function" === typeof window.Toastify) {
			theme.show_notification($("<div>").text(message).html(), "error");
		} else {
			window.alert(message);
		}
	}

	/* The WooCommerce handler answers with a bare string, ours with { message }. */
	function errorText(response) {
		var data = response && response.data;

		if (data && "object" === typeof data && data.message) {
			return data.message;
		}

		return "string" === typeof data && data ? data : failed;
	}

	$(".download-invoice-woo").on("click", function (e) {
		e.preventDefault();

		var $button = $(this);
		var postId = $button.data("invoice");

		if ($button.attr("aria-busy")) {
			return;
		}

		$button.attr("aria-busy", "true").prop("disabled", true);

		function done() {
			$("#hidden-invoice-wrapper").hide().empty();
			$button.removeAttr("aria-busy").prop("disabled", false);
		}

		$.ajax({
			url: jws_script.ajax_url,
			type: "POST",
			data: {
				action: "get_invoice_html_woo",
				order_id: postId
			},
			success: function (response) {

				if (!response || !response.success || !response.data || !response.data.html) {
					done();
					notify(errorText(response));
					return;
				}

				if ("function" !== typeof window.html2canvas || !window.PDFLib) {
					done();
					notify(failed);
					return;
				}

				$("#hidden-invoice-wrapper").html(response.data.html).show();

				html2canvas(document.querySelector("#hidden-invoice-wrapper"), { scale: 2 }).then(async function (canvas) {
					const imgData = canvas.toDataURL("image/png", 1.0);

					const { PDFDocument } = PDFLib;
					const pdfDoc = await PDFDocument.create();

					const pageWidth = 595.28;
					const pageHeight = 841.89;

					const page = pdfDoc.addPage([pageWidth, pageHeight]);
					const pngImage = await pdfDoc.embedPng(imgData);

					const imgWidth = pageWidth;
					const imgHeight = (canvas.height * pageWidth) / canvas.width;

					page.drawImage(pngImage, {
						x: 0,
						y: pageHeight - imgHeight,
						width: imgWidth,
						height: imgHeight
					});

					const pdfBytes = await pdfDoc.save();
					const blob = new Blob([pdfBytes], { type: "application/pdf" });
					const link = document.createElement("a");
					link.href = URL.createObjectURL(blob);
					link.download = "invoice-" + postId + ".pdf";
					link.click();

					done();
				}).catch(function () {
					done();
					notify(failed);
				});
			},
			error: function () {
				done();
				notify(failed);
			}
		});
	});
});
</script>
