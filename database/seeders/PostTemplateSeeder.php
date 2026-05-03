<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PostTemplate;
use Illuminate\Database\Seeder;

class PostTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // --- product_launch ---
            [
                'name' => 'Feature launch — carousel',
                'description' => 'Announce a new product feature with a 5-slide story.',
                'category' => 'product_launch',
                'platform' => 'instagram_carousel',
                'content' => "{{brand_name}} just launched something we're excited about. Swipe to see what's new.",
                'slides' => [
                    ['title' => "What's new", 'body' => 'A quick look at the latest update.', 'image_keywords' => ['product', 'launch']],
                    ['title' => 'How it works', 'body' => 'Three steps and you are running.', 'image_keywords' => ['workflow']],
                    ['title' => 'Why it matters', 'body' => 'Faster, simpler, fewer clicks.', 'image_keywords' => ['speed']],
                    ['title' => 'Who is it for', 'body' => 'Built for teams who ship.', 'image_keywords' => ['team']],
                    ['title' => 'Try it now', 'body' => 'Available today. Link in bio.', 'image_keywords' => ['phone', 'app']],
                ],
                'image_count' => 5,
                'image_keywords' => null,
            ],
            [
                'name' => 'Product launch announcement',
                'description' => 'Short LinkedIn post announcing a new product.',
                'category' => 'product_launch',
                'platform' => 'linkedin_post',
                'content' => "Excited to share: {{brand_name}} just launched [Product Name].\n\nHere's why this matters for [your audience]:\n→ [Benefit 1]\n→ [Benefit 2]\n→ [Benefit 3]\n\nLearn more at [link]. We'd love your feedback.",
                'slides' => null,
                'image_count' => 0,
                'image_keywords' => null,
            ],
            // --- promotion ---
            [
                'name' => 'Limited-time offer — feed',
                'description' => 'Promote a time-sensitive deal on Instagram Feed.',
                'category' => 'promotion',
                'platform' => 'instagram_feed',
                'content' => "⏰ Don't miss it. {{brand_name}} is offering [discount]% off [product/service] — today only.\n\nTap the link in bio to grab yours before it's gone.",
                'slides' => null,
                'image_count' => 1,
                'image_keywords' => ['sale', 'offer', 'shopping'],
            ],
            [
                'name' => 'Flash sale — X post',
                'description' => 'Short X post for a flash sale with urgency.',
                'category' => 'promotion',
                'platform' => 'x_post',
                'content' => "🔥 Flash sale at {{brand_name}}!\n\n[Discount]% off [product/service] — ends in 24 hours.\n\nNo code needed → [link]",
                'slides' => null,
                'image_count' => 0,
                'image_keywords' => null,
            ],
            // --- educational ---
            [
                'name' => '5 tips carousel',
                'description' => 'Share five actionable tips in a swipeable carousel.',
                'category' => 'educational',
                'platform' => 'instagram_carousel',
                'content' => '5 things I wish I knew about [topic] sooner. Save this for later. 👇',
                'slides' => [
                    ['title' => 'Tip #1', 'body' => '[First tip — be specific and actionable.]', 'image_keywords' => ['idea', 'lightbulb']],
                    ['title' => 'Tip #2', 'body' => '[Second tip — use a real example if you can.]', 'image_keywords' => ['notebook']],
                    ['title' => 'Tip #3', 'body' => '[Third tip — keep it short.]', 'image_keywords' => ['focus']],
                    ['title' => 'Tip #4', 'body' => '[Fourth tip — something surprising or counterintuitive.]', 'image_keywords' => ['surprise']],
                    ['title' => 'Tip #5', 'body' => '[Fifth tip — finish strong with a takeaway.]', 'image_keywords' => ['success']],
                ],
                'image_count' => 5,
                'image_keywords' => null,
            ],
            [
                'name' => 'How-to guide — LinkedIn',
                'description' => 'Step-by-step educational post for LinkedIn.',
                'category' => 'educational',
                'platform' => 'linkedin_post',
                'content' => "How to [achieve X] in [timeframe] — a step-by-step guide.\n\nStep 1: [Action]\nStep 2: [Action]\nStep 3: [Action]\nStep 4: [Action]\n\nThe key insight most people miss: [insight].\n\nWhich step do you find most challenging? Drop a comment below.",
                'slides' => null,
                'image_count' => 0,
                'image_keywords' => null,
            ],
            // --- behind_the_scenes ---
            [
                'name' => 'Team behind the scenes — feed',
                'description' => 'Humanize your brand by showing the team at work.',
                'category' => 'behind_the_scenes',
                'platform' => 'instagram_feed',
                'content' => "This is what a typical day at {{brand_name}} looks like. 👀\n\nWe believe great work happens when people feel at home. Here's a peek behind the curtain.\n\n#{{brand_name}} #BehindTheScenes",
                'slides' => null,
                'image_count' => 1,
                'image_keywords' => ['office', 'team', 'workspace'],
            ],
            [
                'name' => 'Process reveal — carousel',
                'description' => 'Walk followers through how you create your product or service.',
                'category' => 'behind_the_scenes',
                'platform' => 'instagram_carousel',
                'content' => 'Ever wondered how [product/service] is made? Swipe to see every step. ✨',
                'slides' => [
                    ['title' => 'It starts with research', 'body' => 'Every project begins with understanding the problem deeply.', 'image_keywords' => ['research', 'desk']],
                    ['title' => 'Design & iteration', 'body' => 'We prototype fast and test often.', 'image_keywords' => ['design', 'sketch']],
                    ['title' => 'Building the real thing', 'body' => 'Craft and precision in every detail.', 'image_keywords' => ['craft', 'build']],
                    ['title' => 'The final result', 'body' => 'Quality you can feel the moment you use it.', 'image_keywords' => ['product', 'final']],
                ],
                'image_count' => 4,
                'image_keywords' => null,
            ],
            // --- testimonial ---
            [
                'name' => 'Customer quote — LinkedIn',
                'description' => 'Share a compelling customer testimonial on LinkedIn.',
                'category' => 'testimonial',
                'platform' => 'linkedin_post',
                'content' => "\"[Customer quote about the result they achieved with your product or service.]\"\n— [Customer Name], [Role] at [Company]\n\nThis is exactly why we built {{brand_name}}. Real results for real teams.\n\nRead the full story → [link]",
                'slides' => null,
                'image_count' => 0,
                'image_keywords' => null,
            ],
            [
                'name' => 'Success story — X post',
                'description' => 'Brief success-story format for X.',
                'category' => 'testimonial',
                'platform' => 'x_post',
                'content' => "\"[Short customer quote.]\"\n— @[handle]\n\nThis is why we do what we do at {{brand_name}}. 🙌",
                'slides' => null,
                'image_count' => 0,
                'image_keywords' => null,
            ],
            // --- industry_tip ---
            [
                'name' => 'Industry insight — LinkedIn',
                'description' => 'Share a timely insight or hot take relevant to your industry.',
                'category' => 'industry_tip',
                'platform' => 'linkedin_post',
                'content' => "Here's something most people in [industry] get wrong:\n\n[Counterintuitive statement or hot take]\n\nWhy? Because [reason].\n\nThe smarter approach: [better way to do it].\n\nAt {{brand_name}}, we've seen this play out with hundreds of [customers/teams]. The data is clear.\n\nWhat's your take?",
                'slides' => null,
                'image_count' => 0,
                'image_keywords' => null,
            ],
            [
                'name' => 'Quick stat carousel',
                'description' => 'Present industry stats in a visual carousel format.',
                'category' => 'industry_tip',
                'platform' => 'instagram_carousel',
                'content' => "The [industry] numbers that should be on every marketer's radar this year. 📊",
                'slides' => [
                    ['title' => 'Stat #1', 'body' => '[X]% of [audience] say [finding]. Source: [source]', 'image_keywords' => ['chart', 'data']],
                    ['title' => 'Stat #2', 'body' => '[Y]% increase in [metric] year over year.', 'image_keywords' => ['growth', 'graph']],
                    ['title' => 'Stat #3', 'body' => 'By [year], [projection].', 'image_keywords' => ['future', 'trend']],
                    ['title' => 'What this means for you', 'body' => '[Actionable takeaway based on the stats above.]', 'image_keywords' => ['action', 'plan']],
                ],
                'image_count' => 4,
                'image_keywords' => null,
            ],
            // --- event ---
            [
                'name' => 'Event announcement — LinkedIn',
                'description' => 'Announce an upcoming webinar or live event.',
                'category' => 'event',
                'platform' => 'linkedin_post',
                'content' => "📅 Save the date: {{brand_name}} is hosting [Event Name] on [Date] at [Time].\n\nWhat to expect:\n✔ [Topic 1]\n✔ [Topic 2]\n✔ [Topic 3]\n\nSpots are limited. Register now → [link]",
                'slides' => null,
                'image_count' => 0,
                'image_keywords' => null,
            ],
            [
                'name' => 'Event recap — carousel',
                'description' => 'Recap highlights from a recent event or conference.',
                'category' => 'event',
                'platform' => 'instagram_carousel',
                'content' => 'We just wrapped [Event Name] and it was incredible. Here are the highlights. 🙌',
                'slides' => [
                    ['title' => 'It all started with…', 'body' => '[Opening moment or keynote highlight.]', 'image_keywords' => ['conference', 'stage']],
                    ['title' => 'The session everyone talked about', 'body' => '[Key insight from the standout talk.]', 'image_keywords' => ['presentation', 'crowd']],
                    ['title' => 'Connecting with the community', 'body' => '[Highlight networking or attendee moments.]', 'image_keywords' => ['networking', 'people']],
                    ['title' => 'See you next time', 'body' => 'Follow {{brand_name}} for updates on our next event.', 'image_keywords' => ['celebration']],
                ],
                'image_count' => 4,
                'image_keywords' => null,
            ],
            // --- engagement ---
            [
                'name' => 'This or that — X poll',
                'description' => 'Drive engagement with a simple binary choice question.',
                'category' => 'engagement',
                'platform' => 'x_post',
                'content' => "Quick question for the {{brand_name}} community:\n\n[Option A] or [Option B]?\n\nReply with your pick and why 👇",
                'slides' => null,
                'image_count' => 0,
                'image_keywords' => null,
            ],
            [
                'name' => 'Community question — LinkedIn',
                'description' => 'Spark discussion with an open-ended question to your network.',
                'category' => 'engagement',
                'platform' => 'linkedin_post',
                'content' => "If you could give one piece of advice to someone just starting out in [industry/role], what would it be?\n\nMine: [your answer — be honest and specific]\n\nDrop yours in the comments. Let's build a thread worth bookmarking.",
                'slides' => null,
                'image_count' => 0,
                'image_keywords' => null,
            ],
        ];

        foreach ($templates as $t) {
            PostTemplate::query()->updateOrCreate(
                ['name' => $t['name'], 'platform' => $t['platform']],
                $t,
            );
        }
    }
}
