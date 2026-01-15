<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/layouts/GuestLayout.vue';
</script>

<template>
    <Head title="Privacy Policy" />

    <GuestLayout title="Privacy Policy">
        <div class="prose prose-gray dark:prose-invert max-w-none">
            <p class="text-muted-foreground">Last updated: January 15, 2025</p>

            <h2>1. Introduction</h2>
            <p>
                Welcome to TryPost ("we," "our," or "us"). We are committed to protecting your personal information
                and your right to privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard
                your information when you use our social media scheduling service.
            </p>

            <h2>2. Information We Collect</h2>
            <h3>2.1 Personal Information</h3>
            <p>We collect personal information that you voluntarily provide to us when you:</p>
            <ul>
                <li>Register for an account</li>
                <li>Connect your social media accounts</li>
                <li>Create and schedule posts</li>
                <li>Contact us for support</li>
            </ul>
            <p>This information may include:</p>
            <ul>
                <li>Name and email address</li>
                <li>Social media account credentials and tokens</li>
                <li>Content you create for posting (text, images, videos)</li>
                <li>Billing information (processed securely by Stripe)</li>
            </ul>

            <h3>2.2 Social Media Data</h3>
            <p>
                When you connect your social media accounts (Facebook, Instagram, LinkedIn, X/Twitter, TikTok, YouTube, Threads),
                we access certain information as permitted by each platform's API, including:
            </p>
            <ul>
                <li>Account profile information (name, username, profile picture)</li>
                <li>Page/channel information for business accounts</li>
                <li>Access tokens required to post on your behalf</li>
            </ul>

            <h3>2.3 Automatically Collected Information</h3>
            <p>We automatically collect certain information when you visit our platform, including:</p>
            <ul>
                <li>IP address and browser type</li>
                <li>Device information</li>
                <li>Usage data and analytics</li>
            </ul>

            <h2>3. How We Use Your Information</h2>
            <p>We use the information we collect to:</p>
            <ul>
                <li>Provide and maintain our service</li>
                <li>Schedule and publish your content to connected social media platforms</li>
                <li>Process your transactions and manage your subscription</li>
                <li>Send you service-related communications</li>
                <li>Respond to your inquiries and provide customer support</li>
                <li>Improve our service and develop new features</li>
                <li>Protect against fraud and unauthorized access</li>
            </ul>

            <h2>4. How We Share Your Information</h2>
            <p>We may share your information in the following situations:</p>
            <ul>
                <li><strong>With Social Media Platforms:</strong> We share your content with the platforms you've connected to publish your scheduled posts.</li>
                <li><strong>With Service Providers:</strong> We work with third-party service providers (hosting, payment processing, analytics) who need access to your information to perform services for us.</li>
                <li><strong>For Legal Compliance:</strong> We may disclose your information if required by law or in response to valid legal requests.</li>
                <li><strong>Business Transfers:</strong> In connection with any merger, sale, or acquisition of our business.</li>
            </ul>
            <p>We do not sell your personal information to third parties.</p>

            <h2>5. Data Security</h2>
            <p>
                We implement appropriate technical and organizational security measures to protect your personal information.
                This includes encryption of sensitive data, secure HTTPS connections, and regular security audits.
                However, no method of transmission over the Internet is 100% secure, and we cannot guarantee absolute security.
            </p>

            <h2>6. Data Retention</h2>
            <p>
                We retain your personal information for as long as your account is active or as needed to provide you services.
                You may request deletion of your account and associated data at any time by contacting us.
            </p>

            <h2>7. Your Privacy Rights</h2>
            <p>Depending on your location, you may have the right to:</p>
            <ul>
                <li>Access the personal information we hold about you</li>
                <li>Request correction of inaccurate information</li>
                <li>Request deletion of your personal information</li>
                <li>Object to or restrict processing of your information</li>
                <li>Data portability</li>
                <li>Withdraw consent where processing is based on consent</li>
            </ul>
            <p>To exercise these rights, please contact us at the email address provided below.</p>

            <h2>8. Third-Party Services</h2>
            <p>
                Our service integrates with third-party social media platforms. Each platform has its own privacy policy,
                and we encourage you to review them:
            </p>
            <ul>
                <li><a href="https://www.facebook.com/privacy/policy" target="_blank" rel="noopener">Meta (Facebook, Instagram, Threads) Privacy Policy</a></li>
                <li><a href="https://www.linkedin.com/legal/privacy-policy" target="_blank" rel="noopener">LinkedIn Privacy Policy</a></li>
                <li><a href="https://twitter.com/privacy" target="_blank" rel="noopener">X (Twitter) Privacy Policy</a></li>
                <li><a href="https://www.tiktok.com/legal/privacy-policy" target="_blank" rel="noopener">TikTok Privacy Policy</a></li>
                <li><a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google (YouTube) Privacy Policy</a></li>
            </ul>

            <h2>9. Children's Privacy</h2>
            <p>
                Our service is not intended for individuals under the age of 18. We do not knowingly collect personal
                information from children. If you believe we have collected information from a child, please contact us immediately.
            </p>

            <h2>10. International Data Transfers</h2>
            <p>
                Your information may be transferred to and processed in countries other than your country of residence.
                These countries may have different data protection laws. We ensure appropriate safeguards are in place
                for such transfers.
            </p>

            <h2>11. Changes to This Privacy Policy</h2>
            <p>
                We may update this Privacy Policy from time to time. We will notify you of any changes by posting the
                new Privacy Policy on this page and updating the "Last updated" date. We encourage you to review this
                Privacy Policy periodically.
            </p>

            <h2>12. Contact Us</h2>
            <p>If you have questions or concerns about this Privacy Policy, please contact us at:</p>
            <p>
                <strong>Email:</strong> privacy@trypost.com<br />
                <strong>Website:</strong> https://trypost.com
            </p>
        </div>
    </GuestLayout>
</template>
