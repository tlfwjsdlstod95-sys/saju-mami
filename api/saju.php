<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$ANTHROPIC_API_KEY = getenv('ANTHROPIC_API_KEY') ?: 'sk-ant-여기에본인키입력';

$RATE_LIMIT_PER_HOUR = 20;
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip = explode(',', $ip)[0];
$rateFile = sys_get_temp_dir() . '/saju_rate_' . md5($ip);
$now = time();
$windowStart = $now - 3600;
$attempts = [];
if (file_exists($rateFile)) {
    $data = file_get_contents($rateFile);
    $attempts = $data ? json_decode($data, true) : [];
    if (!is_array($attempts)) $attempts = [];
}
$attempts = array_filter($attempts, fn($t) => $t > $windowStart);
if (count($attempts) >= $RATE_LIMIT_PER_HOUR) {
    http_response_code(429);
    echo json_encode(['error' => '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.']);
    exit;
}
$attempts[] = $now;
file_put_contents($rateFile, json_encode(array_values($attempts)));

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!$input || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => '잘못된 요청입니다.']);
    exit;
}

$name = trim($input['name'] ?? '');
$gender = trim($input['gender'] ?? '');
$year = intval($input['year'] ?? 0);
$month = intval($input['month'] ?? 0);
$day = intval($input['day'] ?? 0);
$hour = isset($input['hour']) ? intval($input['hour']) : -1;
$sajuStr = trim($input['sajuStr'] ?? '');
$ohStr = trim($input['ohStr'] ?? '');

if (empty($name) || strlen($name) > 30) {
    http_response_code(400);
    echo json_encode(['error' => '이름이 올바르지 않습니다.']);
    exit;
}
if (!in_array($gender, ['남', '여'])) {
    http_response_code(400);
    echo json_encode(['error' => '성별이 올바르지 않습니다.']);
    exit;
}
if ($year < 1900 || $year > 2100) {
    http_response_code(400);
    echo json_encode(['error' => '연도가 올바르지 않습니다.']);
    exit;
}
if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
    http_response_code(400);
    echo json_encode(['error' => '날짜가 올바르지 않습니다.']);
    exit;
}
if (empty($sajuStr) || strlen($sajuStr) > 200) {
    http_response_code(400);
    echo json_encode(['error' => '사주 정보가 올바르지 않습니다.']);
    exit;
}

$hourStr = $hour >= 0 ? "{$hour}시" : '(시간 미입력)';

$prompt = "이름: {$name}, 성별: {$gender}, 생년월일: {$year}년 {$month}월 {$day}일 {$hourStr}
사주팔자: {$sajuStr}
오행 구성: {$ohStr}

위 사주를 한국 전통 명리학에 기반하여 분석하고, 반드시 아래 JSON 형식으로만 응답하세요. JSON 외 다른 텍스트는 절대 포함하지 마세요.

{
  \"headline\": \"20자 내외의 시적이고 감성적인 한 줄 제목 (예: 섬세한 칼날 위에 핀 꽃처럼 빛날 당신)\",
  \"intro\": \"{$name}님을 직접 호명하며 시작하는 4~5문장의 개인화된 소개. 일주의 특성, 오행 구성의 의미, 전반적인 기질을 명리학 용어와 감성적 비유를 섞어 풀어내기. 따뜻하지만 통찰력 있는 톤.\",
  \"sections\": [
    {\"icon\": \"sparkles\", \"title\": \"15자 이내 매력적인 제목\", \"content\": \"성격과 기질에 대한 3~4문장 분석. 사주의 구체적 요소를 근거로 들기.\"},
    {\"icon\": \"briefcase\", \"title\": \"직업과 재능 관련 매력적인 제목\", \"content\": \"적합한 직업/재능 분야 3~4문장. 일간과 오행에 기반.\"},
    {\"icon\": \"heart\", \"title\": \"사랑과 인연 관련 매력적인 제목\", \"content\": \"사랑/인간관계 스타일 3~4문장. 사주에 기반.\"},
    {\"icon\": \"coin\", \"title\": \"재물운 관련 매력적인 제목\", \"content\": \"재물운과 금전 패턴 3~4문장.\"},
    {\"icon\": \"activity\", \"title\": \"건강 관련 따뜻한 제목\", \"content\": \"건강 주의사항 3~4문장. 오행 균형 기반.\"},
    {\"icon\": \"calendar-event\", \"title\": \"올해 운세 매력적인 제목\", \"content\": \"2025~2026년 운세 흐름 3~4문장.\"}
  ]
}

각 섹션 제목은 saju-kid.com 스타일처럼 평서문이 아닌 시적이고 호기심을 자극하는 카피로 작성하세요. 예: '완벽주의라는 가면 뒤에 숨은 자기검열을 멈추세요', '재물 창고를 깔고 앉은 당신, 자산가의 기운이 강력하게 흐르네요'";

$payload = [
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 2500,
    'messages' => [
        ['role' => 'user', 'content' => $prompt]
    ]
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01'
    ],
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'API 연결 실패: ' . $curlErr]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code(502);
    $errInfo = json_decode($response, true);
    $errMsg = $errInfo['error']['message'] ?? "API 오류 {$httpCode}";
    echo json_encode(['error' => $errMsg]);
    exit;
}

$data = json_decode($response, true);
if (!$data || !isset($data['content'])) {
    http_response_code(502);
    echo json_encode(['error' => 'API 응답 형식 오류']);
    exit;
}

$rawText = '';
foreach ($data['content'] as $block) {
    if (($block['type'] ?? '') === 'text') {
        $rawText .= $block['text'];
    }
}

if (preg_match('/\{[\s\S]*\}/', $rawText, $matches)) {
    $jsonStr = $matches[0];
    $aiResult = json_decode($jsonStr, true);
    if ($aiResult && is_array($aiResult)) {
        echo json_encode($aiResult, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

http_response_code(502);
echo json_encode(['error' => 'AI 응답을 파싱할 수 없습니다.']);
