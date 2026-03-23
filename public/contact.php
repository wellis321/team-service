<?php
require_once dirname(__DIR__) . '/config/config.php';

$pageTitle = 'Contact Us';
$success   = false;
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validatePost()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$name) {
            $error = 'Please enter your name.';
        } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!$subject) {
            $error = 'Please enter a subject.';
        } elseif (!$message) {
            $error = 'Please enter your message.';
        } else {
            $to      = defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'admin@example.com';
            $headers = "From: $to\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8\r\n";
            $body    = "Name: $name\nEmail: $email\nSubject: $subject\n\nMessage:\n$message\n\n"
                     . "Sent: " . date('Y-m-d H:i:s') . "\nHost: " . ($_SERVER['HTTP_HOST'] ?? '') . "\n";
            @mail($to, 'Contact: ' . $subject, $body, $headers);
            $success = true;
        }
    }
}

include INCLUDES_PATH . '/public_header.php';
?>

<style>
.contact-wrap { max-width: 820px; margin: 4rem auto; padding: 0 20px; }
.contact-wrap h1 { font-size: 2rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; }
.contact-wrap .lead { color: #6b7280; margin-bottom: 2rem; font-size: 1.05rem; }
.contact-info {
    background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.5rem;
    padding: 1.5rem; margin-bottom: 2rem;
}
.contact-info h3 { color: #1e40af; font-size: 1.1rem; margin-bottom: 0.5rem; }
.contact-info p { color: #4b5563; line-height: 1.6; font-size: 0.95rem; margin-bottom: 0.25rem; }
.contact-info a { color: #2563eb; text-decoration: none; }
.contact-info a:hover { text-decoration: underline; }
.contact-form { background: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
.contact-form .btn-submit {
    background: #2563eb; color: white; padding: 0.75rem 2rem; border: none;
    border-radius: 0.375rem; font-size: 1rem; font-weight: 600; cursor: pointer;
    transition: background 0.2s;
}
.contact-form .btn-submit:hover { background: #1d4ed8; }
</style>

<div class="contact-wrap">
    <h1><i class="fas fa-envelope" style="color:#2563eb"></i> Contact Us</h1>
    <p class="lead">Have a question about Team Service? Get in touch and we'll get back to you.</p>

    <div class="contact-info">
        <h3>Get in Touch</h3>
        <p>Fill out the form and we'll respond as soon as possible.</p>
        <?php if (defined('CONTACT_EMAIL')): ?>
        <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars(CONTACT_EMAIL); ?>"><?php echo htmlspecialchars(CONTACT_EMAIL); ?></a></p>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <strong><i class="fas fa-check-circle"></i> Message sent!</strong>
        Thank you — we'll be in touch soon.
    </div>
    <?php else: ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="contact-form">
        <?php echo CSRF::tokenField(); ?>
        <div class="form-group">
            <label for="name">Name <span style="color:#dc2626">*</span></label>
            <input type="text" id="name" name="name" required placeholder="Your full name"
                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="email">Email address <span style="color:#dc2626">*</span></label>
            <input type="email" id="email" name="email" required placeholder="your@email.com"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? (Auth::isLoggedIn() ? Auth::getUser()['email'] : '')); ?>">
        </div>
        <div class="form-group">
            <label for="subject">Subject <span style="color:#dc2626">*</span></label>
            <input type="text" id="subject" name="subject" required placeholder="What is your message about?"
                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="message">Message <span style="color:#dc2626">*</span></label>
            <textarea id="message" name="message" required placeholder="Please include as much detail as you can…"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
        </div>
        <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Send Message</button>
    </form>

    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/public_footer.php'; ?>
