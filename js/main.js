$(document).ready(() => {
  // ฟังก์ชันสำหรับทำความสะอาดข้อมูลเพื่อป้องกัน XSS
  function escapeHtml(text) {
    return text
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // สร้างตัวแปรเก็บไฟล์ที่เลือก
  let selectedFiles = [];

  // File preview functionality
  $("#attachments").on("change", function () {
    const filePreview = $("#filePreview");
    const newFiles = Array.from(this.files);

    // เพิ่มไฟล์ใหม่เข้าไปในรายการ
    selectedFiles = selectedFiles.concat(newFiles);

    // อัปเดตการแสดงผลไฟล์
    updateFilePreview();

    // รีเซ็ต input file เพื่อให้สามารถเลือกไฟล์เดิมซ้ำได้
    $(this).val("");
  });

  // ฟังก์ชันอัปเดตการแสดงผลไฟล์
  function updateFilePreview() {
    const filePreview = $("#filePreview");
    filePreview.html("");

    if (selectedFiles.length > 0) {
      selectedFiles.forEach((file, index) => {
        if (file.type.match("image.*")) {
          const reader = new FileReader();
          reader.onload = (e) => {
            filePreview.append(`
              <div class="preview-item" data-index="${index}">
                <img src="${e.target.result}" alt="${escapeHtml(file.name)}">
                <span>${escapeHtml(file.name)}</span>
                <button type="button" class="remove-file" data-index="${index}">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            `);
          };
          reader.readAsDataURL(file);
        } else {
          filePreview.append(`
            <div class="preview-item file" data-index="${index}">
              <i class="fas fa-file"></i>
              <span>${escapeHtml(file.name)}</span>
              <button type="button" class="remove-file" data-index="${index}">
                  <i class="fas fa-times"></i>
                </button>
            </div>
          `);
        }
      });
    }
  }

  // ลบไฟล์เมื่อคลิกที่ปุ่มลบ
  $(document).on("click", ".remove-file", function () {
    const index = $(this).data("index");
    selectedFiles.splice(index, 1);
    updateFilePreview();
  });

  // ฟังก์ชันตรวจสอบความถูกต้องของข้อมูล
  function validateForm() {
    let isValid = true;
    const fullname = $("#fullname").val().trim();
    const reportName = $("#report_name").val().trim();
    const details = $("#details").val().trim();

    // ตรวจสอบชื่อผู้ขอ
    if (fullname === "") {
      isValid = false;
      $("#fullname").addClass("error-input");
    } else {
      $("#fullname").removeClass("error-input");
    }

    // ตรวจสอบชื่อรายงาน
    if (reportName === "") {
      isValid = false;
      $("#report_name").addClass("error-input");
    } else {
      $("#report_name").removeClass("error-input");
    }

    // ตรวจสอบรายละเอียด
    if (details === "") {
      isValid = false;
      $("#details").addClass("error-input");
    } else {
      $("#details").removeClass("error-input");
    }

    return isValid;
  }

  // Form submission with AJAX
  $("#reportRequestForm").on("submit", function (e) {
    e.preventDefault();

    // ตรวจสอบความถูกต้องของข้อมูล
    if (!validateForm()) {
      $("#formMessage")
        .addClass("error")
        .html("กรุณากรอกข้อมูลให้ครบถ้วน")
        .show();
      return;
    }

    // สร้าง FormData จากฟอร์ม
    const formData = new FormData(this);

    // ลบไฟ���์เดิมที่อาจมีใน FormData
    formData.delete("attachments[]");

    // เพิ่มไฟล์ที่เลือกเข้าไปใน FormData
    selectedFiles.forEach((file) => {
      formData.append("attachments[]", file);
    });

    const submitBtn = $("#submitBtn");
    const formMessage = $("#formMessage");

    // Disable button and show loading state
    submitBtn
      .prop("disabled", true)
      .html('<i class="fas fa-spinner fa-spin"></i> กำลังส่งข้อมูล...');
    formMessage.removeClass("success error").html("");

    $.ajax({
      url: "api/submit_request.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: (response) => {
        try {
          const data =
            typeof response === "string" ? JSON.parse(response) : response;

          if (data.status === "success") {
            formMessage.addClass("success").html(data.message);
            $("#reportRequestForm")[0].reset();
            selectedFiles = []; // รีเซ็ตรายการไฟล์
            $("#filePreview").html("");

            // เพิ่มการแจ้งเตือนเพิ่มเติม
            setTimeout(() => {
              alert(
                "ส่งคำขอเรียบร้อยแล้ว หมายเลขคำขอของคุณคือ: " + data.request_id
              );
            }, 500);
          } else {
            formMessage.addClass("error").html(data.message);
          }
        } catch (e) {
          formMessage
            .addClass("error")
            .html("เกิดข้อผิดพลาดในการประมวลผลข้อมูล");
        }
      },
      error: (xhr, status, error) => {
        formMessage
          .addClass("error")
          .html("เกิดข้อผิดพลาดในการส่งข้อมูล: " + error);
      },
      complete: () => {
        // Re-enable button
        submitBtn
          .prop("disabled", false)
          .html('<i class="fas fa-paper-plane"></i> ส่งคำขอ');
      },
    });
  });

  // เพิ่ม CSS สำหรับแสดงข้อผิดพลาดใน input และปุ่มลบไฟล์
  $("<style>")
    .prop("type", "text/css")
    .html(
      `
      .error-input {
        border: 1px solid #ef4444 !important;
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2) !important;
      }
      .preview-item {
        position: relative;
      }
      .remove-file {
        position: absolute;
        top: 5px;
        right: 5px;
        background-color: rgba(239, 68, 68, 0.8);
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
      }
      .remove-file:hover {
        background-color: #ef4444;
        transform: scale(1.1);
      }
    `
    )
    .appendTo("head");
});
