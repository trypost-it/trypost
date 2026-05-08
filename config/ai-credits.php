<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AI Credit Pricing
    |--------------------------------------------------------------------------
    |
    | Defines how AI usage is converted to credits debited from a workspace's
    | monthly quota. Text scales by tokens; images and videos are flat per call
    | because providers charge per output, not per token.
    |
    */

    'text' => [
        // 1 credit = X tokens (sum of input + output).
        // ceil(total_tokens / tokens_per_credit) is debited per call.
        'tokens_per_credit' => 150,
    ],

    'image' => [
        // Credits charged per image generated, keyed by model id.
        // Falls back to 'default' if the model is not listed.
        'default' => 50,
        'gpt-image-1.5' => 50,
        'gpt-image-1.5-hd' => 100,
        // gpt-image-2 is currently called at quality=low (~$0.01/image at 1024².
        // If we ever expose medium/high to users, add gpt-image-2-medium / -hd.
        'gpt-image-2' => 15,
        'flux-pro' => 30,
    ],

    'video' => [
        'default' => 500,
    ],

];
