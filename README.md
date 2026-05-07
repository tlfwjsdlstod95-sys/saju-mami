# 사주마미 - AI 사주풀이 서비스

## 파일 구성

- `index.html` — 사용자 화면 (프론트엔드)
- `api/saju.php` — PHP 백엔드 (Cafe24, 카페24, 가비아, 닷홈 등 일반 호스팅)
- `server.js` — Node.js 백엔드 (Vercel, Railway, Render, AWS 등)
- `package.json` — Node.js 의존성

---

## 배포 방법 (둘 중 하나 선택)

### A. 일반 PHP 호스팅 (가장 쉬움)

#### 1. Anthropic API 키 발급
- https://console.anthropic.com 접속 → API Keys → Create Key
- 결제 카드 등록 (사용한 만큼만 청구됨, 1건 분석당 약 50~100원)

#### 2. 파일 업로드
호스팅 FTP에 다음과 같이 업로드:
```
public_html/
├── index.html
└── api/
    └── saju.php
```

#### 3. API 키 설정 (방법 둘 중 하나)
**방법 1 (권장):** 호스팅 제어판에서 환경변수 설정
- 환경변수명: `ANTHROPIC_API_KEY`
- 값: `sk-ant-api03-...`

**방법 2:** `saju.php` 파일 직접 수정
- `'sk-ant-여기에본인키입력'` 부분을 실제 키로 교체

#### 4. 도메인 접속해서 테스트
끝!

---

### B. Vercel 배포 (무료, Node.js)

#### 1. 깃허브에 코드 업로드
```bash
cd saju
git init
git add .
git commit -m "init"
gh repo create saju-mami --public --source=. --push
```

#### 2. Vercel 연결
- https://vercel.com 접속 → Import Project → 깃허브 저장소 선택
- 환경변수 추가:
  - Name: `ANTHROPIC_API_KEY`
  - Value: 본인 API 키
- Deploy 클릭

#### 3. 도메인 연결
Vercel 대시보드 → Domains → 본인 도메인 추가

---

## 보안 체크리스트

✅ API 키는 서버 환경변수로만 보관 (HTML 파일에 절대 넣지 말 것)
✅ Rate limiting 적용됨 (IP당 시간당 20회)
✅ 입력값 검증 (이름 30자 제한, 연도 1900~2100 등)
✅ HTTPS 사용 (Cloudflare 또는 Let's Encrypt 권장)

---

## 비용 추정

### Claude API 비용 (1건당)
- Claude Sonnet 4 기준
- 입력 약 800 토큰 + 출력 약 1500 토큰
- 1건당 약 50~100원

### 수익 모델 예시
- 무료: 기본 분석 1회 (광고 노출)
- 유료: 5,900원 → 대운/세운 추가, PDF 저장
- 프리미엄: 19,900원 → 1:1 전문가 상담 연결

손익분기: 1건 분석 = 100원 비용 / 5,900원 매출 = 약 60배 마진

---

## 추가 개선 아이디어

1. **결제 연동** (toss payments, 카카오페이)
2. **카카오 로그인** (이용자 정보 저장 → 재방문 유도)
3. **광고 SDK** (Google AdSense, 카카오 모먼트)
4. **구글 애널리틱스** (전환율 측정)
5. **카카오톡 공유하기** (바이럴)
6. **궁합 기능** (두 사람 사주 비교 → 객단가 상승)
7. **출생시간 모름 → 시간 추정** 기능 (유료)
8. **PDF 다운로드** (이름 새겨서 소장가치 부여)

---

## 사주 계산 정확도

- VSOP87 기반 태양황경 계산 → 절기 시각 정밀 산출
- 1900~2100년 범위 검증
- 입춘 기준 년주, 12절기 기준 월주, 갑자일 기준 일주
- 자시(23:00~00:59) 처리 (조자시/야자시 구분 없음)

특정 날짜 검증이 필요하면 한국천문연구원 만세력과 비교 권장.
