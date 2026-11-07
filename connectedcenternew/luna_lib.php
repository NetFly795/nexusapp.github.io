<?php
require_once __DIR__ . '/luna_secrets.php';

define('LUNA_IMAGE_DAILY_LIMIT', 10);
define('LUNA_POLLINATIONS_BASE', 'https://gen.pollinations.ai');
define('LUNA_CHAT_MODEL', 'gemini-fast');
define('LUNA_IMAGE_MODEL', 'flux');

function lunaEnsureUsageTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS luna_image_usage (
        user_id     INT NOT NULL,
        usage_date  DATE NOT NULL,
        image_count INT NOT NULL DEFAULT 0,
        PRIMARY KEY (user_id, usage_date),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function lunaImageQuota(PDO $pdo, int $userId): array {
    lunaEnsureUsageTable($pdo);
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT image_count FROM luna_image_usage WHERE user_id = ? AND usage_date = ?');
    $stmt->execute([$userId, $today]);
    $row = $stmt->fetch();
    $used = $row ? (int)$row['image_count'] : 0;
    return [
        'limit'     => LUNA_IMAGE_DAILY_LIMIT,
        'used'      => $used,
        'remaining' => max(0, LUNA_IMAGE_DAILY_LIMIT - $used),
    ];
}

function lunaIncrementImageUsage(PDO $pdo, int $userId): void {
    lunaEnsureUsageTable($pdo);
    $today = date('Y-m-d');
    $pdo->prepare('
        INSERT INTO luna_image_usage (user_id, usage_date, image_count)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE image_count = image_count + 1
    ')->execute([$userId, $today]);
}

function lunaSystemPrompt(array $quota): string {
    $remaining = (int)$quota['remaining'];
    return <<<PROMPT
You are Luna, the AI assistant built into Nexus — a personal social and entertainment hub created as a passion project.

## What Nexus is
Nexus is a unified home screen for someone's digital life: a customizable dock gives access to Home (dashboard and widgets), Games (a large library of browser games with a fluid overlay player), Connected Center (servers, channels, direct messages, community games, game invites, and social features tied to a Connected account), and Settings (themes, profiles, and preferences). Users can pick light or dark themes, pin a default page, and use features like a message sidebar, game invites, and Nexus Blocks — a Scratch/TurboWarp-based editor for creating community games that can be published to Connected Center for others to play with cloud variables.

Nexus is not a mega-corporation product. It is a hobby project built with care for community, play, and connection — more like a cozy digital living room than an enterprise dashboard.

## Your personality
You are Luna: helpful, warm, and professional without being cold, robotic, or overly corporate. Use normal, everyday language — the way a smart friendly person would talk, not a press release. You can explore interesting topics with curiosity. When the user corrects you or asks you to adjust tone or focus, follow through gracefully — never get defensive or snippy.

You are AI. You are not a replacement for human relationships, therapy, or real-world action. Compared to a human, you are limited: you cannot truly know the user's life, you cannot act in the physical world, and you should be honest about that when relevant — without being preachy.

## Image generation tool
You have a tool called `generate_image`. When the user wants a picture, illustration, artwork, diagram-as-art, or any visual created for them, call this tool. Do NOT tell the user the internal name of any image engine, model, or provider — simply say you're creating their image.

When calling `generate_image`, write an excellent, detailed visual prompt for the tool: subject, composition, camera angle, art style, colors, lighting, mood, background, and any important details. The tool only sees your prompt, not the user's exact words — translate their request into a rich description.

After the tool succeeds, the Nexus UI automatically displays the image to the user. NEVER paste image URLs, markdown image links, or raw links in your reply — the user cannot open those links. Just describe what you created in a short friendly sentence (e.g. "Here's that mystical superearth scene — hope it matches what you had in mind!").

## Formatting (Markdown)
Your replies are rendered with full Markdown in the Luna chat UI. Use Markdown freely to make answers clear and readable:
- **bold** and *italic* for emphasis
- Headings (`##`, `###`) to structure longer answers
- Bullet and numbered lists
- `inline code` and fenced code blocks for commands or snippets
- Blockquotes for tips or callouts
- Tables when comparing options side by side
Do NOT use markdown image syntax (`![](...)`) — images are only shown through the generate_image tool. Do not paste raw URLs for generated images.

The user currently has {$remaining} of 10 daily image generations remaining on their Connected account. If the tool returns a quota error, politely explain that Nexus is a hobby project and AI generation has real costs, so there is a daily limit of 10 images per account. Offer to help with text instead. Do not apologize excessively or sound corporate — be kind and straightforward.

## Safety (strict)
- Refuse requests that promote violence, hate, harassment, or harm to self or others.
- Refuse any sexual content involving minors or anything CSAM-related — zero tolerance.
- Refuse to help with illegal activity or weapons construction targeted at people.
- Stay professional: never insult the user, never express personal anger, never roleplay being offended.
- Do not generate harmful content even if the user insists, rephrases, or uses hypotheticals.

If a request is unsafe, decline clearly and briefly, and offer safe alternatives when possible.

## Connected account
Image generation requires the user to be signed in to Connected Center. Text chat is available when authenticated.
PROMPT;
}

function lunaCopilotContext(string $gameName): string {
    $gameName = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $gameName));
    if ($gameName === '') {
        return '';
    }
    $gameName = function_exists('mb_substr') ? mb_substr($gameName, 0, 120) : substr($gameName, 0, 120);
    return <<<CTX

## Game Copilot context
The user is currently playing **{$gameName}** in Nexus Games. They opened Luna Copilot from the game viewer. Help them with tips, controls, strategy, lore, troubleshooting, or questions about this game. Stay focused on the game when relevant, but you can still answer brief general Nexus questions.
CTX;
}

