<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class LegalPageSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'description' => 'How Dewdora handles website and connected social account information.',
                'content' => <<<'HTML'
<h2>About this policy</h2><p>Dewdora is operated by [EDIT: legal business name]. Contact us at [EDIT: contact email/address]. This template describes current and planned site and Affiliate Agent features. Review and customize it before publication.</p>
<h2>Information we handle</h2><p>We may receive information you submit through forms or accounts, such as your name, email, contact message and account details. We process website usage and analytics information when analytics or cookies are enabled. Affiliate link interactions may be recorded for attribution and reporting.</p>
<h2>Connected social accounts</h2><p>When an administrator authorizes a social platform, Dewdora receives permitted account, Page or channel identifiers and OAuth access or refresh tokens. We do not receive your social-platform password. The Laravel backend encrypts stored OAuth tokens and uses them to access authorized platform APIs and publish content you have finally approved. You can disconnect an account in the admin dashboard; this removes locally stored tokens. You may also revoke access through the platform itself. Publication history and IDs may remain for auditing.</p>
<h2>AI and affiliate services</h2><p>When configured, content generation and revision may send product facts, scripts, review feedback and related content to OpenAI. Voice generation may send narration text to ElevenLabs. Additional providers will require this policy to be updated before use. We may use affiliate links and receive a commission when visitors purchase or register through them; providers and affiliate networks may process clicks under their own policies.</p>
<h2>Third parties and processing location</h2><p>Social platforms, analytics tools, affiliate networks, OpenAI and ElevenLabs may process data according to their own terms and in other countries. We only connect integrations enabled by the administrator. Review the linked providers' policies for their handling of your data.</p>
<h2>Storage, security and retention</h2><p>We limit administrative access and store social tokens encrypted in the backend. No internet service can guarantee absolute security. We retain content versions, approvals, publishing logs and source records for operating and auditing the service; contact us about deletion requests where applicable. Backup and legal retention requirements may affect deletion.</p>
<h2>Your choices and rights</h2><p>You may ask about, correct or request deletion of personal information where applicable. Administrators can disconnect social accounts and revoke platform access. Cookie or analytics choices depend on the deployment's settings and browser controls.</p>
<h2>Changes and contact</h2><p>We may update this page when practices change. The current version is shown here. Questions and privacy requests: [EDIT: contact email/address].</p>
HTML,
            ],
            'terms' => [
                'title' => 'Terms & Conditions',
                'description' => 'Terms for using Dewdora content and connected services.',
                'content' => <<<'HTML'
<h2>Acceptance and operator</h2><p>By using Dewdora you agree to these terms. Operator: [EDIT: legal business name]. Contact: [EDIT: contact email/address]. Review this template with counsel before publication.</p>
<h2>Site purpose and accuracy</h2><p>Dewdora shares product information, guides and affiliate recommendations. Some content may be generated with AI and reviewed by an administrator. Facts, pricing, availability and platform features can change. Check the provider's official terms and information before relying on a recommendation.</p>
<h2>Affiliate relationships and third parties</h2><p>Some links are affiliate links and may earn Dewdora a commission. Purchases and registrations occur with third parties, whose terms, privacy policies and support apply. Social-platform and API integrations are governed by their providers' policies and permissions.</p>
<h2>Acceptable use and intellectual property</h2><p>Do not misuse the site, attempt unauthorized access, submit unlawful material or interfere with the service. Dewdora's original site content and design may be protected by intellectual property rights; third-party names and assets remain their owners' property.</p>
<h2>Availability and external links</h2><p>We may change, suspend or restrict features or access, including for misuse. External links are provided for convenience and are outside our control. Features may depend on third-party API availability and developer approvals.</p>
<h2>Disclaimers and liability</h2><p>The site is provided as available, subject to applicable law. We do not promise uninterrupted access or that all AI-generated or third-party information is error free. Liability limits, if any, must be interpreted under applicable law; this template does not waive rights that cannot legally be waived.</p>
<h2>Updates and governing law</h2><p>We may update these terms and publish revisions here. Governing law and dispute contact: [EDIT: jurisdiction and contact details after legal review].</p>
HTML,
            ],
            'affiliate-disclosure' => [
                'title' => 'Affiliate Disclosure',
                'description' => 'How Dewdora uses affiliate links and earns commissions.',
                'content' => <<<'HTML'
<h2>Affiliate relationships</h2><p>Dewdora may use affiliate links on this website and in relevant social content. If you purchase or register through an eligible link, Dewdora may receive a commission from the provider or affiliate network. This generally does not add cost for the visitor; check the provider's current price and terms.</p>
<h2>Recommendations and individual posts</h2><p>Affiliate relationships can involve third-party products and services. Individual posts, videos and platform captions should also include a clear disclosure where appropriate. Product details should be checked against current official sources.</p>
<h2>Program-specific notices</h2><p>If Dewdora joins a program with its own required disclosure language, add that wording here and to relevant content before promoting the program. Contact: [EDIT: contact email/address].</p>
HTML,
            ],
        ];
        foreach ($templates as $key => $data) {
            $page = Page::where('legal_key', $key)->orWhere('slug', $key)->first();
            if (!$page) $page = Page::create(['legal_key' => $key, 'title' => $data['title'], 'slug' => $key, 'excerpt' => $data['description'], 'content' => $data['content'], 'status' => true]);
            elseif (!$page->legal_key) $page->update(['legal_key' => $key]);
            $page->seoMeta()->firstOrCreate([], ['meta_title' => $data['title'].' | Dewdora', 'meta_description' => $data['description']]);
        }
    }
}
