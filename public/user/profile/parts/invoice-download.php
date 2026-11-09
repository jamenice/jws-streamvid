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
 $(".download-invoice-woo").on("click", function (e) {
            e.preventDefault();

            var postId = $(this).data("invoice");

            $.ajax({
                url: jws_script.ajax_url,
                type: "POST",
                data: {
                    action: "get_invoice_html_woo",
                    order_id: postId
                },
                success: function (response) {

                    if (response.success) {

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

                            $("#hidden-invoice-wrapper").hide();
                        });
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function () {
                    alert("Error loading invoice");
                }
            });
        });
});
</script>