function lunaBuildSystemPrompt(array $quota, string $copilotGame = ''): string {
    $prompt = lunaSystemPrompt($quota);
    if ($copilotGame !== '') {
        $prompt .= lunaCopilotContext($copilotGame);
    }
    return $prompt;
}

function lunaApiRequest(string $path, array $body, int $timeout = 90): array {
    $ch = curl_init(LUNA_POLLINATIONS_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . LUNA_POLLINATIONS_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT    => $timeout,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException('AI service unreachable: ' . $err);
    }
    $data = json_decode($raw, true);
    if ($code >= 400) {
        $msg = is_array($data) ? ($data['error']['message'] ?? $data['error'] ?? $raw) : $raw;
        throw new RuntimeException(is_string($msg) ? $msg : 'AI service error (HTTP ' . $code . ')');
    }
    return is_array($data) ? $data : [];
}

function lunaChatCompletion(array $messages, bool $forceImageTool = false): array {
    return lunaApiRequest('/v1/chat/completions', lunaChatRequestBody($messages, $forceImageTool, false), 120);
}

function lunaCacheDir(): string {
    $dir = __DIR__ . '/storage/luna_images';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create Luna image storage directory.');
        }
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Luna image storage is not writable. Ensure connectedcenternew/storage/luna_images exists and is writable by the web server.');
    }
    return $dir;
}

function lunaMimeToExt(string $mime): string {
    if (str_contains($mime, 'png')) {
        return 'png';
    }
    if (str_contains($mime, 'webp')) {
        return 'webp';
    }
    return 'jpg';
}

function lunaImageSignatureSecret(): string {
    return hash('sha256', LUNA_POLLINATIONS_API_KEY . '|luna_image_v1');
}

function lunaImageSignedParams(string $id, int $ttl = 604800): array {
    $exp = time() + $ttl;
    $sig = hash_hmac('sha256', $id . '|' . $exp, lunaImageSignatureSecret());
    return ['exp' => $exp, 'sig' => $sig];
}

function lunaImagePublicUrl(string $id): string {
    $signed = lunaImageSignedParams($id);
    return 'connectedcenternew/api/luna_image.php?id=' . $id
        . '&exp=' . $signed['exp']
        . '&sig=' . $signed['sig'];
}

function lunaVerifyImageSignature(string $id, int $exp, string $sig): bool {
    if ($exp < time() || $id === '' || $sig === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $id . '|' . $exp, lunaImageSignatureSecret());
    return hash_equals($expected, $sig);
}

