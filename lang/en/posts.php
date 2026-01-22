<?php

return [
    'title' => 'Posts',
    'all_posts' => 'All Posts',
    'new_post' => 'New Post',
    'create_post' => 'Create Post',
    'no_posts' => 'No posts found',
    'no_posts_status' => 'No :status posts yet.',
    'start_creating' => 'Start by creating your first post.',
    'manage_posts' => 'Manage all your posts',
    'delete_confirm' => 'Are you sure you want to delete this post?',
    'by' => 'by',

    'actions' => [
        'view' => 'View post',
        'delete' => 'Delete post',
    ],

    'status' => [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'publishing' => 'Publishing',
        'published' => 'Published',
        'partially_published' => 'Partially Published',
        'failed' => 'Failed',
    ],

    'descriptions' => [
        'draft' => 'Posts waiting to be scheduled',
        'scheduled' => 'Posts scheduled for publishing',
        'published' => 'Posts already published',
    ],

    'edit' => [
        'title' => 'Edit Post',
        'view_title' => 'View Post',
        'manage_platforms' => 'Manage platforms',
        'sync' => 'Sync',
        'schedule' => 'Schedule',
        'publish' => 'Publish',
        'saving' => 'Saving...',
        'saved' => 'Saved',
        'scheduled_at' => 'Scheduled:',
        'published_at' => 'Published:',
        'media' => 'Media',
        'caption' => 'Caption',
        'no_caption' => 'No caption',
        'no_content' => 'No content',

        'empty_state' => [
            'title' => 'No platforms selected',
            'description' => 'Select at least one platform to create your post',
        ],

        'delete_modal' => [
            'title' => 'Delete Post',
            'description' => 'Are you sure you want to delete this post? This action cannot be undone.',
            'action' => 'Delete',
            'cancel' => 'Cancel',
        ],

        'sync_enable' => [
            'title' => 'Discard text and sync with :platform?',
            'description' => 'If you enable syncing, you\'ll lose all edits made specifically to other platforms.',
            'confirm_question' => 'Are you sure you want to sync with the :platform version?',
            'cancel' => 'Cancel',
            'action' => 'Sync with :platform',
        ],

        'sync_disable' => [
            'title' => 'Disable sync?',
            'description' => 'Each platform will keep its current content, but future edits will only apply to the platform you\'re editing.',
            'customize_note' => 'You\'ll be able to customize the content for each platform individually.',
            'cancel' => 'Cancel',
            'action' => 'Disable sync',
        ],

        'platforms_dialog' => [
            'title' => 'Select Platforms',
            'description' => 'Choose which platforms to publish this post to.',
        ],

        'validation' => [
            'select_board' => 'Select a board',
            'images_not_supported' => 'Images not supported',
            'videos_not_supported' => 'Videos not supported',
            'max_images' => 'Max :count images',
            'requires_media' => 'Requires media',
            'exceeded' => ':count exceeded',
            'does_not_support_images' => ':platform does not support images',
            'supports_up_to_images' => ':platform supports up to :count images',
            'does_not_support_videos' => ':platform does not support videos',
        ],
    ],

    'content_types' => [
        'instagram_feed' => [
            'label' => 'Feed Post',
            'description' => 'Appears in your feed and profile',
        ],
        'instagram_reel' => [
            'label' => 'Reel',
            'description' => 'Short video up to 90 seconds',
        ],
        'instagram_story' => [
            'label' => 'Story',
            'description' => 'Disappears after 24 hours',
        ],
        'linkedin_post' => [
            'label' => 'Post',
            'description' => 'Standard post with text and media',
        ],
        'linkedin_carousel' => [
            'label' => 'Carousel',
            'description' => 'Swipeable images',
        ],
        'linkedin_page_post' => [
            'label' => 'Post',
            'description' => 'Standard post with text and media',
        ],
        'linkedin_page_carousel' => [
            'label' => 'Carousel',
            'description' => 'Swipeable images',
        ],
        'facebook_post' => [
            'label' => 'Post',
            'description' => 'Standard post on your page',
        ],
        'facebook_reel' => [
            'label' => 'Reel',
            'description' => 'Short video up to 90 seconds',
        ],
        'facebook_story' => [
            'label' => 'Story',
            'description' => 'Disappears after 24 hours',
        ],
        'tiktok_video' => [
            'label' => 'Video',
            'description' => 'Short-form video content',
        ],
        'youtube_short' => [
            'label' => 'Short',
            'description' => 'Vertical video up to 60 seconds',
        ],
        'x_post' => [
            'label' => 'Post',
            'description' => 'Tweet with text and media',
        ],
        'threads_post' => [
            'label' => 'Post',
            'description' => 'Text post with optional media',
        ],
        'pinterest_pin' => [
            'label' => 'Pin',
            'description' => 'Image pin with link',
        ],
        'pinterest_video_pin' => [
            'label' => 'Video Pin',
            'description' => 'Video content',
        ],
        'pinterest_carousel' => [
            'label' => 'Carousel',
            'description' => '2-5 images',
        ],
        'bluesky_post' => [
            'label' => 'Post',
            'description' => 'Text post with optional images',
        ],
        'mastodon_post' => [
            'label' => 'Post',
            'description' => 'Text post with optional media',
        ],
    ],

    'platforms' => [
        'linkedin' => 'LinkedIn',
        'linkedin-page' => 'LinkedIn Page',
        'x' => 'X',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube Shorts',
        'facebook' => 'Facebook Page',
        'instagram' => 'Instagram',
        'threads' => 'Threads',
        'pinterest' => 'Pinterest',
        'bluesky' => 'Bluesky',
        'mastodon' => 'Mastodon',
    ],
];
