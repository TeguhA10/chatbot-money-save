import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: 5,
  duration: '10s',
  thresholds: {
    http_req_duration: ['p(95)<500'],
    http_req_failed: ['rate<0.01'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8001';

export default function () {
  // 1. Health check
  const healthRes = http.get(`${BASE_URL}/api/health/live`);
  check(healthRes, {
    'Health is 200': (r) => r.status === 200,
  });

  // 2. Plans API (Supabase Query)
  const plansRes = http.get(`${BASE_URL}/api/v1/plans`);
  check(plansRes, {
    'Plans is 200': (r) => r.status === 200,
  });

  sleep(0.1);
}