function lunaFetchCachedImageRow(PDO $pdo, string $id): ?array {
    lunaEnsureCacheTable($pdo);
    $stmt = $pdo->prepare('SELECT id, user_id, mime FROM luna_image_cache WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function lunaCachedImagePath(string $id, string $mime): ?string {
    $base = lunaCacheDir() . '/' . $id;
    $ext = lunaMimeToExt($mime);
    $path = $base . '.' . $ext;
    if (is_file($path)) {
        return $path;
    }
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $fallbackExt) {
        $candidate = $base . '.' . $fallbackExt;
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function lunaServeCachedImage(array $row): void {
    $path = lunaCachedImagePath($row['id'], $row['mime']);
    if (!$path) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Image file missing';
        exit;
    }
    header('Content-Type: ' . $row['mime']);
    header('Cache-Control: private, max-age=86400');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

function lunaEnsureCacheTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS luna_image_cache (
        id         VARCHAR(32) PRIMARY KEY,
        user_id    INT NOT NULL,
        mime       VARCHAR(64) NOT NULL DEFAULT 'image/jpeg',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_luna_cache_user (user_id),
        INDEX idx_luna_cache_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function lunaSaveImageToCache(PDO $pdo, int $userId, string $bytes, string $mime): array {
    lunaEnsureCacheTable($pdo);
    $id = bin2hex(random_bytes(16));
    $ext = lunaMimeToExt($mime);
    $path = lunaCacheDir() . '/' . $id . '.' . $ext;
    if (@file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Could not save generated image to server storage.');
    }
    $pdo->prepare('INSERT INTO luna_image_cache (id, user_id, mime) VALUES (?, ?, ?)')
        ->execute([$id, $userId, $mime]);
    return [
        'id'       => $id,
        'url'      => lunaImagePublicUrl($id),
        'data_url' => 'data:' . $mime . ';base64,' . base64_encode($bytes),
    ];
}

function lunaDetectImageMime(string $bytes): string {
    if (strncmp($bytes, "\x89PNG", 4) === 0) {
        return 'image/png';
    }
    if (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0) {
        return 'image/jpeg';
    }
    if (strncmp($bytes, 'RIFF', 4) === 0 && substr($bytes, 8, 4) === 'WEBP') {
        return 'image/webp';
    }
    return 'image/jpeg';
}

function lunaFetchRemoteImage(string $url): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . LUNA_POLLINATIONS_API_KEY],
        CURLOPT_TIMEOUT        => 120,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $code >= 400) {
        throw new RuntimeException('Could not download generated image' . ($err ? ': ' . $err : ''));
    }
    return $raw;
}

function lunaGenerateImage(PDO $pdo, int $userId, string $prompt): array {
    $data = lunaApiRequest('/v1/images/generations', [
        'model'           => LUNA_IMAGE_MODEL,
        'prompt'          => $prompt,
        'n'               => 1,
        'response_format' => 'url',
        'size'            => '1024x1024',
    ], 120);

    $remoteUrl = $data['data'][0]['url'] ?? $data['url'] ?? null;
    if (!$remoteUrl) {
        $encoded = rawurlencode($prompt);
        $remoteUrl = LUNA_POLLINATIONS_BASE . '/image/' . $encoded . '?model=' . LUNA_IMAGE_MODEL . '&width=1024&height=1024';
    }

    $bytes = lunaFetchRemoteImage($remoteUrl);
    $mime = lunaDetectImageMime($bytes);
    return lunaSaveImageToCache($pdo, $userId, $bytes, $mime);
}

function lunaRunTool(PDO $pdo, int $userId, string $name, array $args): array {
    if ($name !== 'generate_image') {
        return ['error' => 'Unknown tool'];
    }
    $prompt = trim($args['prompt'] ?? '');
    if ($prompt === '') {
        return ['error' => 'A detailed prompt is required.'];
    }
    $quota = lunaImageQuota($pdo, $userId);
    if ($quota['remaining'] <= 0) {
        return [
            'error'   => 'QUOTA_EXCEEDED',
            'message' => 'Daily image limit reached (10 per Connected account per day).',
        ];
    }
    try {
        $saved = lunaGenerateImage($pdo, $userId, $prompt);
        lunaIncrementImageUsage($pdo, $userId);
        return [
            'success'  => true,
            'url'      => $saved['url'],
            'data_url' => $saved['data_url'],
            'prompt'   => $prompt,
        ];
    } catch (Throwable $e) {
        return ['error' => 'Image generation failed: ' . $e->getMessage()];
    }
}

