<?php
/**
 * Email Helper Functions
 * Provides functionality for sending emails using configured SMTP settings
 */

require_once __DIR__ . '/../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Get email configuration from the database
 * 
 * @param mysqli $conn Database connection
 * @return array Email configuration settings
 */
function getEmailConfig($conn) {
    $config = [];
    
    // Try to get from cai_dat table first
    $query = "SELECT khoa, gia_tri FROM cai_dat WHERE nhom = 'email'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $config[$row['khoa']] = $row['gia_tri'];
        }
    } else {
        // Fallback to settings table
        $query = "SELECT setting_key, setting_value FROM settings WHERE setting_group = 'email'";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $config[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    
    return $config;
}

/**
 * Send an email using the configured SMTP settings
 * 
 * @param mysqli $conn Database connection
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $altBody Plain text version of the email body
 * @param array $attachments Optional attachments as ['name' => 'file_path']
 * @return array Success status and message
 */
function sendMail($conn, $to, $subject, $body, $altBody = '', $attachments = []) {
    // Get email configuration
    $config = getEmailConfig($conn);
    
    // Check if essential settings are available
    if (empty($config['smtp_host']) || empty($config['email_sender'])) {
        return [
            'success' => false,
            'message' => 'Thiếu cấu hình email. Vui lòng cập nhật trong phần Cài đặt > Cấu hình email.'
        ];
    }
    
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->Port = !empty($config['smtp_port']) ? (int)$config['smtp_port'] : 587;
        
        // Authentication if credentials are provided
        if (!empty($config['smtp_username']) && !empty($config['smtp_password'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_username'];
            $mail->Password = $config['smtp_password'];
        }
        
        // Encryption
        if (!empty($config['smtp_encryption'])) {
            if ($config['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else if ($config['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        }
        
        // Sender and reply-to
        $senderName = !empty($config['email_sender_name']) ? $config['email_sender_name'] : 'Bug Shop';
        $mail->setFrom($config['email_sender'], $senderName);
        $mail->addReplyTo($config['email_sender'], $senderName);
        
        // Recipients
        if (is_array($to)) {
            foreach ($to as $recipient) {
                $mail->addAddress($recipient);
            }
        } else {
            $mail->addAddress($to);
        }
        
        // Attachments
        if (!empty($attachments)) {
            foreach ($attachments as $name => $path) {
                $mail->addAttachment($path, $name);
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody ? $altBody : strip_tags($body);
        
        // Send the email
        $mail->send();
        
        // Log successful email
        $logQuery = $conn->prepare("
            INSERT INTO email_log (recipient, subject, status, ngay_gui) 
            VALUES (?, ?, 'success', NOW())
        ");
        if ($logQuery) {
            $recipient = is_array($to) ? implode(', ', $to) : $to;
            $logQuery->bind_param("ss", $recipient, $subject);
            $logQuery->execute();
        }
        
        return [
            'success' => true,
            'message' => 'Email đã được gửi thành công.'
        ];
        
    } catch (Exception $e) {
        // Log failed email
        $logQuery = $conn->prepare("
            INSERT INTO email_log (recipient, subject, status, error_message, ngay_gui) 
            VALUES (?, ?, 'failed', ?, NOW())
        ");
        if ($logQuery) {
            $recipient = is_array($to) ? implode(', ', $to) : $to;
            $errorMessage = $mail->ErrorInfo;
            $logQuery->bind_param("sss", $recipient, $subject, $errorMessage);
            $logQuery->execute();
        }
        
        return [
            'success' => false,
            'message' => 'Không thể gửi email: ' . $mail->ErrorInfo
        ];
    }
}

/**
 * Send an email to the admin
 * 
 * @param mysqli $conn Database connection
 * @param string $subject Email subject
 * @param string $body Email body
 * @return array Success status and message
 */
function sendAdminNotification($conn, $subject, $body) {
    $config = getEmailConfig($conn);
    $adminEmail = !empty($config['admin_email']) ? $config['admin_email'] : $config['email_sender'];
    
    return sendMail($conn, $adminEmail, $subject, $body);
}

/**
 * Generate an email template
 * 
 * @param string $title Email title 
 * @param string $content Email content
 * @param array $buttons Optional buttons [['text' => 'Button Text', 'url' => 'https://example.com']]
 * @return string Completed HTML email template
 */
function generateEmailTemplate($title, $content, $buttons = []) {
    $buttonHtml = '';
    if (!empty($buttons)) {
        foreach ($buttons as $button) {
            $buttonHtml .= '
            <tr>
                <td align="center" style="padding: 20px 0;">
                    <a href="' . $button['url'] . '" 
                       style="background-color: #0d6efd; color: #ffffff; padding: 12px 24px; 
                              border-radius: 4px; text-decoration: none; font-weight: bold; 
                              display: inline-block;">
                        ' . $button['text'] . '
                    </a>
                </td>
            </tr>';
        }
    }
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $title . '</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
            <tr>
                <td align="center" bgcolor="#0d6efd" style="padding: 20px 0; color: #ffffff;">
                    <h1 style="margin: 0;">Bug Shop</h1>
                </td>
            </tr>
            <tr>
                <td style="padding: 30px 20px;">
                    <h2 style="color: #333333;">' . $title . '</h2>
                    <div style="color: #666666; line-height: 1.5;">
                        ' . $content . '
                    </div>
                    ' . $buttonHtml . '
                </td>
            </tr>
            <tr>
                <td align="center" style="padding: 20px; background-color: #f8f9fa; color: #666666; font-size: 12px;">
                    <p>© ' . date('Y') . ' Bug Shop. Tất cả các quyền được bảo lưu.</p>
                    <p>Nếu bạn không yêu cầu email này, vui lòng bỏ qua hoặc liên hệ với chúng tôi.</p>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}
