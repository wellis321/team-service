<?php
/**
 * Email Class
 * Handles sending emails for the application
 */

class Email {
    
    /**
     * Send email verification link
     */
    public static function sendVerificationEmail($email, $firstName, $verificationToken) {
        $verificationUrl = APP_URL . url('verify-email.php?token=' . urlencode($verificationToken));
        
        $subject = 'Verify Your Email Address - ' . APP_NAME;
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .button { display: inline-block; padding: 12px 24px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . htmlspecialchars(APP_NAME) . "</h1>
                </div>
                <div class='content'>
                    <p>Hello " . htmlspecialchars($firstName) . ",</p>
                    <p>Thank you for registering with " . htmlspecialchars(APP_NAME) . ". Please verify your email address to activate your account.</p>
                    <p style='text-align: center;'>
                        <a href='" . htmlspecialchars($verificationUrl) . "' class='button'>Verify Email Address</a>
                    </p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #666;'>" . htmlspecialchars($verificationUrl) . "</p>
                    <p>This link will expire in 24 hours.</p>
                    <p>If you did not create an account, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated email from " . htmlspecialchars(APP_NAME) . "</p>
                    <p>If you have any questions, please contact: " . (getenv('MAIL_REPLY_TO') ?: 'support@example.com') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return self::sendEmail($email, $subject, $message);
    }
    
    /**
     * Send email using PHP mail() function
     * In production, you may want to use a service like SendGrid, Mailgun, or SMTP
     * Protected so subclasses can call it for application-specific email types.
     */
    protected static function sendEmail($to, $subject, $message) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . APP_NAME . ' <' . (getenv('MAIL_FROM') ?: 'noreply@example.com') . '>',
            'Reply-To: ' . (getenv('MAIL_REPLY_TO') ?: 'support@example.com'),
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
    
    /**
     * Generate a secure random token for email verification
     */
    public static function generateVerificationToken() {
        return bin2hex(random_bytes(32)); // 64 character hex string
    }
    
    /**
     * Notify organisation admin about user needing employee profile
     */
    public static function notifyAdminAboutMissingEmployeeProfile($adminEmail, $adminFirstName, $userDetails, $organisationDetails) {
        $subject = 'Action Required: Employee Profile Needed - ' . APP_NAME;
        
        $userManagementUrl = APP_URL . url('admin/employees.php');
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #2196F3; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .info-box { background-color: #fff; border-left: 4px solid #2196F3; padding: 15px; margin: 15px 0; }
                .button { display: inline-block; padding: 12px 24px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
                .detail-row { margin: 8px 0; }
                .detail-label { font-weight: bold; display: inline-block; width: 150px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . htmlspecialchars(APP_NAME) . "</h1>
                </div>
                <div class='content'>
                    <p>Hello " . htmlspecialchars($adminFirstName) . ",</p>
                    <p>A user in your organisation needs an employee profile to be created so they can access their digital ID card.</p>
                    
                    <div class='info-box'>
                        <h3 style='margin-top: 0;'>User Details:</h3>
                        <div class='detail-row'><span class='detail-label'>Name:</span> " . htmlspecialchars($userDetails['first_name'] . ' ' . $userDetails['last_name']) . "</div>
                        <div class='detail-row'><span class='detail-label'>Email:</span> " . htmlspecialchars($userDetails['email']) . "</div>
                        <div class='detail-row'><span class='detail-label'>Organisation:</span> " . htmlspecialchars($organisationDetails['name']) . "</div>
                        <div class='detail-row'><span class='detail-label'>Account Created:</span> " . date('d/m/Y H:i', strtotime($userDetails['created_at'])) . "</div>
                    </div>
                    
                    <p style='text-align: center;'>
                        <a href='" . htmlspecialchars($userManagementUrl) . "' class='button'>Create Employee Profile</a>
                    </p>
                    
                    <p>To create the employee profile:</p>
                    <ol>
                        <li>Click the button above or visit: <a href='" . htmlspecialchars($userManagementUrl) . "'>" . htmlspecialchars($userManagementUrl) . "</a></li>
                        <li>Select the user from the dropdown</li>
                        <li>Enter a unique employee reference number</li>
                        <li>Click 'Create Employee'</li>
                    </ol>
                    
                    <p>The user will be able to access their digital ID card once the profile is created.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated email from " . htmlspecialchars(APP_NAME) . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return self::sendEmail($adminEmail, $subject, $message);
    }
    
    /**
     * Notify site support about user needing employee profile (when no admin found)
     */
    public static function notifySupportAboutMissingEmployeeProfile($userDetails, $organisationDetails, $contactAttempts = []) {
        $supportEmail = defined('CONTACT_EMAIL') ? CONTACT_EMAIL : (getenv('SUPPORT_EMAIL') ?: getenv('MAIL_REPLY_TO') ?: 'support@example.com');
        $subject = '[URGENT] User Missing Employee Profile - ' . APP_NAME;
        
        $contactAttemptsText = '';
        if (!empty($contactAttempts)) {
            $contactAttemptsText = "<h3>Contact Attempts Made:</h3><ul>";
            foreach ($contactAttempts as $attempt) {
                $contactAttemptsText .= "<li>" . htmlspecialchars($attempt) . "</li>";
            }
            $contactAttemptsText .= "</ul>";
        }
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 700px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f44336; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .info-box { background-color: #fff; border-left: 4px solid #f44336; padding: 15px; margin: 15px 0; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
                .detail-row { margin: 8px 0; }
                .detail-label { font-weight: bold; display: inline-block; width: 180px; }
                .warning { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⚠️ Support Required</h1>
                </div>
                <div class='content'>
                    <p><strong>A user is unable to access their digital ID card because they don't have an employee profile, and no administrator was found in their organisation to assist them.</strong></p>
                    
                    <div class='info-box'>
                        <h3 style='margin-top: 0;'>User Details:</h3>
                        <div class='detail-row'><span class='detail-label'>User ID:</span> " . htmlspecialchars($userDetails['id']) . "</div>
                        <div class='detail-row'><span class='detail-label'>Name:</span> " . htmlspecialchars($userDetails['first_name'] . ' ' . $userDetails['last_name']) . "</div>
                        <div class='detail-row'><span class='detail-label'>Email:</span> " . htmlspecialchars($userDetails['email']) . "</div>
                        <div class='detail-row'><span class='detail-label'>Account Status:</span> " . ($userDetails['is_active'] ? 'Active' : 'Inactive') . "</div>
                        <div class='detail-row'><span class='detail-label'>Email Verified:</span> " . ($userDetails['email_verified'] ? 'Yes' : 'No') . "</div>
                        <div class='detail-row'><span class='detail-label'>Account Created:</span> " . date('d/m/Y H:i', strtotime($userDetails['created_at'])) . "</div>
                        <div class='detail-row'><span class='detail-label'>Last Login:</span> " . ($userDetails['last_login'] ? date('d/m/Y H:i', strtotime($userDetails['last_login'])) : 'Never') . "</div>
                    </div>
                    
                    <div class='info-box'>
                        <h3 style='margin-top: 0;'>Organisation Details:</h3>
                        <div class='detail-row'><span class='detail-label'>Organisation ID:</span> " . htmlspecialchars($organisationDetails['id']) . "</div>
                        <div class='detail-row'><span class='detail-label'>Name:</span> " . htmlspecialchars($organisationDetails['name']) . "</div>
                        <div class='detail-row'><span class='detail-label'>Domain:</span> " . htmlspecialchars($organisationDetails['domain']) . "</div>
                        <div class='detail-row'><span class='detail-label'>Created:</span> " . date('d/m/Y H:i', strtotime($organisationDetails['created_at'])) . "</div>
                    </div>
                    
                    " . $contactAttemptsText . "
                    
                    <div class='warning'>
                        <h3 style='margin-top: 0;'>Action Required:</h3>
                        <p>Please contact the user and/or their organisation to:</p>
                        <ol>
                            <li>Verify the user's employment status</li>
                            <li>Create an employee profile with an appropriate employee reference number</li>
                            <li>Ensure the organisation has at least one active administrator</li>
                        </ol>
                    </div>
                    
                    <p><strong>User Contact:</strong> " . htmlspecialchars($userDetails['email']) . "</p>
                </div>
                <div class='footer'>
                    <p>This is an automated support notification from " . htmlspecialchars(APP_NAME) . "</p>
                    <p>Generated: " . date('d/m/Y H:i:s') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return self::sendEmail($supportEmail, $subject, $message);
    }
}

