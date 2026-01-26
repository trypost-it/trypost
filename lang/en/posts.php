<?php

return [
    'title' => 'Posts',
    'all_posts' => 'All Posts',
    'new_post' => 'New Post',
    'no_posts' => 'No posts found',
    'start_creating' => 'Start by creating your first post.',
    'manage_posts' => 'Manage all your posts',
    'delete_confirm' => 'Are you sure you want to delete this post?',
    'by' => 'by',

    'actions' => [
        'view' => 'View post',
        'delete' => 'Delete post',
    ],

    'form' => [
        'post_type' => 'Post Type',
        'board' => 'Board',
        'select_board' => 'Select a board',
        'search_board' => 'Search board...',
        'no_board_found' => 'No board found',
        'media' => 'Media',
        'min' => 'Min',
        'uploading' => 'Uploading...',
        'drop_to_upload' => 'Drop to upload',
        'drag_and_drop' => 'Drag & drop or click to upload',
        'photos_and_videos' => 'Photos and videos',
        'photos_only' => 'Photos only',
        'videos_only' => 'Videos only',
        'drag_to_reorder' => 'Drag to reorder',
        'caption' => 'Caption',
        'write_caption' => 'Write your caption...',
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
        'labels' => 'Labels',
        'hashtags' => 'Hashtags',
        'schedule' => 'Schedule',
        'publish' => 'Publish',
        'delete' => 'Delete',
        'settings' => 'Settings',
        'schedule_for' => 'Schedule for',
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
            'title' => 'Enable sync?',
            'description' => 'All platforms will share the same content. Any custom edits made to individual platforms will be replaced with the current content.',
            'cancel' => 'Cancel',
            'action' => 'Enable sync',
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

        'hashtags_modal' => [
            'search' => 'Search hashtags...',
            'no_results' => 'No hashtags found.',
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

    'flash' => [
        'scheduled' => 'Post scheduled successfully!',
        'publishing' => 'Post is being published!',
        'deleted' => 'Post deleted successfully!',
        'cannot_edit_published' => 'Published posts cannot be edited.',
        'connect_first' => 'Connect at least one social network before creating a post.',
    ],

    'errors' => [
        'account_disconnected' => 'Social account is disconnected',
    ],
];
