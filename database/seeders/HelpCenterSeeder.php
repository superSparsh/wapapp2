<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\TutorialVideo;
use Illuminate\Database\Seeder;

class HelpCenterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFaqs();
        $this->seedTutorials();
    }

    private function seedFaqs(): void
    {
        $faqs = [
            [
                'heading' => 'Template Categorization',
                'slug' => 'template-categorization',
                'sort_order' => 1,
                'description' => <<<'HTML'
<h3>Marketing templates</h3>
<p>Marketing templates are our most flexible. They can enable businesses to achieve a wide range of goals, from generating awareness to driving sales and more.</p>
<h3>Utility templates</h3>
<p>Utility templates are typically triggered by a user action or request. They must include specifics about the active or ongoing transaction, account, subscription, or interaction to which they relate.</p>
HTML,
            ],
            [
                'heading' => 'Messaging Limits',
                'slug' => 'messaging-limits',
                'sort_order' => 2,
                'description' => '<p>WhatsApp messaging limits depend on your phone number quality rating and display name verification status. Higher quality ratings unlock higher daily conversation limits.</p>',
            ],
            [
                'heading' => 'Messaging Quality',
                'slug' => 'messaging-quality',
                'sort_order' => 3,
                'description' => '<p>Maintain high quality by sending relevant, timely messages. High block and report rates can reduce your messaging tier.</p>',
            ],
            [
                'heading' => 'Conversation Based Pricing',
                'slug' => 'conversation-based-pricing',
                'sort_order' => 4,
                'description' => '<p>WhatsApp charges per 24-hour conversation window. Marketing, utility, authentication, and service conversations are billed at different rates.</p>',
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::query()->updateOrCreate(
                ['slug' => $faq['slug']],
                [
                    'heading' => $faq['heading'],
                    'description' => $faq['description'],
                    'is_active' => true,
                    'sort_order' => $faq['sort_order'],
                ],
            );
        }
    }

    private function seedTutorials(): void
    {
        if (TutorialVideo::query()->exists()) {
            return;
        }

        $videos = [
            [
                'title' => 'Dashboard Overview',
                'module_name' => 'DASHBOARD - Sub-module 1: Getting Started',
                'youtube_id' => 'dQw4w9WgXcQ',
                'description' => 'Learn how to navigate the dashboard and monitor key metrics.',
                'duration' => '2:00',
                'sort_order' => 1,
            ],
            [
                'title' => 'Understanding Analytics Cards',
                'module_name' => 'DASHBOARD - Sub-module 1: Getting Started',
                'youtube_id' => 'dQw4w9WgXcQ',
                'description' => 'Review campaign, inbox, and wallet analytics at a glance.',
                'duration' => '3:15',
                'sort_order' => 2,
            ],
            [
                'title' => 'Inbox Basics',
                'module_name' => 'INBOX',
                'youtube_id' => 'dQw4w9WgXcQ',
                'description' => 'Reply to customers, assign conversations, and use templates.',
                'duration' => '4:30',
                'sort_order' => 3,
            ],
            [
                'title' => 'Create Your First Chatbot',
                'module_name' => 'AUTOMATION',
                'youtube_id' => 'dQw4w9WgXcQ',
                'description' => 'Build a simple welcome flow with buttons and template nodes.',
                'duration' => '6:00',
                'sort_order' => 4,
            ],
        ];

        foreach ($videos as $video) {
            TutorialVideo::query()->create($video);
        }
    }
}
