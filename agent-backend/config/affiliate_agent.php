<?php

return [
    'provider' => env('AGENT_AI_PROVIDER', 'mock'),
    'openai_key' => env('OPENAI_API_KEY'),
    'model' => env('AGENT_AI_MODEL', 'gpt-4.1-mini'),
    'input_cost_per_million' => env('AGENT_INPUT_COST_PER_MILLION'),
    'output_cost_per_million' => env('AGENT_OUTPUT_COST_PER_MILLION'),
    'voice_provider' => env('AGENT_VOICE_PROVIDER', 'mock'),
    'elevenlabs_key' => env('ELEVENLABS_API_KEY'),
    'voice_id' => env('ELEVENLABS_VOICE_ID'),
    'voice_model' => env('ELEVENLABS_MODEL', 'eleven_multilingual_v2'),
    'ffmpeg' => env('AGENT_FFMPEG', 'ffmpeg'),
    'ffprobe' => env('AGENT_FFPROBE', 'ffprobe'),
    'render_timeout' => (int) env('AGENT_RENDER_TIMEOUT', 600),
    'max_daily_revisions' => (int) env('AGENT_MAX_DAILY_REVISIONS', 30),
    'admin_url' => env('AGENT_ADMIN_URL', 'http://localhost:3000/admin/affiliate-agent/social-accounts'),
    'oauth' => [
        'pinterest' => ['id' => env('PINTEREST_CLIENT_ID'), 'secret' => env('PINTEREST_CLIENT_SECRET')],
        'youtube' => ['id' => env('GOOGLE_CLIENT_ID'), 'secret' => env('GOOGLE_CLIENT_SECRET')],
        'tiktok' => ['id' => env('TIKTOK_CLIENT_KEY'), 'secret' => env('TIKTOK_CLIENT_SECRET')],
        'meta' => ['id' => env('META_APP_ID'), 'secret' => env('META_APP_SECRET')],
    ],
    'meta_graph_version' => env('META_GRAPH_VERSION', 'v23.0'),
];
