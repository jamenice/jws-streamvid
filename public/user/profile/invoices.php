<?php
if( ! defined('ABSPATH' ) ){
    exit;
}
if(!jws_streamvid()->get()->profile->_profile_is_owner()) return false;

?>
<div class="invoices-page-profile">
<h5><?php echo esc_html_e('Invoices', 'jws_streamvid'); ?></h5><?php
 
require_once( PMPRO_DIR . '/preheaders/invoice.php' );

ob_start();

get_template_part( 'paid-memberships-pro/pages/invoice' );

$output = ob_get_clean();


echo $output;


?>

</div>

<div id="hidden-invoice-wrapper" style="display:none;padding:20px;position:fixed;top:-100%;left:-100%;background:white;"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>
<script>
jQuery(document).ready(function($) {
$(".download-invoice-pmpro").on("click", function (e) {
            e.preventDefault();

            var invoiceCode = $(this).data("invoice");

            $.ajax({
                url: jws_script.ajax_url,
                type: "POST",
                data: {
                    action: "get_invoice_html_pmpro",
                    invoice: invoiceCode
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
                            link.download = "invoice-pmpro-" + invoiceCode + ".pdf";
                            link.click();

                            $("#hidden-invoice-wrapper").hide();
                        });
                    } else {
                        alert(response.data.message || "Error loading invoice");
                    }
                },
                error: function () {
                    alert("Error loading invoice");
                }
            });
        });
});
</script>