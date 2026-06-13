<?php
session_start();
$pdo = require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/design-system.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['name'] : '';
$userRole = $isLoggedIn ? $_SESSION['role'] : 'guest';

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        // Store contact inquiry in database or log
        $to = 'contact@lyra-academy.com';
        $headers = "From: $email\r\nReply-To: $email\r\n";
        $body = "Name: $name\nEmail: $email\nSubject: $subject\n\nMessage:\n$message";
        // Log instead of sending (dev environment)
        error_log("Contact form: $name <$email>: $subject - $message");
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php lms_head('Contact Us', 'guest'); ?>
</head>
<body class="bg-background text-on-surface">

<?php lms_public_navbar('/47_Contact_Us/index.php'); ?>

<main id="lms-main-content" class="max-w-4xl mx-auto px-lg py-xl">
    <section class="text-center mb-xl">
        <h1 class="font-h1 text-h1 text-on-surface mb-md">Contact Us</h1>
        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto">Have a question? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
    </section>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg text-center">
            <span aria-hidden="true" class="material-symbols-outlined text-3xl text-primary mb-sm">email</span>
            <h3 class="font-semibold text-on-surface mb-xs">Email</h3>
            <p class="text-on-surface-variant">contact@lyra-academy.com</p>
        </div>
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg text-center">
            <span aria-hidden="true" class="material-symbols-outlined text-3xl text-primary mb-sm">phone</span>
            <h3 class="font-semibold text-on-surface mb-xs">Phone</h3>
            <p class="text-on-surface-variant">+1 (555) 123-4567</p>
        </div>
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg text-center">
            <span aria-hidden="true" class="material-symbols-outlined text-3xl text-primary mb-sm">location_on</span>
            <h3 class="font-semibold text-on-surface mb-xs">Location</h3>
            <p class="text-on-surface-variant">123 Music Street, Nashville, TN 37203</p>
        </div>
    </div>

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg max-w-2xl mx-auto">
        <?php if ($sent): ?>
            <div class="text-center py-lg">
                <span aria-hidden="true" class="material-symbols-outlined text-5xl text-green-600 mb-sm">check_circle</span>
                <h2 class="font-h2 text-h2 text-on-surface mb-xs">Message Sent!</h2>
                <p class="text-on-surface-variant">Thank you for contacting us. We'll get back to you shortly.</p>
            </div>
        <?php else: ?>
            <h2 class="font-h2 text-h2 text-on-surface mb-lg">Send us a Message</h2>
            <?php if ($error): ?>
                <div class="bg-error/10 text-error p-sm rounded-lg mb-md font-body-md"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-md">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                    <div>
                        <label for="contact-name" class="block text-sm font-medium text-on-surface mb-xs">Name *</label>
                        <input required name="name" id="contact-name" autocomplete="name" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface" placeholder="Your name"/>
                    </div>
                    <div>
                        <label for="contact-email" class="block text-sm font-medium text-on-surface mb-xs">Email *</label>
                        <input required type="email" name="email" id="contact-email" autocomplete="email" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface" placeholder="your@email.com"/>
                    </div>
                </div>
                <div>
                    <label for="contact-subject" class="block text-sm font-medium text-on-surface mb-xs">Subject</label>
                    <input name="subject" id="contact-subject" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface" placeholder="How can we help?"/>
                </div>
                <div>
                    <label for="contact-message" class="block text-sm font-medium text-on-surface mb-xs">Message *</label>
                    <textarea required name="message" id="contact-message" rows="5" class="w-full bg-surface border border-outline-variant rounded-lg px-3 py-2 text-on-surface" placeholder="Tell us more about your inquiry..."></textarea>
                </div>
                <button type="submit" class="bg-primary text-on-primary px-lg py-sm rounded-lg font-semibold hover:opacity-90 transition-opacity">Send Message</button>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
