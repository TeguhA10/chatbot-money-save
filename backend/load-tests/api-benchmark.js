import http from 'k6/http';
import { check, sleep } from 'k6';

// k6 Options: Ramp-up, sustained load, and tear-down
export const options = {
  stages: [
    { duration: '5s', target: 20 },   // Warm-up to 20 VUs
    { duration: '15s', target: 50 },  // Ramp-up to 50 concurrent users
    { duration: '10s', target: 100 }, // Peak stress at 100 VUs
    { duration: '5s', target: 0 },    // Cool down
  ],
  thresholds: {
    // 95% of requests must complete below 300ms
    http_req_duration: ['p(95)<300'],
    // Less than 1% failed requests allowed
    http_req_failed: ['rate<0.01'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8001';

export default function () {
  // 1. Benchmark Health Liveness Probe
  const healthRes = http.get(`${BASE_URL}/api/health/live`);
  check(healthRes, {
    'Health endpoint status is 200': (r) => r.status === 200,
    'Health response is healthy': (r) => r.body.includes('healthy') || r.status === 200,
  });

  // 2. Benchmark Active Subscription Plans (DB Read)
  const plansRes = http.get(`${BASE_URL}/api/v1/plans`);
  check(plansRes, {
    'Plans status is 200': (r) => r.status === 200,
    'Plans returned valid data': (r) => r.json('success') === true,
  });

  // 3. Benchmark Bot Menus Categorized (DB Read & Grouping)
  const menusRes = http.get(`${BASE_URL}/api/v1/bot-menus`);
  check(menusRes, {
    'Menus status is 200': (r) => r.status === 200,
    'Menus returned valid data': (r) => r.json('success') === true,
  });

  sleep(0.1);
}
