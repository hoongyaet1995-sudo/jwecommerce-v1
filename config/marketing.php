<?php

$conversationPilot=json_decode((string)env('MARKETING_CONVERSATION_PILOT_GRANT','{}'),true);
$conversationPilot=is_array($conversationPilot)?$conversationPilot:[];
return [
    'execution_enabled'=>(bool)env('MARKETING_EXECUTION_ENABLED',in_array(env('APP_ENV'),['local','testing'],true)),
    'billing'=>['enabled'=>(bool)env('MARKETING_BILLING_ENABLED',env('APP_ENV')==='local'),'pricing_verified_at'=>'2026-10-23','pricing_valid_until'=>'2026-10-30','included_daily_microusd'=>500000,'included_daily_unlimited_projects'=>array_values(array_filter(array_map('trim',explode(',',(string)env('MARKETING_INCLUDED_DAILY_UNLIMITED_PROJECTS','')))))],
    // Storyboard live transports remain closed for local offline acceptance. No provider model IDs are guessed.
    'storyboard'=>['live_enabled'=>false,'live_grant'=>[],'roles'=>[],'semantic_model'=>null,'presenters'=>[],'voices'=>[],'audio'=>['native'=>[], 'auto_voice'=>[]]],
    'optimization'=>['semantic_enabled'=>(bool)env('MARKETING_SEMANTIC_OPTIMIZATION_ENABLED',true)],
    'editing'=>['enabled'=>(bool)env('MARKETING_EDITING_ENABLED',false),'python_binary'=>env('MARKETING_EDITING_PYTHON_BINARY'),'whisper_model_dir'=>env('MARKETING_EDITING_WHISPER_MODEL_DIR')],
    'workspace_video'=>['enabled'=>(bool)env('MARKETING_WORKSPACE_VIDEO_ENABLED',false)],
    'conversation'=>['enabled'=>(bool)env('MARKETING_CONVERSATION_ENABLED',$conversationPilot['enabled']??false),'open_testing'=>(bool)env('MARKETING_CONVERSATION_OPEN_TESTING',false)],
    // F2 text pilot remains closed until an explicit scoped allowance and fresh pricing check are configured.
    'agent'=>array_replace(['enabled'=>false,'project'=>null,'owner'=>null,'authorization'=>null,'budget_usd'=>'0','max_calls'=>0,'pricing_verified_at'=>null],$conversationPilot),
    'hybrid'=>[
        'enabled'=>(bool)env('MARKETING_HYBRID_ENABLED',false),
        'live_enabled'=>(bool)env('MARKETING_HYBRID_LIVE_ENABLED',false),
        'project'=>env('MARKETING_HYBRID_PROJECT'),
        'authorization'=>['id'=>env('MARKETING_HYBRID_AUTHORIZATION'),'revision'=>env('MARKETING_HYBRID_REVISION'),'signature'=>env('MARKETING_HYBRID_SIGNATURE'),'cap_usd'=>env('MARKETING_HYBRID_CAP_USD'),'max_credits'=>(int)env('MARKETING_HYBRID_MAX_CREDITS',0),'expires_at'=>env('MARKETING_HYBRID_EXPIRES_AT')],
    ],
    'planning'=>[
        'enabled'=>(bool)env('MARKETING_TOOL_PLANNING_ENABLED',false),
        'project'=>env('MARKETING_TOOL_PLANNING_PROJECT'),
        'execution_enabled'=>(bool)env('MARKETING_TOOL_EXECUTION_ENABLED',false),
        // T1-A has no new text or execution authorization. Future gates must be explicit.
        'live_text_enabled'=>(bool)env('MARKETING_TOOL_TEXT_ENABLED',false),
        'text_revision'=>env('MARKETING_TOOL_TEXT_REVISION'),
    ],
    'native'=>[
        'enabled'=>(bool)env('MARKETING_NATIVE_ENABLED',false),
        'live_enabled'=>(bool)env('MARKETING_NATIVE_LIVE_ENABLED',false),
        'api_key'=>env('EVOLINK_API_KEY'),
        'authorization'=>['id'=>env('MARKETING_NATIVE_AUTHORIZATION'),'project'=>env('MARKETING_NATIVE_PROJECT'),'revision'=>env('MARKETING_NATIVE_REVISION'),'signature'=>env('MARKETING_NATIVE_SIGNATURE'),'cap_usd'=>env('MARKETING_NATIVE_CAP_USD'),'expires_at'=>env('MARKETING_NATIVE_EXPIRES_AT')],
    ],
    'creative' => [
        'enabled' => (bool) env('MARKETING_CREATIVE_TEST_ENABLED', false),
        'project' => env('MARKETING_CREATIVE_TEST_PROJECT'),
        'budget_usd' => env('MARKETING_CREATIVE_TEST_USD', '0'),
        'max_calls' => (int) env('MARKETING_CREATIVE_TEST_MAX_CALLS', 0),
        'authorization' => env('MARKETING_CREATIVE_TEST_AUTHORIZATION'),
    ],
    'research' => [
        'enabled' => (bool) env('MARKETING_RESEARCH_ENABLED', false),
        'live_enabled' => false,
        'simulated' => false,
        'free_probe_enabled' => false,
        'free_probe_project' => null,
        'free_probe_run' => null,
        'approved_queries' => null,
        'api_key' => env('TAVILY_API_KEY'),
        'budget_id' => null,
        'text_model' => null,
        'text_call_cap_microusd' => null,
        'pricing_reference' => null,
        'pricing_verified_at' => null,
    ],
    'template' => ['ffmpeg_binary'=>env('MARKETING_TEMPLATE_FFMPEG_BINARY','C:/Users/User/.codex/tools/whatsapp-audio/ffmpeg.exe'),'python_module_path'=>env('MARKETING_TEMPLATE_PYTHON_MODULE_PATH','C:/Users/User/AppData/Roaming/Python/Python312/site-packages')],
    'text_enabled' => (bool) env('MARKETING_TEXT_ENABLED', false),
    'production' => [
        'credit_voice' => [
            'enabled'=>(bool)env('MARKETING_AUDIO_CREDIT_ENABLED',false),
            'id'=>env('MARKETING_AUDIO_CREDIT_AUTHORIZATION'),
            'project'=>env('MARKETING_AUDIO_CREDIT_PROJECT'),
            'revision'=>env('MARKETING_AUDIO_CREDIT_REVISION'),
            'signature'=>env('MARKETING_AUDIO_CREDIT_SIGNATURE'),
            'max_credits'=>(int)env('MARKETING_AUDIO_CREDIT_MAX',0),
            'expires_at'=>env('MARKETING_AUDIO_CREDIT_EXPIRES_AT'),
        ],
        // Internal test spending authorization, not a supplier price. Unset blocks audio budget approval.
        'audio_cap_usd' => env('MARKETING_PRODUCTION_AUDIO_CAP_USD'),
        'live_audio_enabled' => (bool) env('MARKETING_PRODUCTION_LIVE_AUDIO_ENABLED', false),
        'live_audio_project' => env('MARKETING_PRODUCTION_LIVE_AUDIO_PROJECT'),
        'elevenlabs_key' => env('ELEVENLABS_API_KEY'),
        'test_budget' => env('MARKETING_PRODUCTION_TEST_BUDGET'),
        'node_binary' => env('MARKETING_PRODUCTION_NODE_BINARY', 'node'),
        'browser_binary' => env('MARKETING_PRODUCTION_BROWSER_BINARY'),
        'ffmpeg_binary' => env('MARKETING_PRODUCTION_FFMPEG_BINARY'),
        'speed_ffmpeg_binary' => env('MARKETING_SPEED_FFMPEG_BINARY', env('MARKETING_PRODUCTION_FFMPEG_BINARY')),
        'ffprobe_binary' => env('MARKETING_PRODUCTION_FFPROBE_BINARY'),
    ],
];
