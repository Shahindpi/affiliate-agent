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
];