function lunaStripThinking(string $text): string {
    $text = preg_replace('/<thinking>[\s\S]*?<\/thinking>\s*/i', '', $text) ?? $text;
    return trim($text);
}

function lunaSanitizeReply(string $reply, bool $hasImages): string {
    $reply = preg_replace('/!\[[^\]]*\]\([^)]+\)/', '', $reply) ?? $reply;
    $reply = preg_replace('/\[[^\]]*\]\(https?:\/\/gen\.pollinations\.ai[^)]+\)/i', '', $reply) ?? $reply;
    $reply = preg_replace('/https?:\/\/gen\.pollinations\.ai[^\s)>\]]+/i', '', $reply) ?? $reply;
    $reply = trim(preg_replace('/\n{3,}/', "\n\n", $reply) ?? $reply);
    if ($hasImages && $reply === '') {
        return 'Here you go — let me know if you’d like any tweaks.';
    }
    return $reply;
}

function lunaLastUserMessage(array $clientMessages): string {
    for ($i = count($clientMessages) - 1; $i >= 0; $i--) {
        if (($clientMessages[$i]['role'] ?? '') === 'user') {
            return trim($clientMessages[$i]['content'] ?? '');
        }
    }
    return '';
}

function lunaLooksLikeImageRequest(string $text): bool {
    return (bool)preg_match(
        '/\b(generate|create|draw|paint|design|render|illustrate|make me|picture of|image of|photo of|artwork of|art of|visual of|show me a)\b/i',
        $text
    );
}

function lunaParseClientMessages(array $incoming): array {
    $messages = [];
    foreach ($incoming as $m) {
        if (!is_array($m)) {
            continue;
        }
        $role = $m['role'] ?? '';
        $content = trim($m['content'] ?? '');
        if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
            continue;
        }
        if (strlen($content) > 8000) {
            $content = substr($content, 0, 8000);
        }
        $messages[] = ['role' => $role, 'content' => $content];
    }
    if (count($messages) > 40) {
        $messages = array_slice($messages, -40);
    }
    return $messages;
}

function lunaChatRequestBody(array $messages, bool $forceImageTool, bool $stream = false): array {
    $body = [
        'model'    => LUNA_CHAT_MODEL,
        'messages' => $messages,
        'tools'    => [[
            'type' => 'function',
            'function' => [
                'name'        => 'generate_image',
                'description' => 'Create an image from a detailed visual description. Supply a rich prompt covering subject, style, composition, colors, lighting, and mood.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'prompt' => [
                            'type'        => 'string',
                            'description' => 'Detailed visual description for the image to generate',
                        ],
                    ],
                    'required' => ['prompt'],
                ],
            ],
        ]],
        'tool_choice' => $forceImageTool
            ? ['type' => 'function', 'function' => ['name' => 'generate_image']]
            : 'auto',
    ];
    if ($stream) {
        $body['stream'] = true;
    }
    return $body;
}

function lunaSseEmit(array $payload): void {
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    @flush();
}

function lunaBeginSse(): void {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
}

function lunaStreamFilterAppend(string &$state, string $chunk): string {
    $state .= $chunk;
    $state = preg_replace('/<thinking>[\s\S]*?<\/thinking>/i', '', $state) ?? $state;
    if (preg_match('/<thinking/i', $state)) {
        return '';
    }
    $emit = $state;
    $state = '';
    return $emit;
}

