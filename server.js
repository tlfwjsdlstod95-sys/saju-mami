const express = require('express');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;
const ANTHROPIC_API_KEY = process.env.ANTHROPIC_API_KEY;

if (!ANTHROPIC_API_KEY) {
  console.error('환경변수 ANTHROPIC_API_KEY를 설정해주세요.');
  process.exit(1);
}

app.use(express.json({ limit: '10kb' }));
app.use(express.static(path.join(__dirname)));

const rateLimitMap = new Map();
const RATE_LIMIT = 20;
const WINDOW_MS = 60 * 60 * 1000;

function rateLimit(req, res, next) {
  const ip = req.headers['x-forwarded-for']?.split(',')[0].trim() || req.socket.remoteAddress || 'unknown';
  const now = Date.now();
  const arr = (rateLimitMap.get(ip) || []).filter(t => t > now - WINDOW_MS);
  if (arr.length >= RATE_LIMIT) {
    return res.status(429).json({ error: '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.' });
  }
  arr.push(now);
  rateLimitMap.set(ip, arr);
  next();
}

setInterval(() => {
  const cutoff = Date.now() - WINDOW_MS;
  for (const [ip, arr] of rateLimitMap.entries()) {
    const filtered = arr.filter(t => t > cutoff);
    if (filtered.length === 0) rateLimitMap.delete(ip);
    else rateLimitMap.set(ip, filtered);
  }
}, 10 * 60 * 1000);

app.post('/api/saju.php', rateLimit, async (req, res) => {
  try {
    const { name, gender, year, month, day, hour, sajuStr, ohStr } = req.body || {};

    if (!name || typeof name !== 'string' || name.length > 30) {
      return res.status(400).json({ error: '이름이 올바르지 않습니다.' });
    }
    if (!['남', '여'].includes(gender)) {
      return res.status(400).json({ error: '성별이 올바르지 않습니다.' });
    }
    const yr = parseInt(year), mo = parseInt(month), dy = parseInt(day);
    const hr = (hour === undefined || hour === null) ? -1 : parseInt(hour);
    if (yr < 1900 || yr > 2100) return res.status(400).json({ error: '연도가 올바르지 않습니다.' });
    if (mo < 1 || mo > 12 || dy < 1 || dy > 31) return res.status(400).json({ error: '날짜가 올바르지 않습니다.' });
    if (!sajuStr || typeof sajuStr !== 'string' || sajuStr.length > 200) {
      return res.status(400).json({ error: '사주 정보가 올바르지 않습니다.' });
    }

    const hourStr = hr >= 0 ? `${hr}시` : '(시간 미입력)';
    const prompt = `이름: ${name}, 성별: ${gender}, 생년월일: ${yr}년 ${mo}월 ${dy}일 ${hourStr}
사주팔자: ${sajuStr}
오행 구성: ${ohStr}

위 사주를 한국 전통 명리학에 기반하여 분석하고, 반드시 아래 JSON 형식으로만 응답하세요. JSON 외 다른 텍스트는 절대 포함하지 마세요.

{
  "headline": "20자 내외의 시적이고 감성적인 한 줄 제목",
  "intro": "${name}님을 직접 호명하며 시작하는 4~5문장의 개인화된 소개. 일주의 특성, 오행 구성의 의미, 전반적인 기질을 명리학 용어와 감성적 비유를 섞어 풀어내기.",
  "sections": [
    {"icon": "sparkles", "title": "15자 이내 매력적인 제목", "content": "성격과 기질 3~4문장."},
    {"icon": "briefcase", "title": "직업 관련 매력적인 제목", "content": "적합한 직업/재능 3~4문장."},
    {"icon": "heart", "title": "사랑 관련 매력적인 제목", "content": "사랑/인간관계 3~4문장."},
    {"icon": "coin", "title": "재물 관련 매력적인 제목", "content": "재물운 3~4문장."},
    {"icon": "activity", "title": "건강 관련 따뜻한 제목", "content": "건강 주의사항 3~4문장."},
    {"icon": "calendar-event", "title": "운세 관련 매력적인 제목", "content": "2025~2026년 운세 3~4문장."}
  ]
}

섹션 제목은 시적이고 호기심을 자극하는 카피로 작성하세요.`;

    const apiRes = await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'x-api-key': ANTHROPIC_API_KEY,
        'anthropic-version': '2023-06-01'
      },
      body: JSON.stringify({
        model: 'claude-sonnet-4-20250514',
        max_tokens: 2500,
        messages: [{ role: 'user', content: prompt }]
      })
    });

    if (!apiRes.ok) {
      const errBody = await apiRes.text();
      let errMsg = `API 오류 ${apiRes.status}`;
      try { errMsg = JSON.parse(errBody)?.error?.message || errMsg; } catch {}
      return res.status(502).json({ error: errMsg });
    }

    const data = await apiRes.json();
    const rawText = (data.content || []).filter(b => b.type === 'text').map(b => b.text).join('');
    const match = rawText.match(/\{[\s\S]*\}/);
    if (!match) return res.status(502).json({ error: 'AI 응답을 파싱할 수 없습니다.' });
    const aiResult = JSON.parse(match[0]);
    res.json(aiResult);
  } catch (e) {
    console.error('Error:', e);
    res.status(500).json({ error: '서버 내부 오류: ' + e.message });
  }
});

app.listen(PORT, () => {
  console.log(`사주마미 서버 실행 중: http://localhost:${PORT}`);
});