function lunaStreamChatCompletion(array $messages, bool $forceImageTool, callable $onDelta): array {
    $body = lunaChatRequestBody($messages, $forceImageTool, true);
    $content = '';
    $filterState = '';
    $toolCalls = [];
    $buffer = '';
    $httpCode = 0;

    $ch = curl_init(LUNA_POLLINATIONS_BASE . '/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . LUNA_POLLINATIONS_API_KEY,
            'Content-Type: application/json',
            'Accept: text/event-stream',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_WRITEFUNCTION  => function ($ch, $data) use (&$buffer, &$content, &$filterState, &$toolCalls, $onDelta) {
            $buffer .= $data;
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $block = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 2);
                if ($block === '' || $block === 'data: [DONE]') {
                    continue;
                }
                if (!str_starts_with($block, 'data: ')) {
                    continue;
                }
                $json = json_decode(substr($block, 6), true);
                if (!is_array($json)) {
                    continue;
                }
                $delta = $json['choices'][0]['delta'] ?? [];
                if (!empty($delta['content'])) {
                    $piece = lunaStreamFilterAppend($filterState, $delta['content']);
                    if ($piece !== '') {
                        $content .= $piece;
                        $onDelta($piece);
                    }
                }
                if (!empty($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $tc) {
                        $idx = $tc['index'] ?? 0;
                        if (!isset($toolCalls[$idx])) {
                            $toolCalls[$idx] = [
                                'id'       => '',
                                'type'     => 'function',
                                'function' => ['name' => '', 'arguments' => ''],
                            ];
                        }
                        if (!empty($tc['id'])) {
                            $toolCalls[$idx]['id'] = $tc['id'];
                        }
                        if (!empty($tc['function']['name'])) {
                            $toolCalls[$idx]['function']['name'] .= $tc['function']['name'];
                        }
                        if (isset($tc['function']['arguments'])) {
                            $toolCalls[$idx]['function']['arguments'] .= $tc['function']['arguments'];
                        }
                    }
                }
            }
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new RuntimeException($err ?: 'AI streaming failed (HTTP ' . $httpCode . ')');
    }

    if ($filterState !== '') {
        $tail = lunaStripThinking($filterState);
        if ($tail !== '') {
            $content .= $tail;
            $onDelta($tail);
        }
    }

    $message = ['role' => 'assistant', 'content' => lunaStripThinking($content)];
    if ($toolCalls) {
        $message['tool_calls'] = array_values($toolCalls);
    }
    return $message;
}

function lunaProcessChatStream(PDO $pdo, int $userId, array $clientMessages, ?int $conversationId = null, string $copilotGame = ''): void {
    $quota = lunaImageQuota($pdo, $userId);
    $userContent = lunaLastUserMessage($clientMessages);
    $messages = array_merge(
        [['role' => 'system', 'content' => lunaBuildSystemPrompt($quota, $copilotGame)]],
        $clientMessages
    );

    $generatedImages = [];
    $maxRounds = 4;
    $forceImageTool = lunaLooksLikeImageRequest($userContent);
    $streamedContent = '';

    for ($round = 0; $round < $maxRounds; $round++) {
        $choice = lunaStreamChatCompletion(
            $messages,
            $forceImageTool && $round === 0,
            function (string $text) use (&$streamedContent) {
                $streamedContent .= $text;
                lunaSseEmit(['type' => 'delta', 'text' => $text]);
            }
        );
        $forceImageTool = false;

        $toolCalls = $choice['tool_calls'] ?? null;
        if (empty($toolCalls)) {
            $reply = lunaSanitizeReply(lunaStripThinking($choice['content'] ?? ''), !empty($generatedImages));
            if ($reply === '' && $streamedContent === '') {
                $reply = 'I’m here — what would you like to talk about?';
            }
            lunaEmitDonePayload(
                $pdo,
                $userId,
                $conversationId,
                $reply,
                $generatedImages,
                lunaImageQuota($pdo, $userId),
                $userContent
            );
            return;
        }

        $messages[] = $choice;
        lunaSseEmit(['type' => 'status', 'text' => 'Creating your image…']);

        foreach ($toolCalls as $tc) {
            $fn = $tc['function']['name'] ?? '';
            $args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
            $result = lunaRunTool($pdo, $userId, $fn, $args);
            if (!empty($result['url'])) {
                $img = [
                    'url'      => $result['url'],
                    'data_url' => $result['data_url'] ?? null,
                    'prompt'   => $result['prompt'] ?? '',
                ];
                $generatedImages[] = $img;
                lunaSseEmit(['type' => 'image', 'image' => $img]);
            }
            $toolPayload = $result;
            unset($toolPayload['url'], $toolPayload['data_url']);
            if (!empty($result['success'])) {
                $toolPayload['message'] = 'Image generated and displayed in the Nexus chat UI. Reply briefly without links or URLs.';
            }
            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => $tc['id'] ?? ('call_' . uniqid()),
                'content'      => json_encode($toolPayload),
            ];
        }

        $streamedContent = '';
    }

    lunaEmitDonePayload(
        $pdo,
        $userId,
        $conversationId,
        lunaSanitizeReply('I ran into a loop while working on that — could you try rephrasing your request?', !empty($generatedImages)),
        $generatedImages,
        lunaImageQuota($pdo, $userId),
        $userContent
    );
}

function lunaEnsureConversationTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS luna_conversations (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        title      VARCHAR(120) NOT NULL DEFAULT 'New chat',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_luna_conv_user_updated (user_id, updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS luna_messages (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id  INT NOT NULL,
        role             ENUM('user','assistant') NOT NULL,
        content          MEDIUMTEXT NOT NULL,
        images_json      JSON DEFAULT NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES luna_conversations(id) ON DELETE CASCADE,
        INDEX idx_luna_msg_conv (conversation_id, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function lunaAutoTitle(string $text): string {
    $t = preg_replace('/\s+/', ' ', trim($text)) ?? '';
    if (function_exists('mb_strlen') && mb_strlen($t) > 48) {
        return mb_substr($t, 0, 45) . '…';
    }
    if (strlen($t) > 48) {
        return substr($t, 0, 45) . '…';
    }
    return $t !== '' ? $t : 'New chat';
}

function lunaRequireConversation(PDO $pdo, int $userId, int $conversationId): array {
    lunaEnsureConversationTables($pdo);
    $stmt = $pdo->prepare('SELECT * FROM luna_conversations WHERE id = ? AND user_id = ?');
    $stmt->execute([$conversationId, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Conversation not found');
    }
    return $row;
}

function lunaCreateConversation(PDO $pdo, int $userId, ?string $title = null): array {
    lunaEnsureConversationTables($pdo);
    $title = $title ?: 'New chat';
    $pdo->prepare('INSERT INTO luna_conversations (user_id, title) VALUES (?, ?)')
        ->execute([$userId, $title]);
    $id = (int)$pdo->lastInsertId();
    return lunaRequireConversation($pdo, $userId, $id);
}

function lunaListConversations(PDO $pdo, int $userId, int $limit = 50): array {
    lunaEnsureConversationTables($pdo);
    $stmt = $pdo->prepare('
        SELECT c.id, c.title, c.created_at, c.updated_at,
               (SELECT content FROM luna_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS preview
        FROM luna_conversations c
        WHERE c.user_id = ?
        ORDER BY c.updated_at DESC
        LIMIT ?
    ');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function lunaGetConversationMessages(PDO $pdo, int $userId, int $conversationId): array {
    lunaRequireConversation($pdo, $userId, $conversationId);
    $stmt = $pdo->prepare('
        SELECT id, role, content, images_json, created_at
        FROM luna_messages
        WHERE conversation_id = ?
        ORDER BY id ASC
    ');
    $stmt->execute([$conversationId]);
    $rows = $stmt->fetchAll();
    $messages = [];
    foreach ($rows as $row) {
        $msg = [
            'role'    => $row['role'],
            'content' => $row['content'],
        ];
        if (!empty($row['images_json'])) {
            $images = json_decode($row['images_json'], true);
            $msg['images'] = is_array($images) ? $images : [];
        }
        $messages[] = $msg;
    }
    return $messages;
}

function lunaImagesForStorage(array $images): ?string {
    if (!$images) {
        return null;
    }
    $stored = [];
    foreach ($images as $img) {
        if (!is_array($img)) {
            continue;
        }
        $stored[] = [
            'url'    => $img['url'] ?? '',
            'prompt' => $img['prompt'] ?? '',
        ];
    }
    return $stored ? json_encode($stored) : null;
}

function lunaSaveChatTurn(PDO $pdo, int $conversationId, string $userContent, string $assistantReply, array $images): string {
    lunaEnsureConversationTables($pdo);
    $imagesJson = lunaImagesForStorage($images);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO luna_messages (conversation_id, role, content) VALUES (?, ?, ?)')
            ->execute([$conversationId, 'user', $userContent]);
        $pdo->prepare('INSERT INTO luna_messages (conversation_id, role, content, images_json) VALUES (?, ?, ?, ?)')
            ->execute([$conversationId, 'assistant', $assistantReply, $imagesJson]);

        $stmt = $pdo->prepare('SELECT title FROM luna_conversations WHERE id = ? FOR UPDATE');
        $stmt->execute([$conversationId]);
        $title = (string)($stmt->fetchColumn() ?: 'New chat');
        if ($title === 'New chat') {
            $title = lunaAutoTitle($userContent);
            $pdo->prepare('UPDATE luna_conversations SET title = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$title, $conversationId]);
        } else {
            $pdo->prepare('UPDATE luna_conversations SET updated_at = NOW() WHERE id = ?')
                ->execute([$conversationId]);
        }
        $pdo->commit();
        return $title;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function lunaDeleteConversation(PDO $pdo, int $userId, int $conversationId): void {
    lunaRequireConversation($pdo, $userId, $conversationId);
    $pdo->prepare('DELETE FROM luna_conversations WHERE id = ? AND user_id = ?')
        ->execute([$conversationId, $userId]);
}

function lunaEmitDonePayload(PDO $pdo, int $userId, ?int $conversationId, string $reply, array $images, array $quota, string $userContent): void {
    $payload = [
        'type'   => 'done',
        'reply'  => $reply,
        'images' => $images,
        'quota'  => $quota,
    ];
    if ($conversationId) {
        try {
            $title = lunaSaveChatTurn($pdo, $conversationId, $userContent, $reply, $images);
            $payload['conversation_id'] = $conversationId;
            $payload['title'] = $title;
        } catch (Throwable $e) {
            // Still finish the stream even if persistence fails.
        }
    }
    lunaSseEmit($payload);
}

function lunaProcessChat(PDO $pdo, int $userId, array $clientMessages, string $copilotGame = ''): array {
    $quota = lunaImageQuota($pdo, $userId);
    $messages = array_merge(
        [['role' => 'system', 'content' => lunaBuildSystemPrompt($quota, $copilotGame)]],
        $clientMessages
    );

    $generatedImages = [];
    $maxRounds = 4;
    $forceImageTool = lunaLooksLikeImageRequest(lunaLastUserMessage($clientMessages));

    for ($round = 0; $round < $maxRounds; $round++) {
        $response = lunaChatCompletion($messages, $forceImageTool && $round === 0);
        $forceImageTool = false;
        $choice = $response['choices'][0]['message'] ?? null;
        if (!$choice) {
            throw new RuntimeException('Empty response from AI');
        }

        $toolCalls = $choice['tool_calls'] ?? null;
        if (empty($toolCalls)) {
            $reply = lunaSanitizeReply(lunaStripThinking(trim($choice['content'] ?? '')), !empty($generatedImages));
            return [
                'reply'  => $reply ?: 'I’m here — what would you like to talk about?',
                'images' => $generatedImages,
                'quota'  => lunaImageQuota($pdo, $userId),
            ];
        }

        $messages[] = $choice;

        foreach ($toolCalls as $tc) {
            $fn = $tc['function']['name'] ?? '';
            $args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
            $result = lunaRunTool($pdo, $userId, $fn, $args);
            if (!empty($result['url'])) {
                $generatedImages[] = [
                    'url'      => $result['url'],
                    'data_url' => $result['data_url'] ?? null,
                    'prompt'   => $result['prompt'] ?? '',
                ];
            }
            $toolPayload = $result;
            unset($toolPayload['url'], $toolPayload['data_url']);
            if (!empty($result['success'])) {
                $toolPayload['message'] = 'Image generated and displayed in the Nexus chat UI. Reply briefly without links or URLs.';
            }
            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => $tc['id'] ?? ('call_' . uniqid()),
                'content'      => json_encode($toolPayload),
            ];
        }
    }

    return [
        'reply'  => lunaSanitizeReply('I ran into a loop while working on that — could you try rephrasing your request?', !empty($generatedImages)),
        'images' => $generatedImages,
        'quota'  => lunaImageQuota($pdo, $userId),
    ];
}
